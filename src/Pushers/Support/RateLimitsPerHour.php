<?php

namespace NextDeveloper\Events\Pushers\Support;

use NextDeveloper\Commons\Database\Models\PusherLogs;
use NextDeveloper\Commons\Database\Models\Pushers;

/**
 * Optional per-pusher hourly cap so a noisy event cannot flood a chat room / mailbox / receiver.
 * Configured with provider_metadata.hourly_limit; no key (or 0) means unlimited.
 * Same idea as the hourly_limit used by FlowStagePusher / FlowAutomationEmailPusher.
 */
trait RateLimitsPerHour
{
    protected function isWithinHourlyLimit(Pushers $pusher): bool
    {
        $limit = (int) data_get($pusher->provider_metadata, 'hourly_limit', 0);

        if ($limit <= 0) {
            return true;
        }

        $recent = PusherLogs::withoutGlobalScopes()
            ->where('common_pusher_id', $pusher->id)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recent < $limit;
    }
}
