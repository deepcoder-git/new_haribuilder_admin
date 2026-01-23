<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Site;

use App\Models\Site;
use App\Utility\Livewire\BaseViewComponent;

class SiteView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Site::class;
    }

    protected function getModelVariableName(): string
    {
        return 'site';
    }

    protected function getModuleName(): string
    {
        return 'Site';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.sites.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.sites.view';
    }

    protected function getRelations(): array
    {
        return ['siteManager'];
    }

    protected function getIcon(): string
    {
        return 'building';
    }
}


