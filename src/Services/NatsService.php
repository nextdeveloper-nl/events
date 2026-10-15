<?php

namespace NextDeveloper\Events\Services;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NextDeveloper\Events\Exceptions\AgentTimeoutException;

/**
 * Wraps the NATS client with lazy connection and config-driven setup.
 * Connection is established on first publish/subscribe and reused for the lifetime of the process.
 *
 * Uses NATS_SERVER_HOST / NATS_SERVER_PORT for server-side PHP connections (raw TCP, port 4222).
 * NATS_HOST / NATS_PORT (port 443) are for browser WebSocket connections only.
 */
class NatsService
{
    //  A publish-only client never answers the broker's PINGs, and the broker eventually closes
    //  such a connection without the next write noticing. So a reused connection is checked with
    //  ping() after it has been idle for KEEPALIVE_IDLE_SECONDS, and replaced outright after
    //  RECYCLE_AFTER_SECONDS or MAX_PUBLISHES_PER_CONNECTION, whichever comes first.
    private const KEEPALIVE_IDLE_SECONDS = 15;
    private const RECYCLE_AFTER_SECONDS = 60;
    private const MAX_PUBLISHES_PER_CONNECTION = 1000;

    private ?Client $client = null;

    private float $connectedAt = 0.0;
    private float $lastUsedAt = 0.0;
    private int $publishCount = 0;

    public function publish(string $subject, array $payload): void
    {
        if (!config('events.nats.enabled', false)) {
            return;
        }

        //  Invalid UTF-8 (binary-ish fields in a model) makes json_encode() return false, which
        //  used to surface as a TypeError and a lost event. Substitute the bad bytes instead.
        $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        try {
            $this->execute(function () use ($subject, $encoded) {
                $this->refreshConnection();
                $this->client()->publish($subject, $encoded);
            });
            $this->markUsed();
            return;
        } catch (\Throwable $e) {
            // Free the broken socket so the reconnect attempt gets a fresh FD.
            $this->dropClient();

            Log::warning('[NatsService] Publish failed, retrying with fresh connection', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }

        // Single retry with a freshly opened connection.
        try {
            $this->execute(fn () => $this->client()->publish($subject, $encoded));
            $this->markUsed();
        } catch (\Throwable $e) {
            $this->dropClient();

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
            //  The subject the message was published on, not the one subscribed: under a wildcard
            //  (`agent.status.*`, `fixlean.sensor.>`) the tokens it matched are what identify the sender.
            $callback($payload, $message->subject ?: $subject, $replyTo ?: null);
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
     * Publish a command to a JetStream subject and block until the agent replies.
     *
     * Unlike the native NATS request-reply pattern, this method does NOT set a
     * NATS replyTo header — that would cause JetStream to respond with a PubAck
     * instead of the agent's result. Instead, the inbox subject is embedded in
     * the payload as "reply_to" so the agent can publish its result there directly.
     *
     * @param  string $subject   Target subject (e.g. agent.vm.{uuid}.cmd)
     * @param  array  $payload   Command envelope; "reply_to" will be injected automatically
     * @param  float  $timeout   Seconds to wait for a reply
     * @return array             Decoded reply payload from the agent
     * @throws \NextDeveloper\Events\Exceptions\AgentTimeoutException
     */
    public function dispatch(string $subject, array $payload, float $timeout = 10.0): array
    {
        $inbox    = '_INBOX.' . Str::uuid()->toString();
        $result   = null;
        $deadline = microtime(true) + $timeout;

        try {
            // Subscribe to our temporary inbox before publishing
            // The Basis NATS client passes a Payload object, not a raw string
            $this->client()->subscribe($inbox, function (\Basis\Nats\Message\Payload|string $message) use (&$result) {
                $result = json_decode((string) $message, true) ?? [];
            });

            // Embed the inbox so the agent knows where to publish the result
            $payload['reply_to'] = $inbox;

            // Plain publish — no NATS replyTo header, avoids JetStream PubAck
            $this->client()->publish($subject, json_encode($payload));

            // Poll until the agent replies or we time out
            while ($result === null && microtime(true) < $deadline) {
                $this->client()->process(0.1);
            }
        } catch (\Throwable $e) {
            // Covers the lazy connect (raw TCP/TLS "Connection timed out" etc.) as well as
            // subscribe/publish/process failures — none of these are the agent's fault, but
            // callers only need to know "no result within budget", so surface them the same
            // way as a reply timeout instead of letting a raw transport exception bubble up.
            $this->client = null;

            Log::warning('[NatsService] Dispatch failed before/while waiting for reply', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);

            throw new AgentTimeoutException(
                "Agent did not respond on [{$subject}] within {$timeout}s ({$e->getMessage()})"
            );
        }

        if ($result === null) {
            throw new AgentTimeoutException(
                "Agent did not respond on [{$subject}] within {$timeout}s"
            );
        }

        return $result;
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

    /**
     * Keep a long-lived connection healthy between publishes (queue workers reuse one service
     * instance for thousands of jobs, so a connection is opened once instead of once per job).
     * A connection that holds subscriptions - the nats:listen command - is never recycled.
     */
    private function refreshConnection(): void
    {
        if ($this->client === null || $this->client->getSubscriptions() !== []) {
            return;
        }

        $now = microtime(true);

        if (($now - $this->connectedAt) > self::RECYCLE_AFTER_SECONDS
            || $this->publishCount >= self::MAX_PUBLISHES_PER_CONNECTION) {
            $this->dropClient();
            return;
        }

        if (($now - $this->lastUsedAt) > self::KEEPALIVE_IDLE_SECONDS) {
            try {
                if (!$this->client->ping()) {
                    $this->dropClient();
                }
            } catch (\Throwable $e) {
                $this->dropClient();
            }
        }
    }

    private function markUsed(): void
    {
        $this->lastUsedAt = microtime(true);
        $this->publishCount++;
    }

    /**
     * Close the socket (so its file descriptor is released at once instead of whenever the
     * garbage collector gets to it) and forget the client; the next use opens a new one.
     */
    private function dropClient(): void
    {
        if ($this->client !== null) {
            try {
                $this->client->disconnect();
            } catch (\Throwable $e) {
                // The connection is already broken; there is nothing more to close.
            }
        }

        $this->client = null;
        $this->publishCount = 0;
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

            // Configuration's own default (1s) is tight for a WAN link doing a
            // client-cert TLS handshake - occasional latency/broker-load spikes can hit
            // it even when the broker itself is healthy (e.g. still reachable via the
            // browser's separate WSS connection), producing an intermittent raw
            // "Connection timed out" instead of the graceful AgentTimeoutException
            // callers actually handle. This is the connect step only, distinct from
            // dispatch()'s own $timeout param above, which governs how long to wait for
            // a reply once already connected.
            $config['timeout'] = (float) config('events.nats.connect_timeout', 5);

            $this->client = new Client(new Configuration($config));
            $this->connectedAt = microtime(true);
            $this->lastUsedAt = $this->connectedAt;
            $this->publishCount = 0;
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
