<?php

declare(strict_types=1);

namespace App\Utility\Enums\Hostel;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum HostelTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Boys = 'boys';
    case Girls = 'girls';
    case Combine = 'combine';
}
