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
use Illuminate\Support\Str;
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
 * Payload (standard protocol envelope — see docs/agents/protocol.md):
 *   {
 *     "v":          1,
 *     "id":         "<uuid>",
 *     "type":       "event",
 *     "agent_type": "platform",
 *     "agent_uuid": "<account_uuid>",
 *     "timestamp":  1748000000,
 *     "payload": {
 *       "event":       "created:NextDeveloper\IAAS\VirtualMachines",
 *       "object_type": "NextDeveloper\IAAS\Database\Models\VirtualMachines",
 *       "object":      { ...transformer output... }
 *     }
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

    //  Identity of the model at dispatch time. SerializesModels only keeps the primary key
    //  and reloads the row in the worker, so we remember the uuid separately to verify that
    //  the worker publishes the same object that fired the event.
    //  Defaults to null so jobs serialized before this property existed (still queued during
    //  a deploy) unserialize with the check disabled instead of an uninitialized-property error.
    private ?string $dispatchedUuid = null;

    //  Short description of the code that fired the event (class@function:line chain). Jobs run
    //  in a worker, far from the code that caused them, so this is the only way to tell later
    //  who produced a stray event. Null for jobs queued before this property existed.
    private ?string $origin = null;

    //  When the job was dispatched (unix time). Lets the Published log show how long the job
    //  waited in the queue; null for jobs queued before this property existed.
    private ?int $dispatchedAt = null;

    //  Fixed queue name for NATS publishing. It is deliberately not configurable: a NATS_QUEUE
    //  override in .env once could (and in production nearly did) put these jobs back on
    //  'default', behind tens of millions of unrelated jobs. The worker for it is
    //  [program:nats] in the main project's .docker/supervisord.conf.
    public const QUEUE = 'nats';

    //  Plain model lifecycle verbs. Everything else is an action event (starting, started,
    //  halted, ...) which gets extra logging because it is rare and the UI depends on it.
    private const LIFECYCLE_EVENTS = ['saving', 'saved', 'creating', 'created', 'updating', 'updated'];

    public static function isLifecycleEvent(string $eventName): bool
    {
        return in_array(strtok($eventName, ':'), self::LIFECYCLE_EVENTS, true);
    }

    public function __construct(
        private readonly Model $model,
        private readonly array $params
    ) {
        $this->queue = self::QUEUE;
        $this->dispatchedUuid = $model->uuid ?? null;
        $this->origin = self::describeCaller();
        $this->dispatchedAt = time();
    }

    //  Walks the stack at dispatch time and keeps the first frames outside the event plumbing.
    private static function describeCaller(): string
    {
        $frames = [];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 14) as $frame) {
            $class = $frame['class'] ?? '';

            if ($class === self::class || $class === \NextDeveloper\Events\Services\Events::class) {
                continue;
            }

            $frames[] = $class . '@' . ($frame['function'] ?? '') . ':' . ($frame['line'] ?? '');

            if (count($frames) >= 6) {
                break;
            }
        }

        return implode(' < ', $frames);
    }

    public function handle(NatsService $nats): void
    {
        $eventName = (string) ($this->params['event'] ?? '');
        $isAction  = !self::isLifecycleEvent($eventName);

        if ($isAction) {
            Log::info('[NatsPublisherJob] Handling action event', [
                'event' => $eventName,
                'lag_seconds' => $this->dispatchedAt !== null ? time() - $this->dispatchedAt : null,
                'model' => get_class($this->model),
                'uuid'  => $this->model->uuid ?? null,
            ]);
        }

        //  A row that is soft-deleted when the job runs must not be pushed to browsers as a live
        //  update (deleting/deleted/restore events still go out). Log who fired it.
        if (method_exists($this->model, 'trashed') && $this->model->trashed()
            && !preg_match('/^(deleting|deleted|restoring|restored|force-?deleted):/', $eventName)) {
            Log::warning('[NatsPublisherJob] Skipping event for soft-deleted model', [
                'event'  => $eventName,
                'model'  => get_class($this->model),
                'uuid'   => $this->model->uuid ?? null,
                'origin' => $this->origin,
            ]);
            return;
        }

        $accountUuid = $this->resolveAccountUuid();

        if ($accountUuid === null) {
            $context = [
                'model' => get_class($this->model),
                'event' => $this->params['event'] ?? null,
            ];

            //  Action events are rare and the UI depends on them, so a skip is worth a warning.
            if ($isAction) {
                Log::warning('[NatsPublisherJob] Skipping model without iam_account_id', $context);
            } else {
                Log::debug('[NatsPublisherJob] Skipping model without iam_account_id', $context);
            }

            return;
        }

        //  Never publish an object other than the one that fired the event.
        if ($this->dispatchedUuid !== null && ($this->model->uuid ?? null) !== $this->dispatchedUuid) {
            Log::error('[NatsPublisherJob] Restored model differs from the dispatched one, not publishing', [
                'event'           => $this->params['event'] ?? null,
                'model'           => get_class($this->model),
                'dispatched_uuid' => $this->dispatchedUuid,
                'restored_uuid'   => $this->model->uuid ?? null,
                'restored_id'     => $this->model->getKey(),
            ]);
            return;
        }

        $payload = $this->buildPayload($accountUuid);

        $nats->publish("client.{$accountUuid}.evt", $payload);

        Log::debug('[NatsPublisherJob] Published', [
            'subject' => "client.{$accountUuid}.evt",
            'event'   => $this->params['event'] ?? null,
            'model'   => class_basename($this->model),
            'lag_seconds' => $this->dispatchedAt !== null ? time() - $this->dispatchedAt : null,
            //  What was actually pushed: the identity and state of the published object, so a
            //  message the UI shows can be traced back to the exact row it came from.
            'object_id'         => $payload['payload']['object']['id'] ?? null,
            'object_status'     => $payload['payload']['object']['status'] ?? null,
            'object_updated_at' => $payload['payload']['object']['updated_at'] ?? null,
            'object_deleted_at' => $payload['payload']['object']['deleted_at'] ?? null,
            'uuid'    => $this->model->uuid ?? null,
            'deleted' => method_exists($this->model, 'trashed') ? $this->model->trashed() : null,
        ]);
    }

    private function buildPayload(string $accountUuid): array
    {
        return [
            'v'          => 1,
            'id'         => (string) Str::uuid(),
            'type'       => 'event',
            'agent_type' => 'platform',
            'agent_uuid' => $accountUuid,
            'timestamp'  => time(),
            'payload'    => [
                'event'       => $this->params['event'] ?? null,
                'object_type' => \NextDeveloper\Commons\Helpers\ObjectHelper::getPublicObjectName($this->model),
                'object'      => $this->transformModel(),
            ],
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
