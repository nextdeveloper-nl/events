<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Support\Str;
use NextDeveloper\Events\Services\AbstractServices\AbstractAgentCommandsService;
use NextDeveloper\IAM\Helpers\UserHelper;

/**
 * This class is responsible from managing the data for AgentCommands
 *
 * Class AgentCommandsService.
 *
 * @package NextDeveloper\Events\Database\Models
 */
class AgentCommandsService extends AbstractAgentCommandsService
{

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    /**
     * Create an agent_commands record, publish the command envelope to NATS, and
     * mark the record as sent. Returns the command UUID for async tracking.
     */
    public static function dispatch(
        string $agentType,
        string $agentUuid,
        string $operation,
        array  $params   = [],
        int    $timeoutS = 300
    ): string {
        $command = self::create([
            'agent_type'    => $agentType,
            'agent_uuid'    => $agentUuid,
            'operation'     => $operation,
            'params'        => $params,
            'status'        => 'pending',
            'timeout_at'    => now()->addSeconds($timeoutS),
            'iam_account_id' => UserHelper::currentAccount()->id,
            'iam_user_id'   => UserHelper::me()->id,
        ]);

        $envelope = [
            'v'          => 1,
            'id'         => $command->uuid,
            'type'       => 'command',
            'agent_type' => $agentType,
            'agent_uuid' => $agentUuid,
            'timestamp'  => time(),
            'payload'    => [
                'operation' => $operation,
                'params'    => $params,
                'timeout_s' => $timeoutS,
            ],
        ];

        app(NatsService::class)->publish("agent.{$agentType}.{$agentUuid}.cmd", $envelope);

        self::update($command->uuid, [
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $command->uuid;
    }
}