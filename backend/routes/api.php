<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'data'    => ['status' => 'ok', 'timestamp' => now()->toIso8601String(), 'version' => '3.0.0'],
]));

// Públicas (com rate limit)
Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:3,60');

// Autenticadas (com rate limit global)
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    Route::get('/weather/search',      [WeatherController::class, 'search']);
    Route::get('/weather/forecast',    [WeatherController::class, 'forecast']);
    Route::get('/weather/air-quality', [WeatherController::class, 'airQuality']);

    Route::get('/events/upcoming-weather', [EventController::class, 'upcomingWeather']);
    Route::get('/events/risk-alerts', [EventController::class, 'riskAlerts']);
    Route::get('/events/financial-insights', [EventController::class, 'financialInsights']);
    Route::apiResource('events', EventController::class);

    Route::get('/weather/best-dates', [WeatherController::class, 'bestDates']);

    Route::get('/history', [HistoryController::class, 'index']);

    Route::get('/favorites',         [FavoriteController::class, 'index']);
    Route::post('/favorites',        [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

    // Tipos de evento — leitura para todos, escrita só para admin
    Route::get('/event-types', [EventTypeController::class, 'index']);

    // Admin
    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->except(['index'])->only(['store', 'show', 'update', 'destroy']);
        Route::get('/users',                          [UserController::class, 'index']);
        Route::patch('/users/{id}/permissions',       [UserController::class, 'updatePermissions']);

        Route::post('/event-types',         [EventTypeController::class, 'store']);
        Route::put('/event-types/{id}',     [EventTypeController::class, 'update']);
        Route::delete('/event-types/{id}',  [EventTypeController::class, 'destroy']);
    });
});
