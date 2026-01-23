<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum MaritalStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Seperated = 'seperated';
    case NotSpecified = 'not_specified';

}
