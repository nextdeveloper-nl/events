<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use NextDeveloper\Commons\Helpers\ObjectHelper;
use NextDeveloper\IAM\Database\Models\Accounts;

/**
 * Builds the payload event pushers deliver.
 *
 * The shape follows CloudEvents 1.0 (specversion / id / source / type / time / subject / data) so receivers can
 * use off-the-shelf tooling. The object is rendered through the model's own Transformer (the same one the NATS
 * publisher uses) and never as a raw row, so internal ids / hidden columns are not leaked and the payload stays
 * stable when columns are added.
 *
 * The `id` is generated once here and stored in the PusherLog body, so retries of the same delivery carry the
 * same id and receivers can de-duplicate (delivery is at-least-once).
 */
class EventEnvelopeBuilder
{
    /**
     * @param string $eventName e.g. "created:NextDeveloper\Support\Tickets"
     */
    public static function build(string $eventName, Model $model): array
    {
        $accountUuid = self::accountUuid($model);

        return [
            'specversion'     => '1.0',
            'id'              => (string) Str::uuid(),
            'source'          => '/plusclouds/' . ($accountUuid ?? 'platform'),
            'type'            => $eventName,
            'time'            => now()->toIso8601String(),
            'datacontenttype' => 'application/json',
            'subject'         => $model->uuid ?? null,
            'data'            => [
                'account_id'  => $accountUuid,
                'object_type' => ObjectHelper::getPublicObjectName($model),
                'object'      => self::transform($model),
            ],
        ];
    }

    /**
     * Uuid of the account that owns the object, null for objects without an account.
     */
    public static function accountUuid(Model $model): ?string
    {
        $accountId = $model->iam_account_id ?? null;

        if ($accountId === null) {
            return null;
        }

        return Accounts::withoutGlobalScopes()->where('id', $accountId)->value('uuid');
    }

    /**
     * NextDeveloper\IAAS\Database\Models\VirtualMachines -> NextDeveloper\IAAS\Http\Transformers\VirtualMachinesTransformer
     */
    private static function transform(Model $model): array
    {
        $transformerClass = str_replace('Database\\Models\\', 'Http\\Transformers\\', get_class($model)) . 'Transformer';

        if (class_exists($transformerClass)) {
            try {
                $data = (new Manager())->createData(new Item($model, new $transformerClass()))->toArray();

                return $data['data'] ?? $model->toArray();
            } catch (\Throwable $e) {
                Log::warning('[EventEnvelopeBuilder] Transformer failed, falling back to toArray', [
                    'transformer' => $transformerClass,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $model->toArray();
    }
}
