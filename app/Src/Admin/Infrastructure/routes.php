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
use Illuminate\Support\Facades\Route;

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
    });
});
