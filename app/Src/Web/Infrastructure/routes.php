<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes For Admin
|--------------------------------------------------------------------------
|
*/

Route::group(['as' => 'web.', 'middleware' => 'web'], function () {
    Route::redirect('/', 'admin/login');
    Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');
    Route::view('/terms-and-conditions', 'terms-and-conditions')->name('terms-and-conditions');
});
