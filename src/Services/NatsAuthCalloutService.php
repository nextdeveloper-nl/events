<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles NATS auth callout requests.
 *
 * Called for every incoming NATS connection. Validates the token against:
 *   - iaas_compute_members.agent_api_key  → compute agent
 *   - iaas_storage_members.agent_api_key  → storage agent
 *   - iaas_network_members.agent_api_key  → network agent
 *   - oauth_access_tokens                → browser / API client
 *
 * Returns a signed authorization_response JWT with scoped subject permissions.
 *
 * Outer JWT structure (per nats-io/jwt/v2 spec and Java reference impl):
 *   iss = account NKey public
 *   sub = user NKey from request (nats.user_nkey)
 *   aud = server NKey from request (nats.server_id.id)
 *   nats.type    = "authorization_response"
 *   nats.version = 2
 *   nats.jwt     = inner signed user JWT  (on success)
 *   nats.error   = reason string          (on failure)
 *
 * Inner user JWT:
 *   iss = account NKey public
 *   sub = same user NKey
 *   nats.type    = "user"
 *   nats.version = 2
 *   nats.pub.allow / nats.sub.allow = scoped permissions
 */
class NatsAuthCalloutService
{
    private const AGENT_TABLES = [
        'iaas_virtual_machines' => 'vm',
        'iaas_compute_members' => 'compute',
        'iaas_storage_members' => 'storage',
        'iaas_network_members' => 'network',
    ];

    /**
     * Process an auth callout request JWT and return a signed response JWT.
     *
     * @param string $requestJwt  The raw JWT sent by the NATS server
     * @return string             Signed authorization_response JWT
     */
    public function handle(string $requestJwt): string
    {
        if (empty($requestJwt)) {
            return $this->deny('unknown', 'unknown', 'Empty auth request');
        }

        [$serverNKey, $userNKey, $token, $username] = $this->parseRequest($requestJwt);

        Log::debug('[NatsAuthCallout] Parsed request', [
            'server'       => $serverNKey,
            'user_nkey'    => $userNKey,
            'token_prefix' => $token ? substr($token, 0, 8) . '...' : null,
            'username'     => $username,
        ]);

        // Identity is determined solely by which table the credential appears in — the client
        // does not declare its type. Agent API keys are checked first across all infra tables;
        // OAuth tokens are the fallback. The first successful lookup wins and sets the identity.
        foreach (self::AGENT_TABLES as $table => $type) {
            if (!$this->tableHasColumn($table, 'agent_api_key')) {
                continue;
            }

            $agent = DB::table($table)
                ->whereNotNull('agent_api_key')
                ->whereNull('deleted_at')
                ->where('agent_api_key', $token ?? $username)
                ->select(['uuid'])
                ->first();

            if ($agent) {
                Log::info('[NatsAuthCallout] Authenticated agent', ['type' => $type, 'uuid' => $agent->uuid]);

                return $this->allow($serverNKey, $userNKey, $agent->uuid, [
                    'sub' => [
                        "agent.{$type}.{$agent->uuid}.cmd",
                        "agent.{$type}.broadcast",
                        "agent.broadcast",
                    ],
                    'pub' => [
                        "agent.{$type}.{$agent->uuid}.evt",
                    ],
                ]);
            }
        }

        // Check OAuth tokens (browser / API clients).
        // Clients may pass the token as auth_token/pass OR as the username field.
        // Agent API keys are long opaque strings — not UUIDs. OAuth token IDs are UUIDs
        // (Laravel Passport). Skipping the DB query for non-UUID credentials avoids a
        // Postgres "invalid input syntax for type uuid" error and short-circuits the lookup.
        $oauthCandidate = $token ?? $username;
        if ($oauthCandidate && \Illuminate\Support\Str::isUuid($oauthCandidate)) {
            $result = $this->resolveOAuthToken($oauthCandidate);
            if ($result) {
                ['account_uuids' => $accountUuids, 'user_uuid' => $userUuid] = $result;

                Log::info('[NatsAuthCallout] Authenticated client', [
                    'accounts' => $accountUuids,
                    'user'     => $userUuid,
                ]);

                // Allow the client to subscribe to every account they belong to.
                // This means an account switch (toggling iam_account_users.is_active)
                // does not require a NATS reconnect.
                $subs = [];
                foreach ($accountUuids as $accountUuid) {
                    $subs[] = "client.{$accountUuid}.evt";
                    $subs[] = "client.{$accountUuid}.{$userUuid}.evt";
                }

                return $this->allow($serverNKey, $userNKey, $accountUuids[0], [
                    'sub' => $subs,
                    'pub' => [],
                ]);
            }
        }

        Log::warning('[NatsAuthCallout] Rejected — unknown credentials', ['user' => $username]);

        return $this->deny($serverNKey, $userNKey, 'Invalid credentials');
    }

