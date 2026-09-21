<?php

namespace NextDeveloper\Events\Services;

use NextDeveloper\Commons\Database\Models\Pushers;
use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\Events\Jobs\EventPusherJob;
use NextDeveloper\Events\Services\AbstractServices\AbstractListenersService;

/**
 * This class is responsible from managing the data for Listeners
 *
 * Class ListenersService.
 *
 * @package NextDeveloper\Events\Database\Models
 */
class ListenersService extends AbstractListenersService
{
    /**
     * Adds a listener. When a pusher is given (common_pusher_id, a uuid) the listener is wired to the generic
     * EventPusherJob and the pusher must be one of the event_* providers, so an event can never be fed to a
     * pusher built for another payload (e.g. a Flow or CRM pusher). The pusher lookup goes through the model
     * scopes, so a caller can only pick pushers they are allowed to see.
     *
     * @throws NotAllowedException
     */
    public static function create(array $data)
    {
        if (!empty($data['common_pusher_id'])) {
            $pusherId = DatabaseHelper::uuidToId(Pushers::class, $data['common_pusher_id']);
            $pusher   = $pusherId ? Pushers::where('id', $pusherId)->first() : null;

            if (!$pusher || !str_starts_with((string) $pusher->provider, 'event_')) {
                throw new NotAllowedException('The pusher does not exist or is not an event pusher (provider event_*).');
            }

            $data['common_pusher_id'] = $pusher->id;
            $data['callback']         = EventPusherJob::class;
        }

        return parent::create($data);
    }

    /**
     * Listeners can be added or removed but never updated, blocked for everyone including admins.
     *
     * @throws NotAllowedException
     */
    public static function update($id, array $data)
    {
        throw new NotAllowedException('Listeners cannot be updated. Delete and add a new one instead.');
    }

    /**
     * Same as update(): listeners must never be updated.
     *
     * @throws NotAllowedException
     */
    public static function updateRaw(array $data): never
    {
        throw new NotAllowedException('Listeners cannot be updated. Delete and add a new one instead.');
    }


    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
