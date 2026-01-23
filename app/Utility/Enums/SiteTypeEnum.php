<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum SiteTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Commercial = 'commercial';
    case Industrial = 'industrial';
    case Structural = 'structural';
    case Residential = 'residential';
}

