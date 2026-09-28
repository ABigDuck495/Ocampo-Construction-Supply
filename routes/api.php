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

    // ============================================================
    // DRIVER APP ROUTES
    // ============================================================
    Route::prefix('driver')->group(function () {

        // Get only deliveries assigned to the logged-in driver
        Route::get(
            '/deliveries',
            [DriverDeliveryController::class, 'index']
        );

        // Get one specific assigned delivery
        Route::get(
            '/deliveries/{dispatch}',
            [DriverDeliveryController::class, 'show']
        );

        // Driver accepts a Pending delivery
        // Pending -> On Route
        Route::post(
            '/deliveries/{dispatch}/accept',
            [DriverDeliveryController::class, 'accept']
        );

        // Get the delivery receipt
        Route::get(
            '/deliveries/{dispatch}/receipt',
            [DriverDeliveryController::class, 'receipt']
        );

        // Driver marks the delivery as Delivered
        // On Route -> Delivered
        Route::post(
            '/deliveries/{dispatch}/deliver',
            [DriverDeliveryController::class, 'deliver']
        );
    });
});