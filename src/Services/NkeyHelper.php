<?php

namespace NextDeveloper\Events\Services;

/**
 * Minimal NKey utility for NATS auth callout JWT signing.
 *
 * NKey prefix constants (NOT ASCII values):
 *   Account  = 0   → public key starts with 'A',  seed starts with 'SA'
 *   Operator = 14  → public key starts with 'O',  seed starts with 'SO'
 *   User     = 20  → public key starts with 'U',  seed starts with 'SU'
 *   Seed     = 18  → the 'S' prefix character for seeds
 *
 * Requires the PHP sodium extension (available in PHP 7.2+).
 */
class NkeyHelper
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // NKey type prefix values (not ASCII)
    private const PREFIX_ACCOUNT  = 0;
    private const PREFIX_OPERATOR = 14;
    private const PREFIX_USER     = 20;
    private const PREFIX_SEED     = 18;

    /**
     * Decode an NKey seed string to its raw 32-byte Ed25519 seed.
     *
     * Seed structure after base32 decode:
     *   [2 bytes: type prefix] [32 bytes: Ed25519 seed] [2 bytes: CRC16 LE]
     */
    public static function decodeSeed(string $nkeySeed): string
    {
        $raw = self::base32Decode(strtoupper(trim($nkeySeed)));

        if (strlen($raw) < 34) {
            throw new \InvalidArgumentException('NKey seed too short after base32 decode (got ' . strlen($raw) . ' bytes, need 34+)');
        }

        // Skip 2-byte type prefix and 2-byte CRC → 32 bytes of Ed25519 seed
        return substr($raw, 2, 32);
    }

    /**
     * Build and sign a NATS JWT using an Account NKey seed.
     *
     * @param array  $payload        JWT payload
     * @param string $accountNkeySeed Account NKey seed (starts with SA...)
     */
    public static function signJwt(array $payload, string $accountNkeySeed): string
    {
        $header = self::b64url(json_encode(
            ['alg' => 'ed25519-nkey', 'typ' => 'JWT'],
            JSON_UNESCAPED_SLASHES
        ));

        $body = self::b64url(json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        $signingInput = $header . '.' . $body;

        $seed      = self::decodeSeed($accountNkeySeed);
        $keypair   = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $signature = sodium_crypto_sign_detached($signingInput, $secretKey);

        return $signingInput . '.' . self::b64url($signature);
    }

    // -------------------------------------------------------------------------

    private static function base32Decode(string $input): string
    {
        $alpha  = self::BASE32_ALPHABET;
        $buffer = 0;
        $bits   = 0;
        $output = '';

        foreach (str_split($input) as $char) {
            $pos = strpos($alpha, $char);
            if ($pos === false) {
                continue;
            }
            $buffer  = ($buffer << 5) | $pos;
            $bits   += 5;
            if ($bits >= 8) {
                $bits   -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Generate a temporary User NKey public key for use as the `sub` field
     * in an authorization_response JWT. The private key is discarded — NATS
     * uses this only as a session identity, not for further signing.
     */
    public static function generateUserPublicKey(): string
    {
        $keypair   = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair); // 32 bytes

        // User NKey prefix = 20 → b1 = 20 << 3 = 160 → first base32 char = 'U'
        $b1  = self::PREFIX_USER << 3;
        $raw = chr($b1) . $publicKey;

        $crc  = self::crc16($raw);
        $raw .= chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF);

        return self::base32Encode($raw);
    }

    private static function base32Encode(string $input): string
    {
        $alpha  = self::BASE32_ALPHABET;
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

    private static function crc16(string $data): int
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

    public static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
