<?php

namespace NextDeveloper\Events\Console\Commands;

use Illuminate\Console\Command;
use NextDeveloper\Events\Services\NatsAuthConfigService;

/**
 * Generates the NATS authorization config from the database and reloads NATS.
 *
 * Run manually:
 *   php artisan events:nats-generate-auth
 *   php artisan events:nats-generate-auth --dry-run
 *   php artisan events:nats-generate-auth --no-reload
 */
class NatsGenerateAuthCommand extends Command
{
    protected $signature = 'events:nats-generate-auth
                            {--dry-run  : Print the generated config without writing or reloading}
                            {--no-reload : Write the config file but skip NATS reload}';

    protected $description = 'Generate NATS authorization config from DB and reload NATS';

    public function handle(NatsAuthConfigService $service): int
    {
        if ($this->option('dry-run')) {
            $this->line($service->generate());
            return 0;
        }

        $path = config('events.nats.auth_config_path', '/etc/nats/nats_auth.conf');
        $this->info("Writing auth config to: {$path}");

        $service->write();
        $this->info('Auth config written.');

        if (!$this->option('no-reload')) {
            $this->info('Reloading NATS...');
            $service->reload();
            $this->info('Done.');
        }

        return 0;
    }
}
