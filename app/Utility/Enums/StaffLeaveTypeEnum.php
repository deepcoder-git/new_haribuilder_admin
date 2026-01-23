<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum StaffLeaveTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Sick = 'sick';
    case Casual = 'casual';
    case Annual = 'annual';

}
