<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\Services\PushersService;
use NextDeveloper\Events\Database\Models\Listeners;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * Turns a fired event into a pusher delivery, according to the listener that matched it.
 *
 * Called from EventPusherJob (which is the `callback` of a listener that has a common_pusher_id). Order of
 * checks is cheapest first and every skip is logged at debug so "why did nothing arrive" can be answered:
 * active -> account ownership -> time window -> envelope -> conditions -> pusher configured -> trigger.
 */
class EventPusherService
{
    /**
     * @return bool true when a delivery was queued
     */
    public static function dispatch(int $listenerId, Model $model, array $params): bool
    {
        // Global scopes off: this runs in a queue worker with no authenticated user.
        $listener = Listeners::withoutGlobalScopes()->where('id', $listenerId)->first();

        if (!$listener || !$listener->is_active) {
            return self::skip($listenerId, 'listener missing or inactive');
        }

        if (empty($listener->common_pusher_id)) {
            return self::skip($listenerId, 'listener has no common_pusher_id');
        }

        if (!self::ownsEvent($listener, $model)) {
            return self::skip($listenerId, 'event belongs to another account');
        }

        if (!ListenerRuleEvaluator::withinTimeWindow($listener)) {
            return self::skip($listenerId, 'outside time_window');
        }

        $eventName = $params['event'] ?? '';
        $envelope  = EventEnvelopeBuilder::build($eventName, $model);

        if (!ListenerRuleEvaluator::conditionsMatch($listener, $envelope)) {
            return self::skip($listenerId, 'conditions did not match');
        }

        // Routing is internal (integer ids) and is only for the email / in-app pushers. The webhook and chat
        // pushers strip it before sending, so it never leaves the platform.
        $envelope['routing'] = [
            'listener_id'               => $listener->uuid,
            'listener_name'             => $listener->name,
            'communication_channel_ids' => $listener->communication_channel_ids ?? [],
            'recipient_iam_account_ids' => $listener->recipient_iam_account_ids ?? [],
        ];

        // PushersService::trigger creates a PusherLog, which needs a current user/account; queue workers have
        // none, so run as the platform admin like the other pusher callers do.
        UserHelper::runAsAdmin(fn () => PushersService::trigger((int) $listener->common_pusher_id, $envelope));

        return true;
    }

    /**
     * A listener that belongs to an account only ever sees that account's events. The platform owner account
     * is the exception: it is the "global" listener that sees every account. Objects without an account are
     * only visible to platform-owner listeners.
     */
    private static function ownsEvent(Listeners $listener, Model $model): bool
    {
        if ($listener->iam_account_id === null) {
            return true;
        }

        $owner = UserHelper::getLeoOwnerAccount();

        if ($owner && (int) $listener->iam_account_id === (int) $owner->id) {
            return true;
        }

        return ($model->iam_account_id ?? null) !== null
            && (int) $model->iam_account_id === (int) $listener->iam_account_id;
    }

    private static function skip(int $listenerId, string $reason): bool
    {
        Log::debug('[EventPusherService] Skipped', ['listener_id' => $listenerId, 'reason' => $reason]);

        return false;
    }
}
