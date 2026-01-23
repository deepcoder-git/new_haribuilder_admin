<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Supplier;

use App\Models\Supplier;
use App\Utility\Livewire\BaseViewComponent;

class SupplierView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Supplier::class;
    }

    protected function getModelVariableName(): string
    {
        return 'supplier';
    }

    protected function getModuleName(): string
    {
        return 'Supplier';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.suppliers.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.suppliers.view';
    }

    protected function getIcon(): string
    {
        return 'truck';
    }
}


