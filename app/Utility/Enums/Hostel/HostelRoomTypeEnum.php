<?php

declare(strict_types=1);

namespace App\Utility\Enums\Hostel;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum HostelRoomTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;
    case Deluxe = 'deluxe';

    case Regular = 'regular';
    case Premium = 'premium';

}
