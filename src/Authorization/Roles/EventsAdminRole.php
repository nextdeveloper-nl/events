<?php

namespace NextDeveloper\Events\Authorization\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NextDeveloper\IAM\Authorization\Roles\AbstractRole;
use NextDeveloper\IAM\Authorization\Roles\IAuthorizationRole;
use NextDeveloper\IAM\Database\Models\Users;

class EventsAdminRole extends AbstractRole implements IAuthorizationRole
{
    public const NAME = 'events-admin';

    public const LEVEL = 100;

    public const DESCRIPTION = 'Events administrator with full access to available events, listeners and agent commands across all accounts.';

    public const DB_PREFIX = 'event';

    /**
     * Admin sees everything — no additional WHERE conditions applied.
     */
    public function apply(Builder $builder, Model $model)
    {
        //  No restrictions for admin
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
     * Every object is listed twice on purpose:
     * - `events_*` is the form the IAM Authorize middleware builds from the URI (/events/<object>),
     * - `event_*` is the table name form used by UserHelper::can() / checkCreatePolicy in the observers.
     */
    public function allowedOperations(): array
    {
        return [
            // Available events — global catalog of events that can be listened to. Read-only for
            // everyone, including admins: events must never be created, updated or deleted via the API.
            'events_available:read',
            'event_available:read',

            // Listeners — callback is a PHP class dispatched globally by Events::fire(), so writes are admin-only.
            // Admin can add and remove listeners but never update one (update is deliberately not granted).
            'events_listeners:read',
            'events_listeners:create',
            'events_listeners:delete',
            'event_listeners:read',
            'event_listeners:create',
            'event_listeners:delete',

            // Agent commands — commands queued for on-host agents
            'events_agent_commands:read',
            'events_agent_commands:create',
            'events_agent_commands:update',
            'events_agent_commands:delete',
            'event_agent_commands:read',
            'event_agent_commands:create',
            'event_agent_commands:update',
            'event_agent_commands:delete',
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
