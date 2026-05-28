<?php

namespace NextDeveloper\Events\Console\Commands;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Events\Services\NatsAuthCalloutService;

/**
 * NATS auth callout service.
 *
 * Connects to NATS using the pre-authorized auth-service account (bypasses
 * the callout itself), subscribes to $SYS.REQ.USER.AUTH, and validates
 * every incoming client connection in real time.
 *
 * Must run continuously alongside the queue worker and nats-listen command.
 *
 * Requires in .env:
 *   NATS_ACCOUNT_NKEY_PUBLIC=AAAA...
 *   NATS_ACCOUNT_NKEY_SEED=SAAA...
 *   NATS_AUTH_SERVICE_PASSWORD=strong-password
 *   NATS_TLS_CA=storage/nats-ca.crt
 *   NATS_TLS_CERT=storage/platform.leo4.crt
 *   NATS_TLS_KEY=storage/platform.leo4.key
 *
 * Supervisor config (same pattern as events:nats-listen):
 *   [program:nats-auth]
 *   command=php /var/www/artisan events:nats-auth-listen
 */
class NatsAuthListenerCommand extends Command
{
    protected $signature   = 'events:nats-auth-listen';
    protected $description = 'NATS auth callout service — validates credentials for every NATS connection';

    private bool $shouldQuit = false;

    // Seconds to wait before attempting to reconnect after a dropped connection
    private const RECONNECT_DELAY = 3;

    public function handle(NatsAuthCalloutService $authService): int
    {
        if (!config('events.nats.enabled', false)) {
            $this->error('NATS is not enabled. Set NATS_ENABLED=true in your .env file.');
            return 1;
        }

        if (!config('events.nats.account_nkey_seed')) {
            $this->error('NATS_ACCOUNT_NKEY_SEED is not set. Run: php artisan events:nats-keygen');
            return 1;
        }

        if (!config('events.nats.auth_service_password')) {
            $this->error('NATS_AUTH_SERVICE_PASSWORD is not set.');
            return 1;
        }

        $this->registerSignalHandlers();

        while (!$this->shouldQuit) {
            $this->runSession($authService);

            if (!$this->shouldQuit) {
                Log::warning('[NatsAuthListener] Reconnecting in ' . self::RECONNECT_DELAY . 's...');
                $this->warn('NATS connection lost — reconnecting in ' . self::RECONNECT_DELAY . 's...');
                sleep(self::RECONNECT_DELAY);
            }
        }

        $this->info('NATS auth listener stopped.');
        return 0;
    }

    private function runSession(NatsAuthCalloutService $authService): void
    {
        try {
            $client = $this->connect();

            // The library's processMsg auto-replies with the callback's return value.
            // We MUST return the JWT rather than calling publish() manually — otherwise
            // the library sends a second empty message to the same replyTo, which
            // overwrites the valid JWT and causes NATS to reject the auth response.
            $client->subscribe(
                '$SYS.REQ.USER.AUTH',
                function (\Basis\Nats\Message\Payload $payload, ?string $replyTo) use ($authService) {
                    $requestJwt = $payload->body;

                    if (!$replyTo) {
                        Log::warning('[NatsAuthListener] Auth request has no replyTo subject');
                        return null;
                    }

                    try {
                        return $authService->handle($requestJwt);
                    } catch (\Throwable $e) {
                        Log::error('[NatsAuthListener] Exception during auth', ['error' => $e->getMessage()]);
                        return $authService->handle('');
                    }
                }
            );

            $this->info('NATS auth callout service is listening on $SYS.REQ.USER.AUTH');

            while (!$this->shouldQuit) {
                $client->process(0.1);
                pcntl_signal_dispatch();
            }
        } catch (\Throwable $e) {
            Log::error('[NatsAuthListener] Connection error', ['error' => $e->getMessage()]);
            $this->error('[NatsAuthListener] ' . $e->getMessage());
        }
    }

    private function connect(): Client
    {
        $config = [
            'host' => config('events.nats.server_host', '127.0.0.1'),
            'port' => (int) config('events.nats.server_port', 4222),
            'user' => 'auth-service',
            'pass' => (string) config('events.nats.auth_service_password'),
        ];

        if (config('events.nats.tls_ca')) {
            $config['tlsCaFile'] = $this->resolvePath(config('events.nats.tls_ca'));
        }

        if (config('events.nats.tls_cert')) {
            $config['tlsCertFile'] = $this->resolvePath(config('events.nats.tls_cert'));
        }

        if (config('events.nats.tls_key')) {
            $config['tlsKeyFile'] = $this->resolvePath(config('events.nats.tls_key'));
        }

        return new Client(new Configuration($config));
    }

    private function resolvePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    private function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGINT,  fn () => $this->shouldQuit = true);
    }
}
