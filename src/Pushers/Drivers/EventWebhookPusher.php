<?php

namespace NextDeveloper\Events\Pushers\Drivers;

use NextDeveloper\Commons\Database\Models\PusherLogs;
use NextDeveloper\Commons\Database\Models\Pushers;
use NextDeveloper\Commons\Pushers\AbstractPusher;
use NextDeveloper\Commons\Pushers\PusherResult;
use NextDeveloper\Events\Pushers\Support\HttpDelivery;
use NextDeveloper\Events\Pushers\Support\RateLimitsPerHour;

/**
 * Delivers an event to a customer URL as a signed webhook.
 *
 * Provider key: event_webhook
 *
 * Body: the CloudEvents-shaped envelope built by EventEnvelopeBuilder (internal `routing` block removed).
 * Signing follows Standard Webhooks (https://github.com/standard-webhooks/standard-webhooks):
 *   webhook-id         the envelope id, identical on every retry so receivers can de-duplicate
 *   webhook-timestamp  unix seconds of this attempt, receivers should reject old ones (replay protection)
 *   webhook-signature  "v1,<base64 HMAC-SHA256 over "{id}.{timestamp}.{body}">", space separated when more
 *                      than one secret is valid (rotation)
 *
 * Pushers columns:
 *   url    receiver URL, https only, must resolve to a public address (see SsrfGuard)
 *   token  signing secret. "whsec_<base64>" is base64-decoded first, any other value is used as raw bytes.
 *          Required: an unsigned webhook is refused.
 *
 * provider_metadata keys (all optional):
 *   previous_secret  old secret, also signed with while it is set so receivers can rotate without downtime
 *   timeout          seconds, default 15, max 60
 *   hourly_limit     max completed deliveries per hour, over that the delivery fails with 429
 */
class EventWebhookPusher extends AbstractPusher
{
    use RateLimitsPerHour;

    public static function provider(): string
    {
        return 'event_webhook';
    }

    public function send(PusherLogs $log, Pushers $pusher): PusherResult
    {
        if (empty($pusher->token)) {
            return PusherResult::fail(422, 'event_webhook pusher needs a token (signing secret).');
        }

        if (!$this->isWithinHourlyLimit($pusher)) {
            return PusherResult::fail(429, 'Hourly delivery limit reached.');
        }

        $envelope = $this->decodeBody($log);

        // routing holds internal ids for the email / in-app pushers, it must not leave the platform.
        unset($envelope['routing']);

        $body      = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $id        = (string) ($envelope['id'] ?? $log->uuid);
        $timestamp = (string) time();

        return HttpDelivery::post($pusher, $body, [
            'webhook-id'        => $id,
            'webhook-timestamp' => $timestamp,
            'webhook-signature' => $this->signatures($pusher, $id, $timestamp, $body),
            'User-Agent'        => 'PlusClouds-Events/1.0',
        ]);
    }

    private function signatures(Pushers $pusher, string $id, string $timestamp, string $body): string
    {
        $secrets = array_filter([
            $pusher->token,
            data_get($pusher->provider_metadata, 'previous_secret'),
        ]);

        $signed = "{$id}.{$timestamp}.{$body}";

        return implode(' ', array_map(
            fn (string $secret) => 'v1,' . base64_encode(hash_hmac('sha256', $signed, $this->key($secret), true)),
            $secrets
        ));
    }

    private function key(string $secret): string
    {
        if (str_starts_with($secret, 'whsec_')) {
            return base64_decode(substr($secret, 6), true) ?: $secret;
        }

        return $secret;
    }
}
