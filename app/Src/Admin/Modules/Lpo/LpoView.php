<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Lpo;

use App\Models\Order;
use App\Utility\Livewire\BaseViewComponent;

class LpoView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Order::class;
    }

    protected function getModelVariableName(): string
    {
        return 'order';
    }

    protected function getModuleName(): string
    {
        return 'LPO';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.lpo.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.lpo.view';
    }

    protected function getRelations(): array
    {
        return [
            'site',
            'siteManager',
            'transportManager',
            'products.category',
            'products.productImages',
            'customProducts.images',
        ];
    }

    protected function getIcon(): string
    {
        return 'file-invoice';
    }
}


