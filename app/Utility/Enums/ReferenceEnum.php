<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum ReferenceEnum: string
{
    use CommonEnumTrait, EnumConcern;
    case Staff = 'staff';
    case Parent = 'parent';
    case Student = 'student';
    case Self = 'self';
}
