<?php

namespace NextDeveloper\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Events\Services\NatsService;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Generic NATS command handler. Receives a structured message and maps it to the
 * correct Service or Action class, then publishes a result back via replyTo.
 *
 * Supported message format:
 *
 *   // Update a resource
 *   { "operation": "patch",   "model": "NextDeveloper\\IAAS\\VirtualMachines", "id": "uuid", "object": { ... } }
 *
 *   // Create a resource
 *   { "operation": "create",  "model": "NextDeveloper\\IAAS\\VirtualMachines", "object": { ... } }
 *
 *   // Delete a resource
 *   { "operation": "delete",  "model": "NextDeveloper\\IAAS\\VirtualMachines", "id": "uuid" }
 *
 *   // Trigger an action
 *   { "operation": "action",  "model": "NextDeveloper\\IAAS\\VirtualMachines", "id": "uuid", "action": "Restart", "params": { ... } }
 *
 * Optional fields (all operations):
 *   "reply_to"      : NATS subject to publish the result to (overrides protocol-level replyTo)
 *   "iam_account_id": account UUID to run as (defaults to admin)
 *   "iam_user_id"   : user UUID to run as (defaults to admin)
 *
 * Register for a subject:
 *   Events::listen('subject-name', NatsCommandHandlerJob::class);
 * Or in config:
 *   'subscribers' => [ 'leo4' => NatsCommandHandlerJob::class ]
 */
class NatsCommandHandlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SUPPORTED_OPERATIONS = ['patch', 'create', 'delete', 'action'];

    public function __construct(
        private readonly array  $payload,
        private readonly string $subject,
        private readonly ?string $replyTo = null
    ) {
        $this->queue = config('events.nats.queue', 'default');
    }

    public function handle(NatsService $nats): void
    {
        $operation = $this->payload['operation'] ?? null;
        $model     = $this->payload['model']     ?? null;

        if (!$operation || !$model) {
            $this->sendReply($nats, false, 'Missing required fields: operation, model');
            return;
        }

        if (!in_array($operation, self::SUPPORTED_OPERATIONS, true)) {
            $this->sendReply($nats, false, "Unsupported operation [{$operation}]. Supported: " . implode(', ', self::SUPPORTED_OPERATIONS));
            return;
        }

        $this->setUserContext();

        try {
            $result = match ($operation) {
                'patch'  => $this->handlePatch($model),
                'create' => $this->handleCreate($model),
                'delete' => $this->handleDelete($model),
                'action' => $this->handleAction($model),
            };

            $this->sendReply($nats, true, 'OK', $result);
        } catch (\Throwable $e) {
            Log::error('[NatsCommandHandlerJob] Operation failed', [
                'operation' => $operation,
                'model'     => $model,
                'subject'   => $this->subject,
                'error'     => $e->getMessage(),
            ]);

            $this->sendReply($nats, false, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------

    private function handlePatch(string $modelClass): array
    {
        $id     = $this->requireField('id');
        $object = $this->requireField('object');

        $service = $this->resolveService($modelClass);
        $updated = $service::update($id, $object);

        return $updated ? $updated->toArray() : [];
    }

    private function handleCreate(string $modelClass): array
    {
        $object = $this->requireField('object');

        $service = $this->resolveService($modelClass);
        $created = $service::create($object);

        return $created ? $created->toArray() : [];
    }

    private function handleDelete(string $modelClass): array
    {
        $id = $this->requireField('id');

        $service = $this->resolveService($modelClass);
        $service::delete($id);

        return ['deleted' => true, 'id' => $id];
    }

    private function handleAction(string $modelClass): array
    {
        $id         = $this->requireField('id');
        $actionName = $this->requireField('action');
        $params     = $this->payload['params'] ?? [];

        $actionClass = $this->resolveAction($modelClass, $actionName);

        // Actions take the model instance, not just the ID
        $model = $this->findModel($modelClass, $id);

        $actionClass::dispatch($model, $params);

        return ['action' => $actionName, 'dispatched' => true];
    }

    // -------------------------------------------------------------------------

    /**
     * Resolves "NextDeveloper\IAAS\VirtualMachines" → "NextDeveloper\IAAS\Services\VirtualMachinesService"
     */
    private function resolveService(string $modelClass): string
    {
        $parts       = explode('\\', $modelClass);
        $modelName   = array_pop($parts);
        $namespace   = implode('\\', $parts);
        $serviceClass = $namespace . '\\Services\\' . $modelName . 'Service';

        if (!class_exists($serviceClass)) {
            throw new \RuntimeException("Service class not found: {$serviceClass}");
        }

        return $serviceClass;
    }

    /**
     * Resolves "NextDeveloper\IAAS\VirtualMachines" + "Restart" → "NextDeveloper\IAAS\Actions\VirtualMachines\Restart"
     */
    private function resolveAction(string $modelClass, string $actionName): string
    {
        $parts      = explode('\\', $modelClass);
        $modelName  = array_pop($parts);
        $namespace  = implode('\\', $parts);
        $actionClass = $namespace . '\\Actions\\' . $modelName . '\\' . $actionName;

        if (!class_exists($actionClass)) {
            throw new \RuntimeException("Action class not found: {$actionClass}");
        }

        return $actionClass;
    }

    /**
     * Finds the model instance by UUID, bypassing AuthorizationScope since we run as admin.
     */
    private function findModel(string $modelClass, string $uuid): mixed
    {
        // Resolve the actual Eloquent model class
        $parts      = explode('\\', $modelClass);
        $modelName  = array_pop($parts);
        $namespace  = implode('\\', $parts);
        $eloquentClass = $namespace . '\\Database\\Models\\' . $modelName;

        if (!class_exists($eloquentClass)) {
            throw new \RuntimeException("Model class not found: {$eloquentClass}");
        }

        $model = $eloquentClass::withoutGlobalScopes()->where('uuid', $uuid)->first();

        if (!$model) {
            throw new \RuntimeException("Model not found: {$modelClass} uuid={$uuid}");
        }

        return $model;
    }

    /**
     * Sets the user context for the operation.
     * If iam_account_id/iam_user_id are in the payload, runs as that account/user.
     * Otherwise defaults to admin.
     */
    private function setUserContext(): void
    {
        $accountUuid = $this->payload['iam_account_id'] ?? null;
        $userUuid    = $this->payload['iam_user_id']    ?? null;

        if ($accountUuid && $userUuid) {
            $accountId = DB::table('iam_accounts')->where('uuid', $accountUuid)->value('id');
            $userId    = DB::table('iam_users')->where('uuid', $userUuid)->value('id');

            if ($accountId && $userId) {
                UserHelper::setUserById($userId);
                return;
            }
        }

        UserHelper::setAdminAsCurrentUser();
    }

    private function requireField(string $field): mixed
    {
        if (!array_key_exists($field, $this->payload)) {
            throw new \InvalidArgumentException("Missing required field: {$field}");
        }

        return $this->payload[$field];
    }

    /**
     * Sends a result back to the caller via:
     *   1. Protocol-level replyTo (set by NATS request/reply)
     *   2. reply_to field in the payload (set manually by publisher)
     */
    private function sendReply(NatsService $nats, bool $success, string $message, array $data = []): void
    {
        $replySubject = $this->replyTo ?? $this->payload['reply_to'] ?? null;

        if (!$replySubject) {
            return;
        }

        $nats->reply($replySubject, [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ]);
    }
}
