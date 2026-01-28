<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Return;

use App\Models\OrderReturn;
use App\Utility\Livewire\BaseViewComponent;

class ReturnView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return OrderReturn::class;
    }

    protected function getModelVariableName(): string
    {
        return 'return';
    }

    protected function getModuleName(): string
    {
        return 'Return';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.returns.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.returns.view';
    }

    protected function getIcon(): string
    {
        return 'rotate-left';
    }

    protected function getRelations(): array
    {
        return ['manager', 'site', 'order', 'items.product'];
    }
}

