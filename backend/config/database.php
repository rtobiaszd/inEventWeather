<?php

return [
    'default' => 'mysql',

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', 'mysql'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'event_weather'),
            'username'  => env('DB_USERNAME', 'weather_user'),
            'password'  => env('DB_PASSWORD', 'weather_pass'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
            'options'   => [],
        ],
    ],

    'migrations' => [
        'table'                  => 'schema_migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),

        'default' => [
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'password'   => env('REDIS_PASSWORD', null),
            'port'       => env('REDIS_PORT', 6379),
            'database'   => 0,
            'persistent' => 1,
        ],

        'cache' => [
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'password'   => env('REDIS_PASSWORD', null),
            'port'       => env('REDIS_PORT', 6379),
            'database'   => 1,
            'persistent' => 1,
        ],
    ],
];
