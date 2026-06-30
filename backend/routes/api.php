<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'success' => true,
    'data'    => ['status' => 'ok', 'timestamp' => now()->toIso8601String(), 'version' => '2.0.0'],
]));

// Públicas
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Autenticadas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    Route::get('/weather/search',      [WeatherController::class, 'search']);
    Route::get('/weather/forecast',    [WeatherController::class, 'forecast']);
    Route::get('/weather/air-quality', [WeatherController::class, 'airQuality']);

    Route::apiResource('events', EventController::class);

    Route::get('/history', [HistoryController::class, 'index']);

    Route::get('/favorites',         [FavoriteController::class, 'index']);
    Route::post('/favorites',        [FavoriteController::class, 'store']);
    Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);
});