    // -------------------------------------------------------------------------

    /**
     * Parse the authorization request JWT sent by NATS.
     *
     * Returns: [serverNKey, userNKey, token, username]
     *   serverNKey = nats.server_id.id  → used as `aud` in the response
     *   userNKey   = nats.user_nkey     → used as `sub` in the response
     */
    private function parseRequest(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return ['unknown', 'unknown', null, null];
        }

        $payload = json_decode(
            base64_decode(strtr($parts[1], '-_', '+/')),
            true
        );

        // Server NKey (starts with N) — must be used as `aud` in response
        $serverNKey = $payload['nats']['server_id']['id'] ?? ($payload['iss'] ?? 'unknown');

        // User NKey — must be used as `sub` in both outer and inner response JWTs
        $userNKey = $payload['nats']['user_nkey'] ?? 'unknown';

        $userInfo    = $payload['nats']['user_info']    ?? [];
        $connectOpts = $payload['nats']['connect_opts'] ?? [];

        $token = $connectOpts['auth_token']
              ?? $connectOpts['pass']
              ?? $userInfo['pass']
              ?? null;

        $username = $connectOpts['user']
                 ?? $userInfo['user']
                 ?? null;

        return [$serverNKey, $userNKey, $token, $username];
    }

    /**
     * Resolve an OAuth access token against the oauth_access_tokens table.
     *
     * Returns all account UUIDs the user belongs to so that account switching
     * (toggling is_active in iam_account_users) does not require a reconnect —
     * the client is already permitted on every account's subject.
     */
    private function resolveOAuthToken(string $token): ?array
    {
        $row = DB::table('oauth_access_tokens')
            ->where('id', $token)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            return null;
        }

        $userUuid = DB::table('iam_users')
            ->where('id', $row->user_id)
            ->value('uuid') ?? 'unknown';

        // Collect all accounts this user is a member of, not just the active one.
        // This allows the client to subscribe to any account's subject after switching
        // without needing to reconnect.
        $accountUuids = DB::table('iam_account_user')
            ->join('iam_accounts', 'iam_accounts.id', '=', 'iam_account_user.iam_account_id')
            ->where('iam_account_user.iam_user_id', $row->user_id)
            ->pluck('iam_accounts.uuid')
            ->filter()
            ->values()
            ->all();

        if (empty($accountUuids)) {
            return null;
        }

        return ['account_uuids' => $accountUuids, 'user_uuid' => $userUuid];
    }

    private function allow(string $serverNKey, string $userNKey, string $identifier, array $permissions): string
    {
        // Inner user JWT — carries the actual permissions.
        // `aud` = target account NAME from nats.conf — server resolves account by name, not NKey.
        $userJwt = NkeyHelper::signJwt([
            'jti'  => Str::uuid()->toString(),
            'iat'  => time(),
            'iss'  => config('events.nats.account_nkey_public'),
            'sub'  => $userNKey,
            'aud'  => 'AUTH',
            'name' => $identifier,
            'nats' => [
                'type'    => 'user',
                'version' => 2,
                'pub'     => ['allow' => $permissions['pub']],
                'sub'     => ['allow' => $permissions['sub']],
            ],
        ], config('events.nats.account_nkey_seed'));

        return $this->buildResponseJwt($serverNKey, $userNKey, ['jwt' => $userJwt]);
    }

    private function deny(string $serverNKey, string $userNKey, string $reason): string
    {
        return $this->buildResponseJwt($serverNKey, $userNKey, ['error' => $reason]);
    }

    /**
     * Build the outer authorization_response JWT.
     *
     * aud = serverNKey (server's NKey ID from the request)
     * sub = userNKey   (user's NKey from the request)
     */
    private function buildResponseJwt(string $serverNKey, string $userNKey, array $nats): string
    {
        $payload = [
            'jti'  => Str::uuid()->toString(),
            'iat'  => time(),
            'iss'  => config('events.nats.account_nkey_public'),
            'sub'  => $userNKey,   // user NKey from request
            'aud'  => $serverNKey, // server NKey from request (nats.server_id.id)
            'nats' => array_merge([
                'type'    => 'authorization_response',
                'version' => 2,
            ], $nats),
        ];

        $jwt = NkeyHelper::signJwt($payload, config('events.nats.account_nkey_seed'));

        $parts = explode('.', $jwt);
        $decoded = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
        Log::debug('[NatsAuthCallout] Response JWT payload', $decoded ?? []);

        return $jwt;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            return in_array($column, DB::getSchemaBuilder()->getColumnListing($table), true);
        } catch (\Throwable) {
            return false;
        }
    }
}
