<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum BloodGroupEnum: string
{
    use CommonEnumTrait, EnumConcern;
    case APositive = 'A+';
    case ANegative = 'A-';
    case BPositive = 'B+';
    case BNegative = 'B-';
    case OPositive = 'o+';
    case ONegative = 'o-';
    case ABPositive = 'AB+';
    case ABNegative = 'AB-';
}
