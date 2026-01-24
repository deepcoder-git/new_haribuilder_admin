<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Material;

use App\Models\Material;
use App\Utility\Livewire\BaseViewComponent;

class MaterialView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Material::class;
    }

    protected function getModelVariableName(): string
    {
        return 'material';
    }

    protected function getModuleName(): string
    {
        return 'Material';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.materials.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.materials.view';
    }

    protected function getIcon(): string
    {
        return 'cubes';
    }

    protected function getRelations(): array
    {
        return ['category', 'productImages'];
    }
}


