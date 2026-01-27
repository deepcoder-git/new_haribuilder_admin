<?php
 
declare(strict_types=1);
 
use App\Src\Api\Modules\Auth\LoginController;
use App\Src\Api\Modules\Profile\ProfileController;
use App\Src\Api\Modules\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Src\Api\Modules\SiteManagement\OrderController;
use App\Src\Api\Modules\StoreManagement\StoreOrderController;
use App\Src\Api\Modules\TransportManagement\TransportManagerController;
 
Route::middleware(['api'])->group(function () {
    Route::group(['prefix' => 'api/v1', 'as' => 'api.v1.'], function () {
        Route::post('auth/login', [LoginController::class, 'login'])->name('login');
        Route::post('auth/forgot-password', [LoginController::class, 'forgotPassword'])->name('forgot-password');
 
        Route::middleware(['auth:sanctum', 'block.admin.api'])->group(function () {
            Route::get('app/dashboard/{role}/{site_id?}', [DashboardController::class, 'dashboard'])->name('order.requests');
            Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
                Route::get('me', [ProfileController::class, 'me'])->name('me');
                Route::post('editProfile', [ProfileController::class, 'editProfile'])->name('editProfile');
                Route::post('logout', [ProfileController::class, 'logout'])->name('logout');
                Route::patch('change-password', [ProfileController::class, 'changePassword'])->name('changePassword');
            }); 

            Route::group(['prefix' => 'sites', 'as' => 'sites.'], function () {
                // Product endpoints
                Route::get('products/categories-sites-list', [OrderController::class, 'categorySiteList'])->name('categories-sites');
                Route::get('products/metadata/{store_id?}', [OrderController::class, 'productList'])->name('products');
                Route::get('products/{category_id}/metadata', [OrderController::class, 'metadata'])->name('metadata');
                Route::get('my-site', [OrderController::class, 'mySiteList'])->name('my-sites');
                
                // Order management endpoints
                Route::post('order-request/form-data', [OrderController::class, 'createOrderWithFormData'])->name('order.create-form-data');
                Route::match(['PUT', 'POST'], 'order-request/form-data/update', [OrderController::class, 'updateOrderWithFormData'])->name('order.update-form-data');
                Route::delete('order-request/form-data/products', [OrderController::class, 'deleteOrderProductsWithFormData'])->name('order.delete-products-form-data');
                Route::get('order-requests/{site_id}', [OrderController::class, 'orderRequestList'])->name('order.requests');
                Route::get('order-request/{order_id}', [OrderController::class, 'orderRequestDetails'])->name('order.request-details');
                Route::delete('order-request/{order_id}/product', [OrderController::class, 'orderProductDelete'])->name('order.product-delete');
                Route::post('order-request/cancel', [OrderController::class, 'orderRequestCancel'])->name('order.request-cancel');
                Route::patch('order/products/status/update', [OrderController::class, 'updateOrderProductsStatus'])->name('order.products-status-update');
            });


            Route::group(['prefix' => 'store', 'as' => 'store.'], function () {
                // Product endpoints
                Route::get('products/metadata/{store_id?}', [OrderController::class, 'metadata'])->name('products');
                
                // Order management endpoints
                Route::get('get-orders', [StoreOrderController::class, 'getOrders'])->name('get-orders');
                Route::get('get-order-details/{order_id}', [StoreOrderController::class, 'getOrderDetails'])->name('order.details');
                Route::post('order-status-update', [StoreOrderController::class, 'updateStatus'])->name('order.status-update');
                Route::delete('order-delete/{order_id}', [StoreOrderController::class, 'deleteOrder'])->name('order-delete');
                
                // Stock management
                Route::get('stock', [StoreOrderController::class, 'stockList'])->name('stock');
                
                // Custom product endpoints
                Route::match(['PUT', 'POST'], 'order-request/custom-product/update', [OrderController::class, 'updateCustomProduct'])->name('order.update-custom-product');
                Route::post('order-request/custom-product/store', [OrderController::class, 'storeCustomProduct'])->name('order.store-custom-product');
                Route::post('order-request/custom-product/count', [StoreOrderController::class, 'calculateCustomProduct'])->name('order.calculate-custom-product');
                
                // Transport assignment
                Route::put('order-request/assign-transport', [OrderController::class, 'assignTransportManager'])->name('order.assign-transport-manager');
            });

            Route::group(['prefix' => 'transport', 'as' => 'transport.'], function () {
                Route::get('get-orders', [TransportManagerController::class, 'getOrders'])->name(name: 'get-orders');
                Route::get('get-order-details/{order_id}', [TransportManagerController::class, 'getOrderDetails'])->name('order.details');
                Route::post('order-request/order-action', [TransportManagerController::class, 'updateDeliveryStatus'])->name('update-delivery-status');
            });
        });
    });
});