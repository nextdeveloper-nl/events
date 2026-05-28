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

        try {
            $this->client()->publish($subject, json_encode($payload));
        } catch (\Throwable $e) {
            // Log and swallow — a NATS failure must never break the main request flow
            Log::error('[NatsService] Failed to publish', [
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
        $this->client()->subscribe($subject, function (string $message, string $replyTo, string $subject) use ($callback) {
            $payload = json_decode($message, true) ?? $message;
            $callback($payload, $subject, $replyTo ?: null);
        });
    }

    /**
     * Publish a reply to a replyTo subject received from a NATS request.
     */
    public function reply(string $replyTo, array $payload): void
    {
        try {
            $this->client()->publish($replyTo, json_encode($payload));
        } catch (\Throwable $e) {
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
        $this->client()->process($timeout);
    }

    private function client(): Client
    {
        if ($this->client === null) {
            // Only include keys with non-null values — typed properties reject null
            // Note: basis-company/nats does not support mTLS when server sends tls_required.
            // The library always calls enableTls(false), skipping client cert.
            // Authentication is handled via user/pass + auth callout.
            $config = array_filter([
                'host'      => config('events.nats.server_host', '127.0.0.1'),
                'port'      => (int) config('events.nats.server_port', 4222),
                'token'     => config('events.nats.token'),
                'user'      => config('events.nats.user'),
                'pass'      => config('events.nats.password'),
                'tlsCaFile' => self::resolvePath(config('events.nats.tls_ca')),
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
