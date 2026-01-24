<?php
 
declare(strict_types=1);
 
use App\Src\Api\Modules\Auth\LoginController;
use App\Src\Api\Modules\Profile\ProfileController;
use App\Src\Api\Modules\DashboardController;
use Illuminate\Support\Facades\Route;
 

 
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
 
          
        });
    });
});