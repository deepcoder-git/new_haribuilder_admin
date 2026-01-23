<?php

declare(strict_types=1);

namespace App\Utility\Enums\Class;

use EmreYarligan\EnumConcern\EnumConcern;

enum ClassRoomTypeEnum: string
{
    use EnumConcern;

    case Group = 'group';
    case Section = 'section';
}
