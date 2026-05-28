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
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use NextDeveloper\Events\Services\NatsService;

/**
 * Publishes a model event to NATS so browser clients (via NATS WebSocket)
 * can receive real-time updates.
 *
 * Published subject:
 *   client.{account_uuid}.evt
 *
 * Payload:
 *   {
 *     "event":       "created:NextDeveloper\IAAS\VirtualMachines",
 *     "object_type": "NextDeveloper\IAAS\Database\Models\VirtualMachines",
 *     "object":      { ...transformer output... }
 *   }
 *
 * The transformer is resolved automatically from the model class name:
 *   NextDeveloper\IAAS\Database\Models\VirtualMachines
 *   → NextDeveloper\IAAS\Http\Transformers\VirtualMachinesTransformer
 *
 * Falls back to toArray() if no transformer exists for the model.
 *
 * Dispatched automatically by Events::fire() when NATS is enabled.
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

        $nats->publish("client.{$accountUuid}.evt", $payload);

        Log::debug('[NatsPublisherJob] Published', [
            'subject' => "client.{$accountUuid}.evt",
            'event'   => $this->params['event'] ?? null,
            'model'   => class_basename($this->model),
        ]);
    }

    private function buildPayload(string $accountUuid): array
    {
        return [
            'event'       => $this->params['event'] ?? null,
            'object_type' => get_class($this->model),
            'object'      => $this->transformModel(),
        ];
    }

    private function transformModel(): array
    {
        $transformerClass = $this->resolveTransformerClass();

        if ($transformerClass) {
            try {
                $manager  = new Manager();
                $resource = new Item($this->model, new $transformerClass());
                $data     = $manager->createData($resource)->toArray();

                return $data['data'] ?? $this->model->toArray();
            } catch (\Throwable $e) {
                Log::warning('[NatsPublisherJob] Transformer failed, falling back to toArray', [
                    'transformer' => $transformerClass,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $this->model->toArray();
    }

    private function resolveTransformerClass(): ?string
    {
        $modelClass = get_class($this->model);

        // NextDeveloper\IAAS\Database\Models\VirtualMachines
        // → NextDeveloper\IAAS\Http\Transformers\VirtualMachinesTransformer
        $transformerClass = str_replace('Database\\Models\\', 'Http\\Transformers\\', $modelClass) . 'Transformer';

        return class_exists($transformerClass) ? $transformerClass : null;
    }

    private function resolveAccountUuid(): ?string
    {
        $accountId = $this->model->iam_account_id ?? null;

        if ($accountId === null) {
            return null;
        }

        return DB::table('iam_accounts')
            ->where('id', $accountId)
            ->value('uuid');
    }
}
