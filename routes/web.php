<?php

use App\Src\Admin\Modules\Return\ReturnDatatable;
use App\Src\Admin\Modules\Return\ReturnForm;
use App\Src\Admin\Modules\Return\ReturnView;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:moderator', 'moderator.active'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('returns', ReturnDatatable::class)->name('returns.index');
        Route::get('returns/create', ReturnForm::class)->name('returns.create');
        Route::get('returns/{id}/edit', ReturnForm::class)->name('returns.edit');
        Route::get('returns/{id}', ReturnView::class)->name('returns.view');
    });
