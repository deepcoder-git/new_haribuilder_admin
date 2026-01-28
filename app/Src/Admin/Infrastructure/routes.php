<?php

declare(strict_types=1);

use App\Src\Admin\Modules\Auth\ForgotPassword;
use App\Src\Admin\Modules\Auth\Login;
use App\Src\Admin\Modules\Auth\Register;
use App\Src\Admin\Modules\Auth\ResetPassword;
use App\Src\Admin\Modules\Profile\ChangePassword;
use App\Src\Admin\Modules\Profile\Dashboard;
use App\Src\Admin\Modules\Profile\Logout;
use App\Src\Admin\Modules\Profile\Profile;
use App\Src\Admin\Modules\Notification\NotificationCenter;
use App\Src\Admin\Modules\RolePermission\RoleDatatable;
use App\Src\Admin\Modules\RolePermission\RoleView;
use App\Src\Admin\Modules\User\UserDatatable;
use App\Src\Admin\Modules\User\UserForm;
use App\Src\Admin\Modules\User\UserView;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Routes for admin panel with authentication and dashboard only
|
*/


Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => 'web'], function () {
    // Authentication routes (guest only)
    Route::group(['as' => 'auth.', 'middleware' => ['guest:moderator']], function () {
        Route::get('login', Login::class)->name('login');
        Route::get('register', Register::class)->name('register');
        Route::get('forgot-password', ForgotPassword::class)->name('forgot-password');
        Route::get('reset-password', ResetPassword::class)->name('reset-password');
    });

    // Protected routes (authenticated users only)
    Route::group(['middleware' => ['auth:moderator', 'moderator.active']], function () {
        // Dashboard
        Route::get('/', Dashboard::class)->name('index');
        Route::get('dashboard', Dashboard::class)->name('dashboard');
        Route::post('logout', Logout::class)->name('logout');

        // Profile management
        Route::group(['prefix' => 'profile', 'as' => 'profile.'], function () {
            Route::get('/', Profile::class)->name('index');
            Route::get('change-password', ChangePassword::class)->name('change-password');
        });

       
        // Notifications
        Route::get('notifications', NotificationCenter::class)->name('notifications.index');

      

        // Super Admin Only Routes
        Route::middleware(['role:super_admin'])->group(function () {
            // User Management
            Route::get('users', UserDatatable::class)->name('users.index');
            Route::get('users/create', UserForm::class)->name('users.create');
            Route::get('users/{id}/edit', UserForm::class)->name('users.edit');
            Route::get('users/{id}/view', UserView::class)->name('users.view');

            // Role & Permission Management
            Route::get('role-permissions', RoleDatatable::class)->name('role-permissions.index');
            Route::get('role-permissions/{role}/view', RoleView::class)->name('role-permissions.view');
        });

        // Settings / Site management routes
        Route::get('sites', App\Src\Admin\Modules\Site\SiteDatatable::class)->name('sites.index');
        Route::get('sites/create', App\Src\Admin\Modules\Site\SiteForm::class)->name('sites.create');
        Route::get('sites/{id}/edit', App\Src\Admin\Modules\Site\SiteForm::class)->name('sites.edit');
        Route::get('sites/{id}/view', App\Src\Admin\Modules\Site\SiteView::class)->name('sites.view');

        // Supplier management routes
        Route::get('suppliers', App\Src\Admin\Modules\Supplier\SupplierDatatable::class)->name('suppliers.index');
        Route::get('suppliers/create', App\Src\Admin\Modules\Supplier\SupplierForm::class)->name('suppliers.create');
        Route::get('suppliers/{id}/edit', App\Src\Admin\Modules\Supplier\SupplierForm::class)->name('suppliers.edit');
        Route::get('suppliers/{id}/view', App\Src\Admin\Modules\Supplier\SupplierView::class)->name('suppliers.view');

        // Product purchase management routes
        Route::get('product-purchases', App\Src\Admin\Modules\ProductPurchase\ProductPurchaseDatatable::class)->name('product-purchases.index');
        Route::get('product-purchases/create', App\Src\Admin\Modules\ProductPurchase\ProductPurchaseForm::class)->name('product-purchases.create');
        Route::get('product-purchases/{id}/edit', App\Src\Admin\Modules\ProductPurchase\ProductPurchaseForm::class)->name('product-purchases.edit');
        Route::get('product-purchases/{id}/view', App\Src\Admin\Modules\ProductPurchase\ProductPurchaseView::class)->name('product-purchases.view');

        // Reports
        Route::get('reports/stock-report', App\Src\Admin\Modules\Report\StockReport::class)->name('reports.stock-report');
        Route::get('reports/low-stock-report', App\Src\Admin\Modules\Report\LowStockReport::class)->name('reports.low-stock-report');
        Route::get('stock/entries', App\Src\Admin\Modules\Report\StockEntries::class)->name('stock.entries');

        // Unit management routes
        Route::get('units', App\Src\Admin\Modules\Unit\UnitDatatable::class)->name('units.index');
        Route::get('units/create', App\Src\Admin\Modules\Unit\UnitForm::class)->name('units.create');
        Route::get('units/{id}/edit', App\Src\Admin\Modules\Unit\UnitForm::class)->name('units.edit');
        Route::get('units/{id}/view', App\Src\Admin\Modules\Unit\UnitView::class)->name('units.view');

        // Category management routes
        Route::get('categories', App\Src\Admin\Modules\Category\CategoryDatatable::class)->name('categories.index');
        Route::get('categories/create', App\Src\Admin\Modules\Category\CategoryForm::class)->name('categories.create');
        Route::get('categories/{id}/edit', App\Src\Admin\Modules\Category\CategoryForm::class)->name('categories.edit');
        Route::get('categories/{id}/view', App\Src\Admin\Modules\Category\CategoryView::class)->name('categories.view');

        // Product management routes
        Route::get('products', App\Src\Admin\Modules\Product\ProductDatatable::class)->name('products.index');
        Route::get('products/create', App\Src\Admin\Modules\Product\ProductForm::class)->name('products.create');
        Route::get('products/{id}/edit', App\Src\Admin\Modules\Product\ProductForm::class)->name('products.edit');
        Route::get('products/{id}/view', App\Src\Admin\Modules\Product\ProductView::class)->name('products.view');

        // Orders
        Route::get('orders', App\Src\Admin\Modules\Order\OrderDatatable::class)->name('orders.index');
        Route::get('orders/create', App\Src\Admin\Modules\Order\OrderForm::class)->name('orders.create');
        Route::get('orders/{id}/edit', App\Src\Admin\Modules\Order\OrderForm::class)->name('orders.edit');
        Route::get('orders/{id}/view', App\Src\Admin\Modules\Order\OrderView::class)->name('orders.view');

        // Wastages
        Route::get('wastages', App\Src\Admin\Modules\Wastage\WastageDatatable::class)->name('wastages.index');
        Route::get('wastages/create', App\Src\Admin\Modules\Wastage\WastageForm::class)->name('wastages.create');
        Route::get('wastages/{id}/edit', App\Src\Admin\Modules\Wastage\WastageForm::class)->name('wastages.edit');
        Route::get('wastages/{id}/view', App\Src\Admin\Modules\Wastage\WastageView::class)->name('wastages.view');

        // LPOs
        Route::get('lpo', App\Src\Admin\Modules\Lpo\LpoDatatable::class)->name('lpo.index');
        Route::get('lpo/create', App\Src\Admin\Modules\Lpo\LpoForm::class)->name('lpo.create');
        Route::get('lpo/{id}/edit', App\Src\Admin\Modules\Lpo\LpoForm::class)->name('lpo.edit');
        Route::get('lpo/{id}/view', App\Src\Admin\Modules\Lpo\LpoView::class)->name('lpo.view');

        // Material management routes
        Route::get('materials', App\Src\Admin\Modules\Material\MaterialDatatable::class)->name('materials.index');
        Route::get('materials/create', App\Src\Admin\Modules\Material\MaterialForm::class)->name('materials.create');
        Route::get('materials/{id}/edit', App\Src\Admin\Modules\Material\MaterialForm::class)->name('materials.edit');
        Route::get('materials/{id}/view', App\Src\Admin\Modules\Material\MaterialView::class)->name('materials.view');
    });
    
});
