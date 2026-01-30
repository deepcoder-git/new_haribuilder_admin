<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Order;

use App\Models\Order;
use App\Utility\Livewire\BaseViewComponent;

class OrderView extends BaseViewComponent
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
        return 'Order';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.orders.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.orders.view';
    }

    protected function getRelations(): array
    {
        return [
            'site', 
            'siteManager', 
            'transportManager', 
            'products.productImages',
            'products.category',
            'products.materials.category',
            'products.materials.productImages',
            'customProducts.images', 
        ];
    }

    protected function getIcon(): string
    {
        return 'file-invoice';
    }
}

