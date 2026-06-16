<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | When false the package is dormant: no publishing happens server-side and
    | the browser client never connects. Toggle this from the admin plugin page
    | (it is persisted in the realtime_settings table and overrides the env).
    */
    'enabled' => env('REALTIME_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Broker connection
    |--------------------------------------------------------------------------
    | "host"/"port" are used by the PHP publisher (php-mqtt) over plain TCP.
    | "ws_*" is what the browser uses to reach EMQX over (secure) WebSockets.
    | In production terminate TLS at your reverse proxy and point ws_url at it,
    | e.g. wss://takeone.bh/mqtt
    */
    'broker' => [
        'host'      => env('REALTIME_MQTT_HOST', '127.0.0.1'),
        'port'      => (int) env('REALTIME_MQTT_PORT', 1883),
        'client_id' => env('REALTIME_MQTT_CLIENT_ID', 'takeone-laravel'),

        // Backend (server) credentials used by the PHP publisher.
        'username'  => env('REALTIME_MQTT_USERNAME', 'takeone-server'),
        'password'  => env('REALTIME_MQTT_PASSWORD', ''),

        // Public WebSocket endpoint the browser connects to.
        'ws_url'    => env('REALTIME_MQTT_WS_URL', 'ws://127.0.0.1:8083/mqtt'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser auth token (JWT)
    |--------------------------------------------------------------------------
    | The Laravel app mints a short-lived HS256 JWT per signed-in user. EMQX
    | verifies the signature with the same secret and enforces the embedded ACL
    | so a user can only subscribe to their own topics. Keep the secret in sync
    | with the broker's emqx auth config (docker/emqx/emqx.conf placeholder).
    */
    'jwt' => [
        'secret' => env('REALTIME_JWT_SECRET', env('APP_KEY')),
        'ttl'    => (int) env('REALTIME_JWT_TTL', 3600), // seconds
        'algo'   => 'HS256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Topic naming
    |--------------------------------------------------------------------------
    | All topics are namespaced under this prefix. A user subscribes to
    | {prefix}/user/{id}/# and the server publishes to the leaf channels.
    */
    'prefix'   => env('REALTIME_TOPIC_PREFIX', 'takeone'),
    'channels' => [
        'notifications' => 'notifications',
        'messages'      => 'messages',
        'presence'      => 'presence',
    ],

    /*
    |--------------------------------------------------------------------------
    | Publish behaviour
    |--------------------------------------------------------------------------
    | "fail_silently" keeps web requests working even if the broker is down —
    | the DB write (source of truth) still succeeds and the user picks the
    | change up on next page load. Errors are logged.
    */
    'qos'           => (int) env('REALTIME_QOS', 1),
    'retain'        => false,
    'fail_silently' => env('REALTIME_FAIL_SILENTLY', true),

    /*
    |--------------------------------------------------------------------------
    | Admin page
    |--------------------------------------------------------------------------
    | Route + middleware used to mount the plugin management screen. Override
    | these when embedding the package in a project with different guards.
    */
    'admin' => [
        'route_prefix' => 'admin/plugins/realtime',
        'route_name'   => 'admin.plugins.realtime',
        'middleware'   => ['web', 'auth', 'role:super-admin'],
        'layout'       => 'layouts.admin',
    ],
];
