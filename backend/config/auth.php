<?php

return [
    'defaults' => [
        'guard'     => 'sanctum',
        'passwords' => 'users',
    ],
    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],
    'passwords' => [],
];
