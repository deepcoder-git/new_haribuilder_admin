<?php

declare(strict_types=1);

namespace App\Utility\Enums\Fees;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum FeeGroupTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Hostel = 'hostel';
    case BusTransport = 'bus_transport';
    case School = 'school';
}
