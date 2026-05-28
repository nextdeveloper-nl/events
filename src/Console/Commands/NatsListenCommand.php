<?php

namespace NextDeveloper\Events\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use NextDeveloper\Events\Services\NatsService;

/**
 * Long-running worker that subscribes to NATS subjects and dispatches
 * handler jobs for each received message.
 *
 * Subjects and handlers are configured in config('events.nats.subscribers').
 * Additional subjects can be passed as CLI arguments.
 *
 * Usage:
 *   php artisan events:nats-listen
 *   php artisan events:nats-listen --subject=agent.status.* --handler=App\\Jobs\\HandleAgentStatus
 *
 * Run via supervisor just like a queue worker.
 */
class NatsListenCommand extends Command
{
    protected $signature = 'events:nats-listen
                            {--subject=* : Additional subject to subscribe to (requires --handler)}
                            {--handler=  : Handler job class for the --subject flag}
                            {--timeout=0.1 : Seconds to wait for messages per poll cycle}';

    protected $description = 'Start a NATS subscriber worker that dispatches handler jobs for incoming messages';

    private bool $shouldQuit = false;

    public function handle(NatsService $nats): int
    {
        if (!config('events.nats.enabled', false)) {
            $this->error('NATS is not enabled. Set NATS_ENABLED=true in your .env file.');
            return 1;
        }

        $subscribers = $this->resolveSubscribers();

        if (empty($subscribers)) {
            $this->error('No subscribers configured. Add entries to config(\'events.nats.subscribers\') or use --subject and --handler.');
            return 1;
        }

        $this->registerSignalHandlers();

        foreach ($subscribers as $subject => $handlerClass) {
            $this->registerSubscriber($nats, $subject, $handlerClass);
        }

        $this->info('Listening on ' . count($subscribers) . ' subject(s). Press Ctrl+C to stop.');
        foreach ($subscribers as $subject => $handler) {
            $this->line("  {$subject} → {$handler}");
        }

        $timeout = (float) $this->option('timeout');

        while (!$this->shouldQuit) {
            $nats->process($timeout);
            pcntl_signal_dispatch();
        }

        $this->info('NATS listener stopped gracefully.');
        return 0;
    }

    private function registerSubscriber(NatsService $nats, string $subject, string $handlerClass): void
    {
        if (!class_exists($handlerClass)) {
            $this->warn("Handler class not found, skipping subject [{$subject}]: {$handlerClass}");
            return;
        }

        $nats->subscribe($subject, function (array|string $payload, string $receivedSubject, ?string $replyTo) use ($handlerClass) {
            Log::debug('[NatsListenCommand] Message received', [
                'subject'  => $receivedSubject,
                'handler'  => $handlerClass,
                'reply_to' => $replyTo,
            ]);

            try {
                $handlerClass::dispatch($payload, $receivedSubject, $replyTo);
            } catch (\Throwable $e) {
                Log::error('[NatsListenCommand] Failed to dispatch handler', [
                    'subject' => $receivedSubject,
                    'handler' => $handlerClass,
                    'error'   => $e->getMessage(),
                ]);
            }
        });

        $this->line("Subscribed: {$subject}");
    }

    private function resolveSubscribers(): array
    {
        // Start from config
        $subscribers = config('events.nats.subscribers', []);

        // Merge in CLI-supplied subject/handler pair
        $cliSubjects = $this->option('subject');
        $cliHandler  = $this->option('handler');

        if (!empty($cliSubjects) && !empty($cliHandler)) {
            foreach ((array) $cliSubjects as $subject) {
                $subscribers[$subject] = $cliHandler;
            }
        }

        return $subscribers;
    }

    private function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        // Graceful shutdown on SIGTERM (supervisor stop) and SIGINT (Ctrl+C)
        pcntl_signal(SIGTERM, fn () => $this->shouldQuit = true);
        pcntl_signal(SIGINT,  fn () => $this->shouldQuit = true);
    }
}
