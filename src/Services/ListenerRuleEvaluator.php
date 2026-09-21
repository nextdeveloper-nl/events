<?php

namespace NextDeveloper\Events\Services;

use Carbon\Carbon;
use NextDeveloper\Events\Database\Models\Listeners;

/**
 * Evaluates the routing rule stored on an event listener: `conditions` and `time_window`.
 *
 * conditions - json list of clauses, all of which must match (AND). A clause is
 *   {"field": "data.object.status", "operator": "eq", "value": "open"}
 * `field` is a dot path into the event envelope (see EventEnvelopeBuilder). Operators:
 *   eq, neq, in, not_in, contains, gt, gte, lt, lte, exists.
 * Empty / null conditions match everything. An unknown operator never matches (fail closed).
 *
 * time_window - json object, events outside it are dropped (not deferred):
 *   {"timezone": "Europe/Istanbul", "days": [1,2,3,4,5], "start": "09:00", "end": "18:00"}
 * days use ISO numbering (1 = Monday ... 7 = Sunday); start > end means the window wraps past midnight.
 * Every key is optional; empty / null means always.
 */
class ListenerRuleEvaluator
{
    public static function conditionsMatch(Listeners $listener, array $envelope): bool
    {
        $conditions = $listener->conditions;

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $clause) {
            if (!is_array($clause) || !self::clauseMatches($clause, $envelope)) {
                return false;
            }
        }

        return true;
    }

    public static function withinTimeWindow(Listeners $listener, ?Carbon $now = null): bool
    {
        $window = $listener->time_window;

        if (empty($window)) {
            return true;
        }

        $now = ($now ?? Carbon::now())->copy()->setTimezone($window['timezone'] ?? config('app.timezone', 'UTC'));

        if (!empty($window['days']) && !in_array($now->dayOfWeekIso, array_map('intval', $window['days']), true)) {
            return false;
        }

        if (!empty($window['start']) && !empty($window['end'])) {
            $time = $now->format('H:i');

            return $window['start'] <= $window['end']
                ? ($time >= $window['start'] && $time <= $window['end'])
                : ($time >= $window['start'] || $time <= $window['end']);
        }

        return true;
    }

    private static function clauseMatches(array $clause, array $envelope): bool
    {
        $operator = $clause['operator'] ?? 'eq';
        $expected = $clause['value'] ?? null;
        $actual   = data_get($envelope, $clause['field'] ?? '');

        return match ($operator) {
            'eq'       => $actual == $expected,
            'neq'      => $actual != $expected,
            'in'       => in_array($actual, (array) $expected, false),
            'not_in'   => !in_array($actual, (array) $expected, false),
            'contains' => is_string($actual) && $expected !== null && str_contains($actual, (string) $expected),
            'gt'       => is_numeric($actual) && $actual > $expected,
            'gte'      => is_numeric($actual) && $actual >= $expected,
            'lt'       => is_numeric($actual) && $actual < $expected,
            'lte'      => is_numeric($actual) && $actual <= $expected,
            'exists'   => $actual !== null,
            default    => false,
        };
    }
}
