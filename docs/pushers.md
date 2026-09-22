# Event pushers

A listener can hand a fired event to a **pusher** (Commons `common_pushers` row) instead of a hard-coded PHP job.
Admins create the pusher and the listener through the API; no code is needed.

```
Events::fire() -> event_listeners row (callback = EventPusherJob, common_pusher_id)
   -> EventPusherService: active? own account? time_window? conditions?
   -> PushersService::trigger()  -> PusherLog + PushObjectJob (queue `pushers`)
   -> driver of the pusher's `provider`
```

## Providers

| provider | what it does | pusher columns | `provider_metadata` |
|---|---|---|---|
| `event_webhook` | signed HTTP POST of the event | `url` (https), `token` = signing secret (required) | `previous_secret`, `timeout`, `hourly_limit` |
| `event_chat` | short message to a chat incoming-webhook | `url` | `flavor` (`slack`, `mattermost`, `teams`, `discord`), `message_template`, `timeout`, `hourly_limit` |
| `event_message` (main project) | email or SMS to members of the recipient accounts | - | `mode` (`email`/`sms`), `communication_channel_id`, `subject_template`, `body_template`, `hourly_limit` |
| `event_inapp` (main project) | persistent notification in the panel inbox | - | `severity`, `title_template`, `message_template` |
| `event_crm_opportunity` (main project) | creates a CRM Opportunity in a Campaign and drops it into that campaign's Flow pipeline at a stage | - | `crm_campaign_id` (required), `flow_stage_id`, `type`, `name_template`, `description_template`, `hourly_limit` |

`{{path}}` placeholders in templates read the envelope with dot notation, e.g. `{{data.object.status}}`.

## Listener fields

`event`, `common_pusher_id` (pusher uuid, must be an `event_*` provider; the callback is then forced to
`EventPusherJob`), `is_active`, `priority` (lower runs first), `conditions`, `time_window`,
`communication_channel_ids` and `recipient_iam_account_ids` (for `event_message` / `event_inapp`).
Listeners can be created and deleted, never updated: delete and add a new one.

A listener that belongs to an account only receives that account's events. A listener owned by the platform
owner account receives every account's events.

`conditions` - list of clauses, all must match:
`[{"field": "data.object.status", "operator": "eq", "value": "open"}]`.
Operators: `eq neq in not_in contains gt gte lt lte exists`. Unknown operators never match.

`time_window` - events outside it are dropped, not deferred:
`{"timezone": "Europe/Istanbul", "days": [1,2,3,4,5], "start": "09:00", "end": "18:00"}` (ISO days, 1 = Monday).

## Payload (CloudEvents 1.0 shape)

```json
{
  "specversion": "1.0",
  "id": "6f0c...",
  "source": "/plusclouds/<account uuid>",
  "type": "created:NextDeveloper\\Support\\Tickets",
  "time": "2026-09-21T10:00:00+00:00",
  "datacontenttype": "application/json",
  "subject": "<object uuid>",
  "data": {"account_id": "<account uuid>", "object_type": "...", "object": { }}
}
```

`object` is the model's own Transformer output. `id` is stable across retries: delivery is at-least-once, so
receivers must de-duplicate on it.

## Verifying an `event_webhook` (Standard Webhooks)

Headers: `webhook-id`, `webhook-timestamp` (unix seconds), `webhook-signature` (`v1,<base64>`, several
space separated while a secret is being rotated).

```php
$signed = $id . '.' . $timestamp . '.' . $rawBody;              // raw bytes, not re-encoded JSON
$key    = str_starts_with($secret, 'whsec_') ? base64_decode(substr($secret, 6)) : $secret;
$expect = 'v1,' . base64_encode(hash_hmac('sha256', $signed, $key, true));
// accept if hash_equals($expect, <any signature in the header>) and abs(time() - $timestamp) < 300
```

## `event_crm_opportunity` details

Needs the event's account to have a CRM account (`crm_accounts.iam_account_id`) and the target `crm_campaign_id`
to already have a Flow pipeline provisioned (created with `campaign_type` / `flow_template_id`, see
`CampaignsService::provisionFlow`). Idempotent per step, not all-or-nothing: the Opportunity is found again by
a tag holding the event id before a new one is created, and the Flow item is found by object_type/object_id
before a new one is created — a retry after a partial failure resumes instead of duplicating.

## Safety

- Every send resolves DNS again and refuses private / loopback / link-local addresses, then pins the
  connection to the checked IP, redirects are not followed, https only (`SsrfGuard`).
  Dev-only switches: `events.pushers.allow_insecure_http`, `events.pushers.allow_private_hosts`.
- `hourly_limit` caps completed deliveries per hour per pusher.
- Failed deliveries are retried by `nextdeveloper:retry-pending-pushers` for pushers with `is_retryable`.
  That command retries every failed log up to `commons.pusher.max_retries`, it does not tell a permanent 4xx from
  a temporary 5xx.
- Not done yet: de-duplicating identical events inside a window, digests when the hourly limit is hit.

## Database

`event_listeners.common_pusher_id` (nullable, FK `common_pushers.id`) must exist before this is deployed.
