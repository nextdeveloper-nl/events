<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NextDeveloper\Events\Exceptions\AgentTimeoutException;

/**
 * Sends a command to a VM agent over NATS and blocks until the agent replies.
 *
 * Uses the NATS request-reply (inbox) pattern so the caller gets a synchronous
 * result, making it suitable for use inside automation pipelines.
 *
 * Protocol envelope sent to agent.vm.{uuid}.cmd:
 * {
 *   "v": 1, "id": "<uuid>", "type": "command", "agent_type": "vm",
 *   "agent_uuid": "<vm-uuid>", "timestamp": <unix>,
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
     * Send a command to a VM agent and wait synchronously for the result.
     *
     * @param  string $agentUuid      VM UUID used as the agent identifier
     * @param  string $operation      Operation name (e.g. 'agent.allowed_operations')
     * @param  array  $params         Optional parameters for the operation
     * @param  int    $timeoutSeconds Maximum seconds to wait for a reply
     * @return array                  Result payload returned by the agent
     * @throws AgentTimeoutException  If the agent does not reply within the timeout
     */
    public function send(string $agentUuid, string $operation, array $params = [], int $timeoutSeconds = 10): array
    {
        $commandId = (string) Str::uuid();
        $subject   = "agent.vm.{$agentUuid}.cmd";

        $envelope = [
            'v'          => 1,
            'id'         => $commandId,
            'type'       => 'command',
            'agent_type' => 'vm',
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
