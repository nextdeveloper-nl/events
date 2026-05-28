<?php

namespace NextDeveloper\Events\Console\Commands;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Basis\Nats\Stream\DiscardPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Creates (or updates) the JetStream streams required for durable agent command delivery.
 *
 * Run once at container startup via start.sh — idempotent, safe to run repeatedly.
 *
 * Streams created:
 *
 *   AGENT_COMMANDS
 *     Subjects : agent.>
 *     Retention: Limits (each durable consumer tracks its own position)
 *     Storage  : File (survives container restarts)
 *     Max age  : 24 hours — commands older than this are irrelevant
 *     Max msgs : 1 000 per subject — prevents unbounded queuing per agent
 *     Discard  : Old — when limits are hit, oldest messages are dropped
 *
 * Agents (Go) create their own durable consumers filtered to their subject
 * (e.g. agent.compute.{uuid}.cmd) when they connect. On reconnect they resume
 * from where they left off, receiving any commands queued while offline.
 */
class NatsSetupStreamsCommand extends Command
{
    protected $signature   = 'events:nats-setup-streams';
    protected $description = 'Create or update JetStream streams for durable agent command delivery';

    // 24 hours in nanoseconds
    private const MAX_AGE_NS = 24 * 3600 * 1_000_000_000;

    public function handle(): int
    {
        if (!config('events.nats.enabled', false)) {
            $this->error('NATS is not enabled. Set NATS_ENABLED=true in your .env file.');
            return 1;
        }

        $config = array_filter([
            'host'        => config('events.nats.server_host', '127.0.0.1'),
            'port'        => (int) config('events.nats.server_port', 4222),
            'user'        => 'auth-service',
            'pass'        => config('events.nats.auth_service_password'),
            'tlsCaFile'   => $this->resolvePath(config('events.nats.tls_ca')),
            'tlsCertFile' => $this->resolvePath(config('events.nats.tls_cert')),
            'tlsKeyFile'  => $this->resolvePath(config('events.nats.tls_key')),
        ], fn ($v) => $v !== null && $v !== '');

        $config['port'] = (int) config('events.nats.server_port', 4222);

        $client = new Client(new Configuration($config));

        $this->setupAgentCommandsStream($client);

        return 0;
    }

    private function setupAgentCommandsStream(Client $client): void
    {
        $stream = $client->getStream('AGENT_COMMANDS');

        $stream->getConfiguration()
            ->setSubjects(['agent.>'])
            ->setStorageBackend(StorageBackend::FILE)
            ->setRetentionPolicy(RetentionPolicy::LIMITS)
            ->setDiscardPolicy(DiscardPolicy::OLD)
            ->setMaxAge(self::MAX_AGE_NS)
            ->setMaxMessagesPerSubject(1000)
            ->setReplicas(1)
            ->setDescription('Durable agent command delivery — compute, storage, network, broadcast');

        if ($stream->exists()) {
            $stream->update();
            $this->info('AGENT_COMMANDS stream updated.');
            Log::info('[NatsSetupStreams] AGENT_COMMANDS stream updated');
        } else {
            $stream->create();
            $this->info('AGENT_COMMANDS stream created.');
            Log::info('[NatsSetupStreams] AGENT_COMMANDS stream created');
        }

        $this->line('  Subjects : agent.>');
        $this->line('  Storage  : file');
        $this->line('  Retention: limits');
        $this->line('  Max age  : 24h');
        $this->line('  Max msgs : 1 000 per subject');
    }

    private function resolvePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
