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

    public static function fire(string $eventName, Model $model)
    {
        if(config('leo.debug.event_listener'))
        Log::info('[EventListener] This event is triggered: ' . $eventName);

        if(config('events.general.save_events')) {
            self::createEvent($eventName);
        }

        $listeners = DB::select('SELECT * FROM event_listeners WHERE event = ?', [$eventName]);

        if($listeners) {
            $job = $listeners[0]->callback;
            $class = new $job($model);
            $job::dispatch($model);
        }
    }

    public static function createEvent(string $eventName)
    {
        // This is a dummy implementation
        try {
            DB::insert('INSERT INTO event_available (event) VALUES (?)', [$eventName]);
        } catch (\Exception $e) {
            if($e->getCode() == 23505)
                return;
        }
    }

    public static function listenEvent($event, $job)
    {
        try {
            DB::insert('INSERT INTO event_listeners (event, callback) VALUES (?, ?)', [$event, $job]);
        } catch (\Exception $e) {
            if($e->getCode() == 23505)
                return;
        }
    }
}
