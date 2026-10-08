<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DiningTableController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\ModifierGroupController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TaxClassController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Every route runs through 'tenant' (resolves + scopes the tenant), except
| the payment gateway webhook, which resolves its own tenant from the
| payment row (see StripeWebhookController) and must be reachable without
| any of our own headers, since the gateway is the one calling it.
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

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
    // tenant.verify MUST come right after auth:sanctum: it's what stops a
    // valid token for Tenant A being combined with a forged
    // X-Tenant-Id: tenant-b header to read Tenant B's data (see
    // ResolveTenant + EnsureUserBelongsToTenant docblocks).
    Route::middleware(['auth:sanctum', 'tenant.verify'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/devices/heartbeat', [DeviceController::class, 'heartbeat']);

        Route::get('/tables', [DiningTableController::class, 'index']);
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);

        Route::middleware('permission:orders.manage')->group(function () {
            Route::post('/orders/{order}/items', [OrderController::class, 'addItems']);
            Route::patch('/orders/{order}/items/{orderItem}/void', [OrderController::class, 'voidItem']);
            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        });

        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->middleware('permission:orders.refund');

        // ---- Manager/owner surface -----------------------------------------------
        Route::middleware('permission:menu.edit')->group(function () {
            Route::apiResource('menu/categories', MenuCategoryController::class)->except(['show']);
            Route::post('/menu/items', [MenuController::class, 'store']);
            Route::put('/menu/items/{menuItem}', [MenuController::class, 'update']);
            Route::delete('/menu/items/{menuItem}', [MenuController::class, 'destroy']);

            Route::get('/modifier-groups', [ModifierGroupController::class, 'index']);
            Route::post('/modifier-groups', [ModifierGroupController::class, 'store']);
            Route::post('/modifier-groups/{modifierGroup}/attach', [ModifierGroupController::class, 'attachToItem']);
            Route::post('/modifier-groups/{modifierGroup}/modifiers', [ModifierGroupController::class, 'addModifier']);
            Route::delete('/modifier-groups/{modifierGroup}/modifiers/{modifier}', [ModifierGroupController::class, 'removeModifier']);

            Route::get('/tax-classes', [TaxClassController::class, 'index']);
            Route::post('/tax-classes', [TaxClassController::class, 'store']);
            Route::put('/tax-classes/{taxClass}', [TaxClassController::class, 'update']);

            Route::get('/discounts', [DiscountController::class, 'index']);
            Route::post('/discounts', [DiscountController::class, 'store']);
            Route::put('/discounts/{discount}', [DiscountController::class, 'update']);
            Route::delete('/discounts/{discount}', [DiscountController::class, 'destroy']);
        });
        Route::patch('/menu/items/{menuItem}/toggle-availability', [MenuController::class, 'toggleAvailability'])
            ->middleware('permission:menu.86');

        Route::middleware('permission:inventory.manage')->group(function () {
            Route::get('/inventory', [InventoryController::class, 'index']);
            Route::post('/inventory', [InventoryController::class, 'store']);
            Route::post('/inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjust']);
            Route::get('/inventory/{inventoryItem}/movements', [InventoryController::class, 'movements']);

            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::post('/suppliers', [SupplierController::class, 'store']);
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
            Route::post('/purchase-orders/{purchaseOrder}/mark-ordered', [PurchaseOrderController::class, 'markOrdered']);
            Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
        });

        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/analytics/top-items', [AnalyticsController::class, 'topItems']);
            Route::get('/analytics/peak-hours', [AnalyticsController::class, 'peakHours']);
        });

        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/devices/pairing-code', [DeviceController::class, 'createPairingCode']);
            Route::get('/staff', [StaffController::class, 'index']);
            Route::post('/staff', [StaffController::class, 'store']);
            Route::put('/staff/{user}', [StaffController::class, 'update']);
            Route::delete('/staff/{user}', [StaffController::class, 'destroy']);
            Route::get('/roles', [StaffController::class, 'roles']);

            Route::get('/branches', [BranchController::class, 'index']);
            Route::post('/branches', [BranchController::class, 'store']);
            Route::put('/branches/{branch}', [BranchController::class, 'update']);
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

            Route::post('/tables', [DiningTableController::class, 'store']);
            Route::patch('/tables/{diningTable}/status', [DiningTableController::class, 'updateStatus']);
            Route::delete('/tables/{diningTable}', [DiningTableController::class, 'destroy']);
        });
    });
});
