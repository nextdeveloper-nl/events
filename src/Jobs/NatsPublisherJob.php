<?php

namespace NextDeveloper\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Events\Services\NatsService;

/**
 * Publishes a model event to NATS so browser clients (via NATS WebSocket)
 * and other subscribers can receive real-time updates.
 *
 * Subjects published:
 *   account.events.{account_uuid}                        — all events for the account
 *   account.events.{account_uuid}.{model_slug}           — filtered by model type
 *
 * Register this job as a listener via:
 *   Events::listen('updated:NextDeveloper\IAAS\ComputeMembers', NatsPublisherJob::class);
 */
class NatsPublisherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Model $model,
        private readonly array $params
    ) {
        $this->queue = config('events.nats.queue', 'default');
    }

    public function handle(NatsService $nats): void
    {
        $accountUuid = $this->resolveAccountUuid();

        if ($accountUuid === null) {
            Log::debug('[NatsPublisherJob] Skipping model without iam_account_id', [
                'model' => get_class($this->model),
                'event' => $this->params['event'] ?? null,
            ]);
            return;
        }

        $payload = $this->buildPayload($accountUuid);

        // Broad subject — browser subscribes to this for all account events
        $nats->publish("account.events.{$accountUuid}", $payload);

        // Narrower subject — allows filtering by model type
        $modelSlug = $this->modelSlug();
        $nats->publish("account.events.{$accountUuid}.{$modelSlug}", $payload);
    }

    private function buildPayload(string $accountUuid): array
    {
        return [
            'event'        => $this->params['event'] ?? null,
            'model'        => class_basename($this->model),
            'model_class'  => get_class($this->model),
            'uuid'         => $this->model->uuid ?? null,
            'account_uuid' => $accountUuid,
            'data'         => $this->model->toArray(),
            'fired_at'     => now()->toIso8601String(),
        ];
    }

    private function resolveAccountUuid(): ?string
    {
        $accountId = $this->model->iam_account_id ?? null;

        if ($accountId === null) {
            return null;
        }

        // Use DB directly to avoid pulling the full IAM model dependency
        return DB::table('iam_accounts')
            ->where('id', $accountId)
            ->value('uuid');
    }

    private function modelSlug(): string
    {
        // "NextDeveloper\IAAS\ComputeMembers" → "compute-members"
        return \Illuminate\Support\Str::kebab(class_basename($this->model));
    }
}
