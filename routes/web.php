<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\DispatchDriverController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TruckController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController as LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('deliveries.index');
    }

    return redirect()->route('login');
});

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
// Provide GET for /auth/login to avoid MethodNotAllowed for GET requests to that URI
Route::get('auth/login', [LoginController::class, 'showLoginForm']);

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::middleware(['auth'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::middleware(['role:Admin,Staff'])->group(function () {
        Route::resource('products', ProductController::class);
        Route::resource('inventories', InventoryController::class);
        Route::resource('orders', OrderController::class);
        Route::resource('order-items', OrderItemController::class);
        Route::resource('dispatches', DispatchController::class);
        Route::resource('deliveries', DeliveryController::class);
        Route::resource('dispatch-drivers', DispatchDriverController::class);
        Route::resource('drivers', DriverController::class);
        Route::resource('trucks', TruckController::class);
        Route::resource('transactions', TransactionController::class);
        Route::resource('reports', ReportController::class);
        // Route::get('/pos', function () {
        //     return view('pos');
        // })->name('pos.index');

        Route::get('products/search', [ProductController::class, 'search']);
        Route::get('products/top-selling', [ProductController::class, 'topSelling']);
        Route::post('inventories/{inventory}/adjust', [InventoryController::class, 'adjust']);
        Route::get('inventories/low-stock', [InventoryController::class, 'lowStock']);
        Route::get('inventories/out-of-stock', [InventoryController::class, 'outOfStock']);
        Route::post('inventories/check-availability', [InventoryController::class, 'checkAvailability']);
        Route::post('orders/{order}/update-status', [OrderController::class, 'updateStatus']);
        Route::post('orders/{order}/sync-status', [OrderController::class, 'syncStatus']);
        Route::get('order-items/{orderItem}/remaining', [OrderItemController::class, 'remaining']);
        Route::post('order-items/{orderItem}/update-status', [OrderItemController::class, 'updateStatus']);
        Route::get('dispatches/active', [DispatchController::class, 'active']);
        Route::post('dispatches/{dispatch}/cancel', [DispatchController::class, 'cancel']);
        Route::get('drivers/available', [DriverController::class, 'available']);
        Route::get('drivers/{driver}/summary', [DriverController::class, 'summary']);
        Route::get('trucks/available', [TruckController::class, 'available']);
        Route::get('trucks/{truck}/utilization', [TruckController::class, 'utilization']);
        Route::post('transactions/pos-sale', [TransactionController::class, 'posSale']);
        Route::get('transactions/daily-total', [TransactionController::class, 'dailyTotal']);
        Route::get('transactions/by-payment-method', [TransactionController::class, 'byPaymentMethod']);
        Route::post('reports/generate-now', [ReportController::class, 'generateNow']);
        Route::get('reports/for-date', [ReportController::class, 'forDate']);
        Route::get('reports/trend', [ReportController::class, 'trend']);
        Route::post('dispatch-drivers/assign', [DispatchDriverController::class, 'assign']);
        Route::post('dispatch-drivers/swap', [DispatchDriverController::class, 'swap']);
        Route::get('dispatch-drivers/history/{driver}', [DispatchDriverController::class, 'history']);
        Route::get('deliveries/failed', [DeliveryController::class, 'failedDeliveries']);
    });

    Route::middleware(['role:Admin'])->group(function () {
        Route::resource('users', UserController::class);
    });
});
