<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Unit;

use App\Models\Unit;
use App\Utility\Livewire\BaseViewComponent;

class UnitView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Unit::class;
    }

    protected function getModelVariableName(): string
    {
        return 'unit';
    }

    protected function getModuleName(): string
    {
        return 'Unit';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.units.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.units.view';
    }

    protected function getIcon(): string
    {
        return 'ruler';
    }
}


