<?php

namespace NextDeveloper\Events\Pushers\Support;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use NextDeveloper\Commons\Database\Models\Pushers;
use NextDeveloper\Commons\Pushers\PusherResult;

/**
 * Shared outbound HTTP for the event webhook / chat pushers: SSRF-guarded, connection pinned to the validated
 * IP, redirects off, bounded timeout. Transport errors (timeouts, refused connections) are thrown on purpose:
 * AbstractPusher::execute() marks the log failed and PushObjectJob retries it.
 */
class HttpDelivery
{
    public static function post(Pushers $pusher, string $body, array $headers = []): PusherResult
    {
        try {
            $pinned = SsrfGuard::resolve((string) $pusher->url);
        } catch (InvalidArgumentException $e) {
            return PusherResult::fail(422, $e->getMessage());
        }

        $timeout = (int) data_get($pusher->provider_metadata, 'timeout', 15);

        $response = Http::withOptions(SsrfGuard::httpOptions($pinned))
            ->timeout(max(1, min($timeout, 60)))
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($pusher->url);

        // Keep the stored response small, receivers sometimes answer with whole HTML error pages.
        $responseBody = mb_substr((string) $response->body(), 0, 2000);

        return $response->successful()
            ? PusherResult::ok($response->status(), $responseBody)
            : PusherResult::fail($response->status(), $responseBody);
    }
}
