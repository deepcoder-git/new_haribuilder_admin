<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum WastageTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case SiteWastage = 'site_wastage';
    case StoreWastage = 'store_wastage';

    public function getName(): string
    {
        return match($this) {
            self::SiteWastage => 'Site Wastage',
            self::StoreWastage => 'Store Wastage',
        };
    }
}