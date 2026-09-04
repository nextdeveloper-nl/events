<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NextDeveloper\Events\Exceptions\AgentTimeoutException;

/**
 * Sends a command to an agent over NATS and blocks until the agent replies.
 *
 * Uses the NATS request-reply (inbox) pattern so the caller gets a synchronous
 * result, making it suitable for use inside automation pipelines.
 *
 * Protocol envelope sent to agent.{agent_type}.{uuid}.cmd:
 * {
 *   "v": 1, "id": "<uuid>", "type": "command", "agent_type": "<type>",
 *   "agent_uuid": "<uuid>", "timestamp": <unix>,
 *   "payload": { "operation": "<op>", "params": {}, "timeout_s": <n> }
 * }
 *
 * The agent replies to the NATS inbox with the operation result.
 */
class AgentCommandService
{
    public function __construct(private readonly NatsService $nats)
    {
    }

    /**
     * Send a command to an agent and wait synchronously for the result.
     *
     * @param  string $agentType      Agent type, e.g. 'vm', 'compute', 's3', 'backup', 'dns'
     * @param  string $agentUuid      Agent identifier (usually the owning resource's UUID)
     * @param  string $operation      Operation name (e.g. 'agent.allowed_operations')
     * @param  array  $params         Optional parameters for the operation
     * @param  int    $timeoutSeconds Maximum seconds to wait for a reply
     * @return array                  Result payload returned by the agent
     * @throws AgentTimeoutException  If the agent does not reply within the timeout
     */
    public function send(string $agentType, string $agentUuid, string $operation, array $params = [], int $timeoutSeconds = 10): array
    {
        $commandId = (string) Str::uuid();
        $subject   = "agent.{$agentType}.{$agentUuid}.cmd";

        $envelope = [
            'v'          => 1,
            'id'         => $commandId,
            'type'       => 'command',
            'agent_type' => $agentType,
            'agent_uuid' => $agentUuid,
            'timestamp'  => time(),
            'payload'    => [
                'operation' => $operation,
                'params'    => empty($params) ? (object) [] : $params,
                'timeout_s' => $timeoutSeconds,
            ],
        ];

        Log::info('[AgentCommandService] Sending command', [
            'subject'    => $subject,
            'command_id' => $commandId,
            'operation'  => $operation,
        ]);

        $result = $this->nats->dispatch($subject, $envelope, (float) $timeoutSeconds);

        Log::info('[AgentCommandService] Command result received', [
            'command_id' => $commandId,
            'operation'  => $operation,
            'status'     => $result['status'] ?? 'unknown',
        ]);

        return $result;
    }
}
