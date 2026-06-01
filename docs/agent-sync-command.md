# Synchronous Agent Command Protocol

## Overview

The platform supports synchronous command-response with VM agents via `AgentCommandService::send()`.  
The caller publishes a command and blocks until the agent replies or a timeout is reached.

This requires one small update to the Go agent: honour the `reply_to` field in the command envelope.

---

## Why `reply_to` in the Payload (Not NATS Header)

The `agent.vm.{uuid}.cmd` subject is backed by a **JetStream stream** (`AGENT_COMMANDS`).  
When a NATS message is published with a `replyTo` header, **JetStream intercepts it and acks to that header immediately** — the platform receives the JetStream PubAck instead of the agent's result.

To avoid this, the platform embeds the reply inbox inside the **payload** as `reply_to`.  
The agent reads it and publishes the result directly to that subject.

---

## Protocol Flow

```
PHP (AgentCommandService)       JetStream            Go Agent
        |                           |                    |
        | subscribe(_INBOX.xyz)     |                    |
        |                           |                    |
        | publish(agent.vm.{uuid}.cmd)                   |
        |   payload includes                             |
        |   "reply_to": "_INBOX.xyz" --> deliver msg --> |
        |                           |                    | execute operation
        |                           |                    |
        | <---- publish(_INBOX.xyz, result) ------------ |
        |                           |
        | return result to caller   |
```

---

## Command Envelope (Received by Agent)

```json
{
  "v": 1,
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "type": "command",
  "agent_type": "vm",
  "agent_uuid": "d6199047-322a-4845-bdea-1d44dd1b49e5",
  "timestamp": 1748000000,
  "reply_to": "_INBOX.7f3a1c2d-...",
  "payload": {
    "operation": "services.get",
    "params": {},
    "timeout_s": 10
  }
}
```

`reply_to` is **optional**. When absent the agent uses its existing async result path.

---

## Result Envelope (Agent Must Send Back)

```json
{
  "v": 1,
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "type": "result",
  "agent_type": "vm",
  "agent_uuid": "d6199047-322a-4845-bdea-1d44dd1b49e5",
  "timestamp": 1748000001,
  "payload": {
    "command_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "output": { }
  }
}
```

On error, set `"status": "error"` and put the error message in `output`.

---

## Changes Required in the Go Agent

### 1. Add `ReplyTo` to the Command Envelope Struct

```go
type CommandEnvelope struct {
    V         int            `json:"v"`
    ID        string         `json:"id"`
    Type      string         `json:"type"`
    AgentType string         `json:"agent_type"`
    AgentUUID string         `json:"agent_uuid"`
    Timestamp int64          `json:"timestamp"`
    ReplyTo   string         `json:"reply_to"`  // ← ADD THIS
    Payload   CommandPayload `json:"payload"`
}
```

### 2. Publish Result to `ReplyTo` When Present

```go
func (a *Agent) handleCommand(env CommandEnvelope) {
    result, err := a.execute(env.Payload.Operation, env.Payload.Params)

    status := "completed"
    output := result

    if err != nil {
        status = "error"
        output = map[string]string{"message": err.Error()}
    }

    response := ResultEnvelope{
        V:         1,
        ID:        env.ID,
        Type:      "result",
        AgentType: "vm",
        AgentUUID: env.AgentUUID,
        Timestamp: time.Now().Unix(),
        Payload: ResultPayload{
            CommandID: env.ID,
            Status:    status,
            Output:    output,
        },
    }

    raw, _ := json.Marshal(response)

    if env.ReplyTo != "" {
        // Synchronous caller is waiting on this inbox — reply directly
        a.nats.Publish(env.ReplyTo, raw)
        return
    }

    // Async path — unchanged behaviour
    a.nats.Publish(fmt.Sprintf("agent.vm.%s", env.AgentUUID), raw)
}
```

> **Important:** The error path must also respect `reply_to`. If it does not publish on error, the PHP caller will hang until the `timeoutSeconds` expires.

---

## PHP Usage

```php
use NextDeveloper\Events\Services\AgentCommandService;
use NextDeveloper\Events\Exceptions\AgentTimeoutException;

try {
    $result = app(AgentCommandService::class)->send(
        agentUuid:      $vm->uuid,
        operation:      'services.get',
        params:         [],
        timeoutSeconds: 10
    );

    // $result is the decoded payload from the agent
} catch (AgentTimeoutException $e) {
    // Agent did not reply within timeoutSeconds
}
```

---

## Summary of Changes

| File / Area                  | Change                                                  |
|------------------------------|---------------------------------------------------------|
| Command envelope struct      | Add `ReplyTo string json:"reply_to"`                    |
| Command handler — success    | Publish result to `env.ReplyTo` if set                  |
| Command handler — error      | Publish error result to `env.ReplyTo` if set            |
| JetStream consumer setup     | No change                                               |
| Heartbeat / telemetry        | No change                                               |
| Capabilities handler         | No change                                               |

The async path (no `reply_to`) is completely untouched — existing behaviour is fully backward compatible.
