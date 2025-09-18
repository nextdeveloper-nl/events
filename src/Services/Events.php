<?php

namespace NextDeveloper\Events\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        foreach ($listeners as $listener) {
            try {
                $job = $listener->callback;

                $params = array_merge($params, [
                    'event' => $eventName,
                ]);

                $class = new $job($model, $params);
                $job::dispatch($model);
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
}
