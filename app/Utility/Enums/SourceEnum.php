<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum SourceEnum: string
{
    use CommonEnumTrait, EnumConcern;
    case Advertidement = 'advertidement';
    case FrontOffice = 'front office';
   
}
