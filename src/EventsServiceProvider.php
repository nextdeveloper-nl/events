<?php

namespace NextDeveloper\Events;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Commons\AbstractServiceProvider;

/**
 * Class CommunicationServiceProvider
 *
 * @package NextDeveloper\Communication
 */
class EventsServiceProvider extends AbstractServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    /**
     * @throws \Exception
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [
            __DIR__.'/../config/events.php' => config_path('events.php'),
            ], 'config'
        );

        $this->loadViewsFrom($this->dir.'/../resources/views', 'Events');

        //        $this->bootErrorHandler();
        $this->bootChannelRoutes();
        $this->bootModelBindings();
        $this->bootEvents();
        $this->bootLogger();
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->registerHelpers();
        $this->registerRoutes();
        $this->registerCommands();

        $this->mergeConfigFrom(__DIR__.'/../config/events.php', 'events');
    }

    /**
     * @return void
     */
    public function bootLogger()
    {
        //        $monolog = Log::getMonolog();
        //        $monolog->pushProcessor(new \Monolog\Processor\WebProcessor());
        //        $monolog->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
        //        $monolog->pushProcessor(new \Monolog\Processor\MemoryPeakUsageProcessor());
    }

    /**
     * @return array
     */
    public function provides()
    {
        return ['events'];
    }

    /**
     * @return void
     */
    private function bootChannelRoutes()
    {
        if (file_exists(($file = $this->dir.'/../config/channel.routes.php'))) {
            include_once $file;
        }
    }

    /**
     * @return void
     */
    protected function bootEvents()
    {
        $configs = config()->all();

        foreach ($configs as $key => $value) {
            if (config()->has($key.'.events')) {
                foreach (config($key.'.events') as $event => $handlers) {
                    foreach ($handlers as $handler) {
                        $this->app['events']->listen($event, $handler);
                    }
                }
            }
        }
    }

    /**
     * Register module routes
     *
     * @return void
     */
    protected function registerRoutes()
    {
        if ( ! $this->app->routesAreCached() && config('leo.allowed_routes.events', true) ) {
            $this->app['router']
                ->namespace('NextDeveloper\Events\Http\Controllers')
                ->group(__DIR__.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'api.routes.php');
        }
    }

    /**
     * Registers module based commands
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                [

                ]
            );
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

    private function bootSchedule()
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->call(function () {})->monthly();

            //	Daily jobs
            $schedule->call(function () {})->daily();

            //  Güne başlarken taskları
            $schedule->call(function () {})->weekdays()->dailyAt('09:00');

            $schedule->call(function () {})->weekdays()->dailyAt('12:00');

            //	Hourly Jobs
            $schedule->call(function () {})->hourly();

            $schedule->call(function () {})->everyFifteenMinutes();

            $schedule->call(function () {
                logger()->info('[Events] Every minute jobs start');
            })->everyMinute();
        });
    }
}
