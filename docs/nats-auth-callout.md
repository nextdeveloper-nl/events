# NATS Auth Callout Service

Standardization reference for the auth callout implementation in this package.

## Components

| File | Purpose |
|---|---|
| `src/Services/NatsAuthCalloutService.php` | Validates credentials, builds and signs JWTs |
| `src/Services/NkeyHelper.php` | Ed25519 NKey encoding, JWT signing, base32 utilities |
| `src/Console/Commands/NatsAuthListenerCommand.php` | Long-running NATS subscriber — bridges NATS and the service |

## Service Contract

`NatsAuthCalloutService::handle(string $requestJwt): string`

- Takes the raw JWT string from the NATS `$SYS.REQ.USER.AUTH` subject
- Returns a signed `authorization_response` JWT string
- Never throws — on any error it returns a deny JWT

`NkeyHelper::signJwt(array $payload, string $accountNkeySeed): string`

- Signs a payload as an `ed25519-nkey` JWT
- `$accountNkeySeed` must be a valid Account NKey seed (starts with `SA`)

## JWT Signing — Two Layers

Both layers are signed with the same account NKey seed (`NATS_ACCOUNT_NKEY_SEED`).

```
authorization_response (outer) ─── signed by account NKey
  iss = NATS_ACCOUNT_NKEY_PUBLIC
  sub = nats.user_nkey from request      ← ephemeral key, must match exactly
  aud = nats.server_id.id from request   ← server NKey (starts with N)
  nats.type    = "authorization_response"
  nats.version = 2
  nats.jwt     = <inner user JWT>        ← on allow
  nats.error   = <reason>               ← on deny (must be non-empty string)

user (inner) ─── also signed by account NKey, embedded in nats.jwt
  iss = NATS_ACCOUNT_NKEY_PUBLIC         ← must match auth_callout.issuer in nats.conf
  sub = nats.user_nkey from request      ← same as outer sub
  aud = "AUTH"                           ← target account NAME (not NKey)
  name = <identifier>                    ← agent UUID or account UUID
  nats.type    = "user"
  nats.version = 2
  nats.pub.allow = [...]
  nats.sub.allow = [...]
```

## Adding a New Identity Type

1. Add the token source (DB table column or a new table) to `NatsAuthCalloutService::AGENT_TABLES` or add a new lookup method
2. Define the subject permissions in the `allow()` call
3. Update the permission matrix in [docs/nats/auth-callout.md](../../plusclouds.api.v4/docs/nats/auth-callout.md) in the main project

Example — adding a `flow` agent type:

```php
// In AGENT_TABLES constant:
'flow_agents' => 'flow',

// Permissions follow the same pattern:
'sub' => ["agent.flow.{$agent->uuid}.cmd", "agent.flow.broadcast", "agent.broadcast"],
'pub' => ["agent.flow.{$agent->uuid}.evt"],
```

## Listener — Critical Rule

The `basis-company/nats` library auto-replies with the **return value** of the subscription callback. The callback must `return` the JWT string. Never call `$client->publish($replyTo, ...)` manually — it causes a double-reply where the library sends a second empty payload that corrupts the auth response.

```php
// Correct pattern
$client->subscribe('$SYS.REQ.USER.AUTH', function ($payload, $replyTo) use ($authService) {
    if (!$replyTo) return null;
    try {
        return $authService->handle($payload->body);
    } catch (\Throwable $e) {
        Log::error('...', ['error' => $e->getMessage()]);
        return $authService->handle('');
    }
});
```

## NkeyHelper Internals

| Method | Purpose |
|---|---|
| `signJwt(array $payload, string $seed)` | Build and sign a NATS JWT using Ed25519 |
| `decodeSeed(string $nkeySeed)` | Decode NKey seed string → raw 32-byte Ed25519 seed |
| `generateUserPublicKey()` | Generate an ephemeral user NKey public key (U…) — seed discarded |
| `b64url(string $data)` | Base64url encode without padding |

NKey seed structure after base32 decode: `[2 bytes prefix][32 bytes Ed25519 seed][2 bytes CRC16]`

## Configuration Keys

All keys are under `config('events.nats.*')`, loaded from `.env`:

| Key | env var | Description |
|---|---|---|
| `account_nkey_public` | `NATS_ACCOUNT_NKEY_PUBLIC` | Account NKey public key — used as `iss` in all JWTs, must match `auth_callout.issuer` in nats.conf |
| `account_nkey_seed` | `NATS_ACCOUNT_NKEY_SEED` | Account NKey seed — used to sign all JWTs, never exposed |
| `auth_service_password` | `NATS_AUTH_SERVICE_PASSWORD` | Password for the `auth-service` NATS user (bypasses the callout) |
| `server_host` | `NATS_HOST` | NATS server hostname |
| `server_port` | `NATS_PORT` | NATS server port (default 4222) |
| `tls_ca` | `NATS_TLS_CA` | Path to CA certificate for TLS verification |
| `enabled` | `NATS_ENABLED` | Must be `true` for the listener to start |

## Supervisor Config

```ini
[program:nats-auth]
command=php /var/www/artisan events:nats-auth-listen
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/nats-auth.err.log
stdout_logfile=/var/log/supervisor/nats-auth.out.log
```

## Error Reference

See the full error log reference in the main project docs:
[docs/nats/auth-callout.md → Error Log Reference](../../plusclouds.api.v4/docs/nats/auth-callout.md#error-log-reference)

Quick summary of the progression encountered during implementation:

| Error | Root cause |
|---|---|
| `Error or Jwt is required` | `nats.error: ""` empty string — must be non-empty or absent |
| `auth callout response is not for expected user` | outer `sub` was a newly generated NKey, not the one from the request |
| `Audience must be a server public key` | outer `aud` was set to `payload['iss']` (account NKey A…) instead of `payload['nats']['server_id']['id']` (server NKey N…) |
| `No valid account "" for auth callout response` | inner JWT had no `aud`, so NATS couldn't resolve the target account |
| `authentication error - User "..."` (single line) | double-reply: manual `publish()` + library auto-reply sent empty payload second |
