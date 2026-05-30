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
 * Creates (or updates) the JetStream streams required by the platform.
 *
 * Run once at container startup via start.sh — idempotent, safe to run repeatedly.
 *
 * Streams created:
 *
 *   AGENT_COMMANDS
 *     Subjects : agent.>
 *     Retention: Limits
 *     Storage  : File
 *     Max age  : 24 hours
 *     Max msgs : 1 000 per subject
 *     Discard  : Old
 *
 *   VM_TELEMETRY
 *     Subjects : vm.*.telemetry
 *     Retention: Limits
 *     Storage  : Memory (hot reads, no persistence needed)
 *     Max age  : 15 minutes — telemetry older than this is stale
 *     Max msgs : 60 per subject (~30s interval × 15 min × 2 buffer)
 *     Discard  : Old
 *     Purpose  : OAuth clients subscribe here to get live + last-15-min telemetry
 */
class NatsSetupStreamsCommand extends Command
{
    protected $signature   = 'events:nats-setup-streams';
    protected $description = 'Create or update JetStream streams for durable agent command delivery';

    private const AGENT_COMMANDS_MAX_AGE_NS  = 24 * 60 * 60 * 1_000_000_000;
    private const VM_TELEMETRY_MAX_AGE_NS    = 15 * 60 * 1_000_000_000;

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
        $this->setupVmTelemetryStream($client);

        return 0;
    }

    private function setupAgentCommandsStream(Client $client): void
    {
        $stream = $client->getApi()->getStream('AGENT_COMMANDS');

        if ($stream->exists()) {
            // Skip update — loading an existing stream config via fromObject() serialises
            // consumer_limits as [] which the NATS server rejects (expects an object).
            // The stream config is stable; delete and re-run if you need to change it.
            $this->info('AGENT_COMMANDS stream already exists — skipping.');
            Log::info('[NatsSetupStreams] AGENT_COMMANDS stream already exists, skipped');
        } else {
            $stream->getConfiguration()
                ->setSubjects(['agent.>'])
                ->setStorageBackend(StorageBackend::FILE)
                ->setRetentionPolicy(RetentionPolicy::LIMITS)
                ->setDiscardPolicy(DiscardPolicy::OLD)
                ->setMaxAge(self::AGENT_COMMANDS_MAX_AGE_NS)
                ->setMaxMessagesPerSubject(1000)
                ->setReplicas(1)
                ->setDescription('Durable agent command delivery — compute, storage, network, broadcast');

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

    private function setupVmTelemetryStream(Client $client): void
    {
        $stream = $client->getApi()->getStream('VM_TELEMETRY');

        if ($stream->exists()) {
            $this->info('VM_TELEMETRY stream already exists — skipping.');
            Log::info('[NatsSetupStreams] VM_TELEMETRY stream already exists, skipped');
        } else {
            $stream->getConfiguration()
                ->setSubjects(['vm.*.telemetry'])
                ->setStorageBackend(StorageBackend::MEMORY)
                ->setRetentionPolicy(RetentionPolicy::LIMITS)
                ->setDiscardPolicy(DiscardPolicy::OLD)
                ->setMaxAge(self::VM_TELEMETRY_MAX_AGE_NS)
                ->setMaxMessagesPerSubject(60)
                ->setReplicas(1)
                ->setDescription('VM telemetry — last 15 minutes per VM, readable by OAuth clients');

            $stream->create();
            $this->info('VM_TELEMETRY stream created.');
            Log::info('[NatsSetupStreams] VM_TELEMETRY stream created');
        }

        $this->line('  Subjects : vm.*.telemetry');
        $this->line('  Storage  : memory');
        $this->line('  Max age  : 15 minutes');
        $this->line('  Max msgs : 60 per VM');
    }

    private function resolvePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
