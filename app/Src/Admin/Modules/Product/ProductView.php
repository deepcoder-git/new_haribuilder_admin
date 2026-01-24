<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Product;

use App\Models\Product;
use App\Utility\Livewire\BaseViewComponent;

class ProductView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Product::class;
    }

    protected function getModelVariableName(): string
    {
        return 'product';
    }

    protected function getModuleName(): string
    {
        return 'Product';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.products.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.products.view';
    }

    protected function getIcon(): string
    {
        return 'box';
    }

    protected function getRelations(): array
    {
        // productImages for gallery; materials for warehouse products table
        return ['category', 'productImages', 'materials.category', 'materials.productImages'];
    }
}
