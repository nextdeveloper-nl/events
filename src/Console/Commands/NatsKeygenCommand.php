<?php

namespace NextDeveloper\Events\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates an Ed25519 NKey account keypair for NATS auth callout.
 *
 * NATS auth_callout.issuer must be an Account NKey (starts with 'A').
 * The corresponding seed (starts with 'SA') is used to sign auth response JWTs.
 *
 * NKey prefix values (NOT ASCII):
 *   Account = 0   → public key starts with 'A',  seed starts with 'SA'
 *   Operator = 14 → public key starts with 'O',  seed starts with 'SO'
 *   User = 20     → public key starts with 'U',  seed starts with 'SU'
 *   Seed = 18     → used as the 'S' in seed prefixes
 *
 * Run once on first setup:
 *   php artisan events:nats-keygen
 */
class NatsKeygenCommand extends Command
{
    protected $signature   = 'events:nats-keygen';
    protected $description = 'Generate an account NKey keypair for NATS auth callout';

    // NKey prefix constants (NOT ASCII values)
    private const PREFIX_ACCOUNT = 0;
    private const PREFIX_SEED    = 18;

    public function handle(): int
    {
        $keypair   = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair); // 64 bytes
        $publicKey = sodium_crypto_sign_publickey($keypair); // 32 bytes

        // Sodium secret key = 32-byte seed + 32-byte public key
        $seed = substr($secretKey, 0, 32);

        $publicNkey = $this->encodePublicKey($publicKey);
        $seedNkey   = $this->encodeSeed($seed);

        $this->line('');
        $this->info('Account NKey keypair generated. Add these to your .env:');
        $this->line('');
        $this->line('NATS_ACCOUNT_NKEY_PUBLIC=' . $publicNkey);
        $this->line('NATS_ACCOUNT_NKEY_SEED='   . $seedNkey);
        $this->line('');
        $this->info('Add the public key to nats.conf authorization block:');
        $this->line('');
        $this->line('authorization {');
        $this->line('  auth_callout {');
        $this->line('    issuer: "' . $publicNkey . '"');
        $this->line('    auth_users: ["auth-service"]');
        $this->line('    account: AUTH');
        $this->line('  }');
        $this->line('}');
        $this->line('');
        $this->warn('Keep NATS_ACCOUNT_NKEY_SEED secret. Never commit it to version control.');

        return 0;
    }

    /**
     * Encode a 32-byte Ed25519 public key as an Account NKey.
     * Format: [1 byte: prefix<<3] + [32 bytes: key] + [2 bytes: CRC16 LE]
     * Result starts with 'A' (Account).
     */
    private function encodePublicKey(string $rawPublicKey): string
    {
        // Account NKey prefix = 0, shifted left 3 → b1 = 0
        // First base32 char = 0 → 'A'
        $b1  = self::PREFIX_ACCOUNT << 3;
        $raw = chr($b1) . $rawPublicKey;

        $crc  = $this->crc16($raw);
        $raw .= chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF);

        return $this->base32Encode($raw);
    }

    /**
     * Encode a 32-byte Ed25519 seed as an Account NKey seed.
     * Format: [2 bytes: seed+account prefix] + [32 bytes: seed] + [2 bytes: CRC16 LE]
     * Result starts with 'SA' (Seed + Account).
     */
    private function encodeSeed(string $rawSeed): string
    {
        // Two-byte seed prefix encodes Seed (18) + Account (0):
        //   b1 = (18 << 3) | (0 >> 5) = 144 → first base32 char = 18 → 'S'
        //   b2 = (0 & 31) << 3        =   0 → contributes 0 to second char → 'A'
        $b1  = (self::PREFIX_SEED << 3) | (self::PREFIX_ACCOUNT >> 5);
        $b2  = (self::PREFIX_ACCOUNT & 31) << 3;
        $raw = chr($b1) . chr($b2) . $rawSeed;

        $crc  = $this->crc16($raw);
        $raw .= chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF);

        return $this->base32Encode($raw);
    }

    private function base32Encode(string $input): string
    {
        $alpha  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bits   = 0;

        foreach (str_split($input) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits  += 8;
            while ($bits >= 5) {
                $bits   -= 5;
                $output .= $alpha[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $output .= $alpha[($buffer << (5 - $bits)) & 0x1F];
        }

        return $output;
    }

    private function crc16(string $data): int
    {
        $crc = 0x0000;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1;
                $crc &= 0xFFFF;
            }
        }
        return $crc;
    }
}
