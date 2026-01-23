<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum PriorityEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}

