<?php

namespace NextDeveloper\Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NextDeveloper\Events\Services\EventPusherService;

/**
 * Generic listener callback: hands a fired event to the pusher configured on the listener.
 *
 * Register a listener with callback = NextDeveloper\Events\Jobs\EventPusherJob and a common_pusher_id.
 * Events::fire() dispatches this job with the id of the listener row that matched (a third argument the older
 * callbacks do not get), so one job class serves any number of listeners with different pushers / rules.
 * All logic is in EventPusherService.
 */
class EventPusherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Model $model,
        private readonly array $params,
        private readonly ?int $listenerId = null
    ) {
        $this->queue = config('events.pushers.queue', 'default');
    }

    public function handle(): void
    {
        if ($this->listenerId === null) {
            return;
        }

        EventPusherService::dispatch($this->listenerId, $this->model, $this->params);
    }
}
