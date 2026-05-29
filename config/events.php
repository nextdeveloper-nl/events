<?php

return [
    'general' => [
        'save_events' => env('EVENTS_CREATE_EVENTS', false),
    ],

    'nats' => [
        // Set to true to enable NATS publishing
        'enabled'  => env('NATS_ENABLED', false),

        // Browser WebSocket connection (used in JS client config only)
        'host'     => env('NATS_HOST', '127.0.0.1'),
        'port'     => env('NATS_PORT', 443),

        // Server-side PHP connection (raw NATS TCP, port 4222)
        'server_host' => env('NATS_SERVER_HOST', env('NATS_HOST', '127.0.0.1')),
        'server_port' => env('NATS_SERVER_PORT', 4222),

        // Token auth (simple)
        'token'    => env('NATS_TOKEN'),

        // Username/password auth
        'user'     => env('NATS_USER'),
        'password' => env('NATS_PASSWORD'),

        // mTLS — paths to certificate files mounted in the container
        'tls_cert' => env('NATS_TLS_CERT'),
        'tls_key'  => env('NATS_TLS_KEY'),
        'tls_ca'   => env('NATS_TLS_CA'),

        // Queue to dispatch NatsPublisherJob on
        'queue'    => env('NATS_QUEUE', 'default'),

        // Account NKey keypair for signing auth callout responses
        // Generate with: php artisan events:nats-keygen
        'account_nkey_public' => env('NATS_ACCOUNT_NKEY_PUBLIC'),
        'account_nkey_seed'   => env('NATS_ACCOUNT_NKEY_SEED'),

        // Auth service credentials (this user bypasses the auth callout in NATS)
        'auth_service_password' => env('NATS_AUTH_SERVICE_PASSWORD'),

        // Path where NatsAuthConfigService writes the generated auth config
        'auth_config_path' => env('NATS_AUTH_CONFIG_PATH', '/etc/nats/nats_auth.conf'),

        // Command to reload NATS after writing the auth config
        'reload_command' => env('NATS_RELOAD_COMMAND', 'docker compose kill -s HUP nats'),

        /**
         * Subject → handler job class mappings for the nats:listen command.
         * The handler job must accept (array $payload, string $subject) in its constructor.
         *
         * NATS wildcards are supported:
         *   *  matches a single token  (e.g. agent.status.*)
         *   >  matches everything after (e.g. agent.>)
         *
         * Example:
         *   'agent.status.*'     => \App\Jobs\Nats\HandleAgentStatus::class,
         *   'agent.results.*'    => \App\Jobs\Nats\HandleAgentResult::class,
         *   'agent.heartbeat.*'  => \App\Jobs\Nats\HandleAgentHeartbeat::class,
         */
        'subscribers' => [],

        /**
         * Events and object types to omit from NATS publishing.
         *
         * 'omit_events' — skip publishing when the event name matches.
         *   Supports exact strings and wildcards (* = any string).
         *   Event names follow the pattern: "{action}:{namespace}\{Model}"
         *
         * Examples:
         *   'creating:*'                                          — skip all pre-create events
         *   'saving:*'                                            — skip all pre-save events
         *   'created:NextDeveloper\IAM\Users'                     — skip this specific event
         *
         * 'omit_objects' — skip publishing when the model class matches.
         *   Use the full model class name.
         *
         * Examples:
         *   'NextDeveloper\IAM\Database\Models\Users'
         *   'NextDeveloper\Commons\Database\Models\Logs'
         */
        'omit_events' => [
            'creating:*',
            'saving:*',
            'deleting:*',
        ],

        'omit_objects' => [
            //  'NextDeveloper\\IAAS\\ComputeMemberEvents',
        ],
    ],
];
