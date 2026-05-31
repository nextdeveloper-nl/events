<?php

namespace NextDeveloper\Events\Services;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the NATS client with lazy connection and config-driven setup.
 * Connection is established on first publish/subscribe and reused for the lifetime of the process.
 *
 * Uses NATS_SERVER_HOST / NATS_SERVER_PORT for server-side PHP connections (raw TCP, port 4222).
 * NATS_HOST / NATS_PORT (port 443) are for browser WebSocket connections only.
 */
class NatsService
{
    private ?Client $client = null;

    public function publish(string $subject, array $payload): void
    {
        if (!config('events.nats.enabled', false)) {
            return;
        }

        $encoded = json_encode($payload);

        try {
            $this->execute(fn () => $this->client()->publish($subject, $encoded));
            return;
        } catch (\Throwable $e) {
            // Free the broken socket so the reconnect attempt gets a fresh FD.
            $this->client = null;

            Log::warning('[NatsService] Publish failed, retrying with fresh connection', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }

        // Single retry with a freshly opened connection.
        try {
            $this->execute(fn () => $this->client()->publish($subject, $encoded));
        } catch (\Throwable $e) {
            $this->client = null;

            Log::error('[NatsService] Failed to publish after retry', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Subscribe to a subject.
     * Callback signature: function(array|string $payload, string $subject, ?string $replyTo)
     * Call process() in a loop to consume messages.
     */
    public function subscribe(string $subject, callable $callback): void
    {
        $this->client()->subscribe($subject, function (\Basis\Nats\Message\Payload $message, ?string $replyTo) use ($callback, $subject) {
            $raw     = (string) $message;
            $payload = json_decode($raw, true) ?? $raw;
            $callback($payload, $subject, $replyTo ?: null);
        });
    }

    /**
     * Publish a reply to a replyTo subject received from a NATS request.
     */
    public function reply(string $replyTo, array $payload): void
    {
        try {
            $this->execute(fn () => $this->client()->publish($replyTo, json_encode($payload)));
        } catch (\Throwable $e) {
            $this->client = null;

            Log::error('[NatsService] Failed to send reply', [
                'reply_to' => $replyTo,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process pending messages from the NATS server.
     * Call this repeatedly inside a loop in long-running processes (e.g. Artisan commands).
     *
     * @param float $timeout seconds to wait for messages (0 = non-blocking)
     */
    public function process(float $timeout = 0.1): void
    {
        $this->execute(fn () => $this->client()->process($timeout));
    }

    /**
     * Wraps a NATS operation so that PHP E_WARNING emissions from stream_select()
     * (triggered when open socket FD numbers exceed FD_SETSIZE=1024 in long-running
     * queue workers) are converted into catchable exceptions instead of silent failures.
     */
    private function execute(callable $fn): void
    {
        set_error_handler(function (int $errno, string $errstr): bool {
            if (str_contains($errstr, 'FD_SETSIZE') || str_contains($errstr, 'stream_select')) {
                throw new \RuntimeException('[NatsService] ' . $errstr, $errno);
            }
            // Let all other warnings/errors pass through to the default handler.
            return false;
        });

        try {
            $fn();
        } finally {
            restore_error_handler();
        }
    }

    private function client(): Client
    {
        if ($this->client === null) {
            // Connect as auth-service — this user bypasses the auth callout,
            // giving the platform full publish access to any subject.
            $config = array_filter([
                'host'        => config('events.nats.server_host', '127.0.0.1'),
                'port'        => (int) config('events.nats.server_port', 4222),
                'user'        => 'auth-service',
                'pass'        => config('events.nats.auth_service_password'),
                'tlsCaFile'   => self::resolvePath(config('events.nats.tls_ca')),
                'tlsCertFile' => self::resolvePath(config('events.nats.tls_cert')),
                'tlsKeyFile'  => self::resolvePath(config('events.nats.tls_key')),
            ], fn ($v) => $v !== null && $v !== '');

            // Restore port — it could be filtered if it's 0
            $config['port'] = (int) config('events.nats.server_port', 4222);

            $this->client = new Client(new Configuration($config));
        }

        return $this->client;
    }

    /**
     * Resolve a potentially relative path to an absolute path using base_path().
     * SSL context functions require absolute paths.
     */
    private static function resolvePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path; // already absolute
        }

        return base_path($path);
    }
}
