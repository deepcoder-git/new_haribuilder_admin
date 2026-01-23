<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum AttendenceStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Present = 'present';
    case Absent = 'absent';

}
