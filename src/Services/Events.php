<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Events\Jobs\NatsPublisherJob;

class Events
{
    public static function listen($event, $job)
    {
        self::listenEvent($event, $job);
    }

    public static function listenEvent($event, $job)
    {
        try {
            DB::insert(
                'INSERT INTO event_listeners (event, callback) VALUES (?, ?) ON CONFLICT DO NOTHING',
                [$event, $job],
            );
        } catch (\Exception $e) {
            if ($e->getCode() == 23505) {
                return;
            }
        }
    }

    public static function fire(string $eventName, Model $model, $params = [])
    {
        if (config('leo.debug.event_listener')) {
            Log::info('[EventListener] This event is triggered: ' . $eventName);
        }

        if (config('events.general.save_events')) {
            self::createEvent($eventName);
        }

        $listeners = DB::select('SELECT * FROM event_listeners WHERE event = ?', [$eventName]);

        $params = ['event' => $eventName];

        // Push the transformed model to the account's NATS subject so browser
        // clients receive real-time updates without registering a listener.
        if (config('events.nats.enabled', false) && !self::isOmitted($eventName, $model)) {
            try {
                NatsPublisherJob::dispatch($model, $params);
            } catch (\Throwable $e) {
                Log::error('[Events::fire] NATS dispatch failed for ' . $eventName . ': ' . $e->getMessage());
            }
        }

        foreach ($listeners as $listener) {
            $job = $listener->callback;

            if (!class_exists($job)) {
                Log::warning('[Events::fire] Listener class not found, skipping: ' . $job);
                continue;
            }

            try {
                $job::dispatch($model, $params);
            } catch (\Exception $e) {
                Log::error(
                    __METHOD__ . ' | We have an exception while firing an event listener: '
                    . $e->getMessage(),
                );
                Log::error($e->getTraceAsString());
            }
        }
    }

    public static function createEvent(string $eventName)
    {
        // This is a dummy implementation
        try {
            DB::insert('INSERT INTO event_available (event) VALUES (?) ON CONFLICT DO NOTHING', [$eventName]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23505) {
                return;
            }
        }
    }

    /**
     * Check whether an event should be omitted from NATS publishing.
     * Matches against events.nats.omit_events (supports * wildcard)
     * and events.nats.omit_objects (exact model class match).
     */
    private static function isOmitted(string $eventName, Model $model): bool
    {
        $omitEvents  = config('events.nats.omit_events', []);
        $omitObjects = config('events.nats.omit_objects', []);

        foreach ($omitEvents as $pattern) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            if (preg_match($regex, $eventName)) {
                return true;
            }
        }

        // Config may use short form (e.g. NextDeveloper\Accounting\Invoices) instead of
        // the full model class (NextDeveloper\Accounting\Database\Models\Invoices), so we
        // normalise by stripping \Database\Models\ before comparing.
        $modelClass       = get_class($model);
        $normalizedClass  = str_replace('\\Database\\Models\\', '\\', $modelClass);

        if (in_array($modelClass, $omitObjects, true) || in_array($normalizedClass, $omitObjects, true)) {
            return true;
        }

        return false;
    }
}
