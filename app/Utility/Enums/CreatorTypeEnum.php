<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum CreatorTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case StoreManager = 'store_manager';
    case SiteManager = 'site_manager';
    case Other = 'other';

    public function getName(): string
    {
        return match($this) {
            self::StoreManager => 'Store Manager',
            self::SiteManager => 'Site Manager',
            self::Other => 'Other',
        };
    }
}
