# Events

This module enables the application to work between external applications and services. It provides two basic features: **listen** and **fire**. Events create eventable and listener objects in the database; when an event fires it starts a background process to trigger the related external application (IFTT-style integrations, NATS messaging, webhooks, etc.).

We needed this module to create generic third-party integrations without touching customer or end-user code directly.

The general inspiration comes from Apache Camel. (Thank you guys!)

## Mechanics

This module receives an event, checks for related listeners, and triggers them via their own delivery mechanism. Supported mechanisms: Laravel Jobs (actions), NATS pub/sub, HTTP, WebSocket, gRPC.

## Idea

The idea was to let customers manage their own events using an IFTT-style logic. Events are managed in two core tables:

- `events_available` — the list of events that third parties can bind to
- `events_listeners` — the list of third-party applications listening to those events

## NATS Support

This package includes first-class support for [NATS](https://nats.io) — a high-performance messaging system used to orchestrate agents and deliver real-time events to browser clients.

### What it does

- **Agent orchestration** — send commands to compute, storage and network agents over NATS JetStream
- **Real-time browser events** — push platform events to connected browser clients via NATS WebSocket
- **Auth callout** — validate every NATS connection against the platform database using NKey-signed JWTs (no static passwords per agent)

### Components

| Component | File | Purpose |
| --- | --- | --- |
| `NatsService` | `src/Services/NatsService.php` | Publish and subscribe wrapper |
| `NatsAuthCalloutService` | `src/Services/NatsAuthCalloutService.php` | Validates credentials, signs auth-response JWTs |
| `NkeyHelper` | `src/Services/NkeyHelper.php` | Ed25519 NKey encoding and JWT signing |
| `NatsAuthConfigService` | `src/Services/NatsAuthConfigService.php` | Manages auth config |
| `NatsListenCommand` | `src/Console/Commands/NatsListenCommand.php` | Long-running NATS subscriber worker |
| `NatsAuthListenerCommand` | `src/Console/Commands/NatsAuthListenerCommand.php` | Auth callout listener — validates every NATS connection |
| `NatsKeygenCommand` | `src/Console/Commands/NatsKeygenCommand.php` | Generate NKey pairs for server config |
| `NatsGenerateAuthCommand` | `src/Console/Commands/NatsGenerateAuthCommand.php` | Generate agent auth tokens |

### Artisan Commands

```bash
# Start the NATS event subscriber (queue-worker equivalent for NATS)
php artisan events:nats-listen

# Start the auth callout service (validates every incoming NATS connection)
php artisan events:nats-auth-listen

# Generate a new NKey pair for nats.conf
php artisan events:nats-keygen

# Generate an auth token for an agent
php artisan events:nats-generate-auth
```

Both `events:nats-listen` and `events:nats-auth-listen` are long-running processes and should be managed by Supervisor alongside the queue worker.

### Required .env Keys

```env
NATS_ENABLED=true
NATS_HOST=nats.example.com
NATS_PORT=4222
NATS_ACCOUNT_NKEY_PUBLIC=ACSGX...   # Account NKey public key (starts with A)
NATS_ACCOUNT_NKEY_SEED=SACSG...     # Account NKey seed — never expose this
NATS_AUTH_SERVICE_PASSWORD=...      # Password for the auth-service NATS user
NATS_TLS_CA=storage/nats-ca.crt    # CA cert path for TLS verification
```

### Auth Callout — How It Works

Every client that connects to NATS (agent or browser) is validated in real time by `NatsAuthCalloutService`. The service looks up the token in the database and returns a signed JWT that grants scoped publish/subscribe permissions.

```text
Client connects to NATS
  └── NATS sends auth request JWT to $SYS.REQ.USER.AUTH
        └── NatsAuthListenerCommand receives it
              └── NatsAuthCalloutService checks:
                    ├── iaas_compute_members.events_token  → agent.compute permissions
                    ├── iaas_storage_members.events_token  → agent.storage permissions
                    ├── iaas_network_members.events_token  → agent.network permissions
                    └── oauth_access_tokens.id             → client.{account_uuid} permissions
              └── Returns signed authorization_response JWT
                    └── NATS allows or rejects the connection
```

Token revocation is instant — remove or nullify `events_token` in the DB and the agent is rejected on its next connection attempt.

See [docs/nats-auth-callout.md](docs/nats-auth-callout.md) for the full JWT structure specification and implementation details.

### Subject Naming Convention

| Identity | Subscribes to | Publishes to |
| --- | --- | --- |
| `agent.compute.{uuid}` | `agent.compute.{uuid}.cmd`, `agent.compute.broadcast`, `agent.broadcast` | `agent.compute.{uuid}.evt` |
| `agent.storage.{uuid}` | `agent.storage.{uuid}.cmd`, `agent.storage.broadcast`, `agent.broadcast` | `agent.storage.{uuid}.evt` |
| `agent.network.{uuid}` | `agent.network.{uuid}.cmd`, `agent.network.broadcast`, `agent.broadcast` | `agent.network.{uuid}.evt` |
| Browser client | `client.{account_uuid}.evt`, `client.{account_uuid}.{user_uuid}.evt` | nothing |

## Planned Feature List

- [x] Dynamically saving the list of events
- [x] Triggering Action listeners
- [x] NATS pub/sub messaging
- [x] NATS auth callout (per-connection credential validation)
- [x] Real-time browser events via NATS WebSocket
- [ ] Triggering external HTTP listeners
- [ ] Triggering socket listeners
- [ ] Receiving external events
- [ ] Registering external events and binding third-party events in return

---

## Our Libraries

This library is part of the **NextDeveloper / PlusClouds open-source ecosystem**. Browse all available libraries and find the right building blocks for your next project:

[https://plusclouds.com/us/solutions/libraries](https://plusclouds.com/us/solutions/libraries)

---

## Join the Community

We believe great software is built together. The PlusClouds developer community is a place where engineers share ideas, ask questions, showcase what they have built, and help shape the direction of these libraries. Whether you are integrating a single package or building an entire platform on top of our stack, you are very welcome here.

Come and join us — we would love to see what you build:

[https://plusclouds.com/us/community](https://plusclouds.com/us/community)
