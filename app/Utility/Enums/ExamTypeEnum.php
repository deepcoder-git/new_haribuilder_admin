<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum ExamTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Theory = 'theory';
    case Practical = 'practical';

}
