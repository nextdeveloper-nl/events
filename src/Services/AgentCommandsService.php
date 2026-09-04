<?php

namespace NextDeveloper\Events\Services;

use NextDeveloper\Events\Database\Models\AgentCommands;
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
     *
     * $iamAccountId/$iamUserId let callers with no authenticated request (queue
     * jobs, console commands) supply attribution explicitly - e.g. the account/user
     * that owns the target resource - instead of relying on UserHelper::currentAccount()
     * /UserHelper::me(), which are null outside an HTTP request context.
     */
    public static function dispatch(
        string $agentType,
        string $agentUuid,
        string $operation,
        array  $params      = [],
        int    $timeoutS    = 300,
        ?int   $iamAccountId = null,
        ?int   $iamUserId    = null
    ): string {
        $iamAccountId ??= optional(UserHelper::currentAccount())->id;
        $iamUserId    ??= optional(UserHelper::me())->id;

        $run = function () use ($agentType, $agentUuid, $operation, $params, $timeoutS, $iamAccountId, $iamUserId) {
            $command = self::create([
                'agent_type'     => $agentType,
                'agent_uuid'     => $agentUuid,
                'operation'      => $operation,
                'params'         => $params,
                'status'         => 'pending',
                'timeout_at'     => now()->addSeconds($timeoutS),
                'iam_account_id' => $iamAccountId,
                'iam_user_id'    => $iamUserId,
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
        };

        // Writing the event_agent_commands row is a system-triggered side effect of an
        // already-authorized action (e.g. a member running/pausing/restoring their own
        // backup job) - the caller was already authorized against the parent resource
        // (BackupJobs, RestoreJobs, ...), not against event_agent_commands itself. Roles
        // like member only grant read on most tables, so without bypassing the
        // create-authorization check here, AgentCommandsObserver's creating() hook would
        // throw NotAllowedException and abort the whole request. Outside an authenticated
        // request (queue/console), there's no identity to preserve, so we elevate to the
        // system user instead via runAsAdmin().
        return UserHelper::me()
            ? UserHelper::withRolesCheckBypassed($run)
            : UserHelper::runAsAdmin($run);
    }

    /**
     * This method updated the model from an array.
     *
     * Same reasoning as dispatch(): status updates on a command row (e.g. marking it
     * 'sent') are a system-triggered side effect of an already-authorized action, so we
     * bypass the role check rather than requiring event_agent_commands:update on every
     * role. Outside an authenticated request (queue/console, e.g. the shared agent-event
     * listener Job), there's no identity to preserve, so we elevate to the system user
     * instead via runAsAdmin().
     *
     * @param  array $data
     * @return mixed
     */
    public static function update($id, array $data)
    {
        return UserHelper::me()
            ? UserHelper::withRolesCheckBypassed(fn () => parent::update($id, $data))
            : UserHelper::runAsAdmin(fn () => parent::update($id, $data));
    }

    /**
     * Closes out the agent_commands row referenced by a `result` event's payload.
     * Centralizes logic that was previously copy-pasted in each module's NATS
     * listener (result lookup by command_id, status/result/error/completed_at update).
     */
    public static function completeFromResult(array $resultPayload): ?AgentCommands
    {
        $commandUuid = $resultPayload['command_id'] ?? null;

        if (!$commandUuid) {
            return null;
        }

        $command = self::getByRef($commandUuid);

        if (!$command) {
            return null;
        }

        $status = in_array($resultPayload['status'] ?? null, ['completed', 'failed', 'rejected'], true)
            ? $resultPayload['status']
            : 'completed';

        return self::update($command->uuid, [
            'status'       => $status,
            'result'       => $resultPayload['output']  ?? [],
            'error'        => $resultPayload['message'] ?? null,
            'completed_at' => now(),
        ]);
    }
}