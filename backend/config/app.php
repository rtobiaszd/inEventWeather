<?php

return [
    'name'            => env('APP_NAME', 'Event Weather'),
    'env'             => env('APP_ENV', 'production'),
    'debug'           => (bool) env('APP_DEBUG', false),
    'url'             => env('APP_URL', 'http://localhost:8080'),
    'key'             => env('APP_KEY'),
    'cipher'          => 'AES-256-CBC',
    'timezone'        => 'America/Sao_Paulo',
    'locale'          => 'pt_BR',
    'fallback_locale' => 'en',
    'faker_locale'    => 'pt_BR',
    'providers'       => \Illuminate\Support\ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        Laravel\Sanctum\SanctumServiceProvider::class,
    ])->toArray(),
    'aliases' => \Illuminate\Support\Facades\Facade::defaultAliases()->toArray(),
];
