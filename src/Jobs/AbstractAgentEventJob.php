<?php

namespace NextDeveloper\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Events\Database\Models\AgentCommands;
use NextDeveloper\Events\Services\AgentCommandsService;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Shared base for handling inbound agent.{type}.{uuid}.evt messages, dispatched
 * by NatsListenCommand (config('events.nats.subscribers')) for every module's
 * agent (vm, compute, s3, backup, dns, ...).
 *
 * Owns everything that's genuinely uniform across agent types: envelope
 * validation, admin-context elevation (so the AgentCommands DB writes below
 * don't hit AgentCommandsObserver's UserHelper::can() check with no current
 * user), exception logging, and closing out `result` messages against the
 * shared event_agent_commands table. Per-module subclasses only implement the
 * hooks that are legitimately different (field names, domain-specific event
 * types) - see resolveAgentModel()/updateHeartbeat()/handleDomainEvent().
 */
abstract class AbstractAgentEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected readonly array   $envelope,
        protected readonly string  $subject,
        protected readonly ?string $replyTo = null
    ) {
    }

    public function handle(): void
    {
        // Unconditional receipt trace, logged before any validation/lookup can
        // short-circuit - proves the queue worker actually picked up and ran
        // this job for this message, independent of whether routing below
        // succeeds. Deliberately `info`, not `debug`: production log levels
        // commonly exclude debug, and this is the one line that answers
        // "did the platform receive this at all?" during a live investigation.
        Log::info(static::class . ': envelope received', [
            'subject' => $this->subject,
            'type'    => $this->envelope['type'] ?? null,
            'agent_uuid' => $this->envelope['agent_uuid'] ?? null,
        ]);

        if (!is_array($this->envelope) || !isset($this->envelope['type'], $this->envelope['agent_uuid'])) {
            Log::warning(static::class . ': malformed envelope', [
                'subject'  => $this->subject,
                'envelope' => $this->envelope,
            ]);
            return;
        }

        $type      = $this->envelope['type'];
        $agentUuid = $this->envelope['agent_uuid'];
        $payload   = $this->envelope['payload'] ?? [];

        $run = fn () => $this->route($type, $agentUuid, $payload);

        try {
            UserHelper::me() ? $run() : UserHelper::runAsAdmin($run);
        } catch (\Throwable $e) {
            Log::error(static::class . ': exception while handling envelope', [
                'subject'   => $this->subject,
                'type'      => $type,
                'exception' => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
            throw $e;
        }
    }

    private function route(string $type, string $agentUuid, array $payload): void
    {
        if ($type === 'heartbeat') {
            // Fires the instant a heartbeat is dequeued and handle()d, before any
            // DB lookup - deliberately NOT gated on resolveAgentModel() finding a
            // matching row, so it proves receipt independent of whether the
            // uuid-to-VM match succeeds. uuid is inlined into the message text
            // itself (not just the context array) for a plain-text Graylog search.
            Log::info("[AgentHeartbeat] received from {$agentUuid}");
        }

        $model = $this->resolveAgentModel($agentUuid);

        Log::info(static::class . ': routing', [
            'agent_uuid' => $agentUuid,
            'type'       => $type,
            'resolved'   => $model !== null,
            'model_id'   => $model->id ?? null,
        ]);

        if ($model) {
            $this->onAnyEvent($model, $type, $payload);
        }

        if ($type === 'result') {
            $command = AgentCommandsService::completeFromResult($payload);
            $this->onCommandResult($model, $command, $payload);
            return;
        }

        if (!$model) {
            // diagnoseUnknownAgent() lets each module explain *why* the lookup
            // missed (soft-deleted row? exists under a different scope? truly
            // no such row?) without having to reproduce the issue live - see
            // HandleVmAgentEventJob/HandleComputeAgentEventJob for the checks.
            Log::warning(static::class . ': unknown agent_uuid', [
                'agent_uuid' => $agentUuid,
                'type'       => $type,
                'payload'    => $payload,
                'diagnosis'  => $this->diagnoseUnknownAgent($agentUuid),
            ]);
            return;
        }

        match ($type) {
            'heartbeat'    => $this->updateHeartbeat($model, $payload),
            'capabilities' => $this->updateCapabilities($model, $payload['operations'] ?? []),
            default        => $this->handleDomainEvent($type, $model, $payload),
        };
    }

    /**
     * Look up the owning resource (VM, ComputeMember, S3 Server, BackupAgent,
     * DnsServer, ...) by its agent UUID. Return null if not found.
     */
    abstract protected function resolveAgentModel(string $agentUuid);

    /**
     * Called only when resolveAgentModel() returns null, to explain *why* in
     * the 'unknown agent_uuid' warning - e.g. whether a row exists but is
     * soft-deleted, or belongs to a different table, versus truly not
     * existing anywhere. Default no-op (empty diagnosis) so modules that
     * don't override it just get the bare warning as before.
     */
    protected function diagnoseUnknownAgent(string $agentUuid): array
    {
        return [];
    }

    /**
     * Persist a heartbeat (last-seen timestamp, agent version, etc). Field
     * names genuinely differ per module (agent_latest_ping vs
     * agent_last_seen_at+agent_status vs last_seen_at+health).
     */
    abstract protected function updateHeartbeat($model, array $payload): void;

    /**
     * Merge reported operations into the model's allow-list. Default no-op -
     * only VM/ComputeMember have an available_operations column.
     */
    protected function updateCapabilities($model, array $operations): void
    {
    }

    /**
     * Runs for every message type (including 'result') once the model is
     * resolved - e.g. S3 treats any inbound envelope as proof the agent is
     * alive and refreshes agent_status/agent_last_seen_at here regardless of
     * what specific type it turns out to be. Default no-op.
     */
    protected function onAnyEvent($model, string $type, array $payload): void
    {
    }

    /**
     * Runs for every 'result' message once AgentCommandsService has attempted to
     * close out its event_agent_commands row. $command is null when no matching
     * row was found (e.g. the command predates this module going through
     * AgentCommandsService::dispatch(), or a test fabricates a result with no
     * prior dispatch) - subclasses that only need the result payload itself
     * (health/liveness side effects) should still act in that case; subclasses
     * that need data off the command row (e.g. DNS's zone/record reconciliation,
     * keyed by $command->params) should simply return early when it's null.
     * $model is null when the agent_uuid itself is unrecognized. Default no-op.
     */
    protected function onCommandResult($model, ?AgentCommands $command, array $payload): void
    {
    }

    /**
     * Handles every event type not already covered above (telemetry,
     * s3_telemetry, s3_audit, job_run, alert, xapi_event, ipmi, ...).
     */
    abstract protected function handleDomainEvent(string $type, $model, array $payload): void;
}
