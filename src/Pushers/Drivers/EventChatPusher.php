<?php

namespace NextDeveloper\Events\Pushers\Drivers;

use NextDeveloper\Commons\Database\Models\PusherLogs;
use NextDeveloper\Commons\Database\Models\Pushers;
use NextDeveloper\Commons\Pushers\AbstractPusher;
use NextDeveloper\Commons\Pushers\PusherResult;
use NextDeveloper\Events\Pushers\Support\HttpDelivery;
use NextDeveloper\Events\Pushers\Support\RateLimitsPerHour;

/**
 * Posts a short human readable message about an event to a chat incoming-webhook.
 *
 * Provider key: event_chat
 *
 * Pushers columns:
 *   url    the chat incoming-webhook URL (it is itself the secret, so `token` is not used)
 *
 * provider_metadata keys:
 *   flavor            slack | mattermost | teams | discord (default slack)
 *   message_template  optional text, {{path}} placeholders are read from the envelope, e.g.
 *                     "New ticket {{data.object.subject}} ({{data.object.status}})".
 *                     Default: event type, object type and subject.
 *   timeout, hourly_limit   as in EventWebhookPusher
 */
class EventChatPusher extends AbstractPusher
{
    use RateLimitsPerHour;

    private const DISCORD_LIMIT = 2000;

    public static function provider(): string
    {
        return 'event_chat';
    }

    public function send(PusherLogs $log, Pushers $pusher): PusherResult
    {
        if (!$this->isWithinHourlyLimit($pusher)) {
            return PusherResult::fail(429, 'Hourly delivery limit reached.');
        }

        $flavor = strtolower((string) data_get($pusher->provider_metadata, 'flavor', 'slack'));
        $text   = $this->message($this->decodeBody($log), $pusher);

        // Slack, Mattermost and Teams incoming webhooks take {"text"}, Discord takes {"content"} (2000 chars).
        $payload = match ($flavor) {
            'discord'                      => ['content' => mb_substr($text, 0, self::DISCORD_LIMIT)],
            'slack', 'mattermost', 'teams' => ['text' => $text],
            default                        => null,
        };

        if ($payload === null) {
            return PusherResult::fail(422, 'Unknown chat flavor: ' . $flavor);
        }

        return HttpDelivery::post($pusher, json_encode($payload, JSON_UNESCAPED_UNICODE), [
            'User-Agent' => 'PlusClouds-Events/1.0',
        ]);
    }

    private function message(array $envelope, Pushers $pusher): string
    {
        $template = data_get($pusher->provider_metadata, 'message_template');

        if ($template) {
            return preg_replace_callback(
                '/\{\{\s*([\w\.\-]+)\s*\}\}/',
                fn (array $m) => (string) data_get($envelope, $m[1], ''),
                $template
            );
        }

        return sprintf(
            "%s\nObject: %s%s",
            $envelope['type'] ?? 'event',
            data_get($envelope, 'data.object_type', 'unknown'),
            !empty($envelope['subject']) ? ' (' . $envelope['subject'] . ')' : ''
        );
    }
}
