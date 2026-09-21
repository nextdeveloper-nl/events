<?php

namespace NextDeveloper\Events\Services;

use NextDeveloper\Commons\Exceptions\NotAllowedException;
use NextDeveloper\Events\Services\AbstractServices\AbstractAvailablesService;

/**
 * This class is responsible from managing the data for Availables
 *
 * Class AvailablesService.
 *
 * @package NextDeveloper\Events\Database\Models
 */
class AvailablesService extends AbstractAvailablesService
{
    /**
     * Available events are read-only through the API; the catalog is filled from code only.
     *
     * @throws NotAllowedException
     */
    public static function create(array $data)
    {
        throw new NotAllowedException('Available events cannot be created through the API.');
    }

    /**
     * Events must never be updated, so this is blocked for everyone including admins.
     *
     * @throws NotAllowedException
     */
    public static function update($id, array $data)
    {
        throw new NotAllowedException('Events cannot be updated.');
    }

    /**
     * Same as update(): events must never be updated.
     *
     * @throws NotAllowedException
     */
    public static function updateRaw(array $data): never
    {
        throw new NotAllowedException('Events cannot be updated.');
    }

    /**
     * Available events cannot be removed through the API.
     *
     * @throws NotAllowedException
     */
    public static function delete($id)
    {
        throw new NotAllowedException('Available events cannot be deleted.');
    }


    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
