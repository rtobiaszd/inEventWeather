<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TaskController;
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

// Públicas — página do evento e inscrição (sem auth)
Route::get('/events/{id}/public',   [RegistrationController::class, 'publicEvent'])->middleware('throttle:60,1');
Route::post('/events/{id}/register', [RegistrationController::class, 'register'])->middleware('throttle:10,1');

// Autenticadas (com rate limit global)
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/auth/logout',       [AuthController::class, 'logout']);
    Route::get('/auth/me',            [AuthController::class, 'me']);
    Route::put('/auth/profile',       [AuthController::class, 'updateProfile']);

    Route::get('/weather/search',      [WeatherController::class, 'search']);
    Route::get('/weather/forecast',    [WeatherController::class, 'forecast']);
    Route::get('/weather/air-quality', [WeatherController::class, 'airQuality']);

    Route::get('/events/upcoming-weather', [EventController::class, 'upcomingWeather']);
    Route::get('/events/risk-alerts', [EventController::class, 'riskAlerts']);
    Route::get('/events/financial-insights', [EventController::class, 'financialInsights']);
    Route::post('/events/{id}/duplicate', [EventController::class, 'duplicate']);
    Route::apiResource('events', EventController::class);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/summary',              [ReportController::class, 'summary']);
        Route::get('/financial-trends',     [ReportController::class, 'financialTrends']);
        Route::get('/city-performance',     [ReportController::class, 'cityPerformance']);
        Route::get('/type-performance',     [ReportController::class, 'typePerformance']);
        Route::get('/session-analytics',    [ReportController::class, 'sessionAnalytics']);
        Route::get('/export/csv',           [ReportController::class, 'exportCsv']);
    });

    Route::prefix('events/{eventId}')->group(function () {
        Route::apiResource('sessions', SessionController::class);
        Route::apiResource('tasks', TaskController::class)->except(['show']);
        Route::patch('tasks/{id}/status', [TaskController::class, 'updateStatus']);
        Route::put('tasks/reorder', [TaskController::class, 'reorder']);

        Route::get('/registrations',                          [RegistrationController::class, 'index']);
        Route::post('/registrations',                         [RegistrationController::class, 'register']);
        Route::get('/registrations/{id}',                     [RegistrationController::class, 'show']);
        Route::put('/registrations/{id}',                     [RegistrationController::class, 'update']);
        Route::post('/registrations/{id}/checkin',            [RegistrationController::class, 'checkIn']);
        Route::post('/registrations/{id}/checkin/undo',       [RegistrationController::class, 'undoCheckIn']);
        Route::delete('/registrations/{id}',                  [RegistrationController::class, 'destroy']);
    });

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
