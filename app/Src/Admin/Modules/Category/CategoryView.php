<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Category;

use App\Models\Category;
use App\Utility\Livewire\BaseViewComponent;

class CategoryView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Category::class;
    }

    protected function getModelVariableName(): string
    {
        return 'category';
    }

    protected function getModuleName(): string
    {
        return 'Category';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.categories.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.categories.view';
    }

    protected function getIcon(): string
    {
        return 'tags';
    }
}


