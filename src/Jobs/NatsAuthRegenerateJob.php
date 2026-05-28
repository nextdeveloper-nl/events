<?php

namespace NextDeveloper\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NextDeveloper\Events\Services\NatsAuthConfigService;

/**
 * Regenerates the NATS auth config and reloads NATS when an agent's events_token changes.
 *
 * Register as a listener for agent update events:
 *   Events::listen('updated:NextDeveloper\IAAS\ComputeMembers', NatsAuthRegenerateJob::class);
 *   Events::listen('updated:NextDeveloper\IAAS\StorageMembers', NatsAuthRegenerateJob::class);
 *   Events::listen('updated:NextDeveloper\IAAS\NetworkMembers', NatsAuthRegenerateJob::class);
 *   Events::listen('created:NextDeveloper\IAAS\ComputeMembers', NatsAuthRegenerateJob::class);
 *   Events::listen('deleted:NextDeveloper\IAAS\ComputeMembers', NatsAuthRegenerateJob::class);
 */
class NatsAuthRegenerateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Model $model,
        private readonly array $params
    ) {}

    public function handle(NatsAuthConfigService $service): void
    {
        $service->write();
        $service->reload();
    }
}
