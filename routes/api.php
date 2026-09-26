<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DriverDeliveryController;
use Illuminate\Support\Facades\Route;

// Public route — no token required, since the user doesn't have one yet
Route::post('/login', [AuthController::class, 'login']);

// Everything below requires a valid Sanctum token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/transactions/{id}/receipt', [TransactionController::class, 'receipt']);

    Route::prefix('driver')->group(function () {
        Route::get('/deliveries', [DriverDeliveryController::class, 'index']);
        Route::get('/deliveries/{dispatch}', [DriverDeliveryController::class, 'show']);
        Route::post('/deliveries/{dispatch}/accept', [DriverDeliveryController::class, 'accept']);
        Route::get('/deliveries/{dispatch}/receipt', [DriverDeliveryController::class, 'receipt']);
    });
});