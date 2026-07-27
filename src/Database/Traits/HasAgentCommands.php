<?php

namespace NextDeveloper\Events\Database\Traits;

use NextDeveloper\Events\Services\AgentCommandService;
use NextDeveloper\Events\Services\AgentCommandsService;

/**
 * Gives an Eloquent model that represents an agent-bearing resource (a VM, a
 * compute node, an S3/backup server, a DNS server, ...) the ability to send
 * commands to its own NATS agent, without hand-building the envelope or
 * calling AgentCommandsService/AgentCommandService directly.
 *
 * The implementing model must define getAgentType() and have `uuid`,
 * `iam_account_id`, and `iam_user_id` attributes.
 */
trait HasAgentCommands
{
    /**
     * Dispatch a command asynchronously. Returns the command UUID, which can
     * be polled via the shared event_agent_commands table.
     */
    public function sendAgentCommand(string $operation, array $params = [], int $timeoutS = 300): string
    {
        $this->assertAgentOperationAllowed($operation);

        return AgentCommandsService::dispatch(
            $this->getAgentType(),
            $this->getAgentIdentifier(),
            $operation,
            $params,
            $timeoutS,
            $this->iam_account_id,
            $this->iam_user_id
        );
    }

    /**
     * Dispatch a command and block until the agent replies.
     */
    public function sendAgentCommandSync(string $operation, array $params = [], int $timeoutSeconds = 10): array
    {
        $this->assertAgentOperationAllowed($operation);

        return app(AgentCommandService::class)->send(
            $this->getAgentType(),
            $this->getAgentIdentifier(),
            $operation,
            $params,
            $timeoutSeconds
        );
    }

    /**
     * The agent_type used in the NATS subject and envelope (e.g. 'vm', 'compute', 's3', 'backup', 'dns').
     */
    abstract public function getAgentType(): string;

    /**
     * The value used as agent_uuid in the NATS subject/envelope. Defaults to the
     * model's own `uuid` - override when the agent identifier lives in a
     * different column (e.g. DnsServers keeps it in a separate `agent_uuid` column).
     */
    protected function getAgentIdentifier(): string
    {
        return $this->uuid;
    }

    /**
     * Hook for models that restrict which operations may be sent (e.g. VM/ComputeMember
     * validate against their available_operations['agent'] allow-list). No restriction
     * by default - override and throw \InvalidArgumentException to enforce one.
     */
    protected function assertAgentOperationAllowed(string $operation): void
    {
    }
}
