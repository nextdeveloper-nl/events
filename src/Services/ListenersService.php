<?php

namespace NextDeveloper\Events\Services;

use NextDeveloper\Commons\Exceptions\NotAllowedException;
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
