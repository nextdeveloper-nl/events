<?php

namespace NextDeveloper\Events\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\Commons\Helpers\DatabaseHelper;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;
use NextDeveloper\IAM\Helpers\UserHelper;

class EventsUserRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'events-user';

    public const LEVEL = 200;

    public const DESCRIPTION = 'Events user with read-only access to the available events catalog and the listeners of their own account.';

    public const DB_PREFIX = 'event';

    /**
     * Restricts queries to the current account for tables that have iam_account_id (event_listeners).
     * Tables without it (event_available) are a global catalog and are not filtered.
     */
    public function apply(Builder $builder, Model $model)
    {
        if (DatabaseHelper::isColumnExists($model->getTable(), 'iam_account_id')) {
            $builder->where('iam_account_id', UserHelper::currentAccount()->id);
        }
    }

    public function checkPrivileges(?Users $users = null)
    {
        //
    }

    public function getModule()
    {
        return 'events';
    }

    /**
     * Read-only. Listeners cannot be written by users because their callback is a PHP class name that
     * Events::fire() dispatches for every matching event across all accounts. Agent commands are not
     * exposed at all. Both name forms are listed: `events_*` (URI form used by the Authorize middleware)
     * and `event_*` (table form used by UserHelper::can()).
     */
    public function allowedOperations(): array
    {
        return [
            // Available events — read-only catalog
            'events_available:read',
            'event_available:read',

            // Listeners — read-only, scoped to own account by apply()
            'events_listeners:read',
            'event_listeners:read',
        ];
    }

    public function getLevel(): int
    {
        return self::LEVEL;
    }

    public function getDescription(): string
    {
        return self::DESCRIPTION;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function canBeApplied(mixed $column): bool
    {
        if (self::DB_PREFIX === '*') {
            return true;
        }

        if (Str::startsWith($column, self::DB_PREFIX)) {
            return true;
        }

        return false;
    }

    public function getDbPrefix()
    {
        return self::DB_PREFIX;
    }

    public function checkRules(Users $_users): bool
    {
        return true;
    }
}
