<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Every route runs through 'tenant' (resolves + scopes the tenant).
| 'auth:sanctum' additionally requires a logged-in staff user or customer.
| 'permission:x' additionally requires that specific permission.
|--------------------------------------------------------------------------
*/

Route::middleware('tenant')->group(function () {

    // ---- Public / unauthenticated -------------------------------------------------
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/pin-login', [AuthController::class, 'pinLogin']);
    Route::post('/devices/claim', [DeviceController::class, 'claim']);
    Route::get('/menu', [MenuController::class, 'index']);
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'trackByNumber']);

    // Public ordering (guest checkout allowed — customer_id is optional in CreateOrderRequest)
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/payment-intent', [PaymentController::class, 'createIntent']);
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
    Route::post('/reservations', [ReservationController::class, 'store']);

    // ---- Authenticated (staff or customer bearer token) ----------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/devices/heartbeat', [DeviceController::class, 'heartbeat']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->middleware('permission:orders.manage');
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->middleware('permission:orders.refund');

        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);

        // ---- Manager/owner surface -----------------------------------------------
        Route::middleware('permission:menu.edit')->group(function () {
            Route::post('/menu/items', [MenuController::class, 'store']);
            Route::put('/menu/items/{menuItem}', [MenuController::class, 'update']);
            Route::delete('/menu/items/{menuItem}', [MenuController::class, 'destroy']);
        });
        Route::patch('/menu/items/{menuItem}/toggle-availability', [MenuController::class, 'toggleAvailability'])
            ->middleware('permission:menu.86');

        Route::middleware('permission:inventory.manage')->group(function () {
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::post('/inventory', [InventoryController::class, 'store']);
            Route::post('/inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjust']);
            Route::get('/inventory/{inventoryItem}/movements', [InventoryController::class, 'movements']);
        });

        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/analytics/top-items', [AnalyticsController::class, 'topItems']);
            Route::get('/analytics/peak-hours', [AnalyticsController::class, 'peakHours']);
        });

        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/devices/pairing-code', [DeviceController::class, 'createPairingCode']);
        });
    });
});
