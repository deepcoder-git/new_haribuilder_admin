<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Wastage;

use App\Models\Wastage;
use App\Utility\Livewire\BaseViewComponent;

class WastageView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Wastage::class;
    }

    protected function getModelVariableName(): string
    {
        return 'wastage';
    }

    protected function getModuleName(): string
    {
        return 'Wastage';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.wastages.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.wastages.view';
    }

    protected function getIcon(): string
    {
        return 'trash';
    }

    protected function getRelations(): array
    {
        return ['manager', 'site', 'order', 'products.category'];
    }
}

