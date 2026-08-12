<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/transactions/{id}/receipt', [TransactionController::class, 'receipt']);
});