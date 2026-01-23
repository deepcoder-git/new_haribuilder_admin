<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum WhatsappDefaultEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Hostel = 'hostel';
    case School = 'school';

    public static function labels(): array
    {
        return [
            self::Hostel->value => 'For Hostel',
            self::School->value    => 'For School',
        ];
    }
}
