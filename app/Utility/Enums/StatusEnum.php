<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum StatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Active = 'active';

    case InActive = 'in_active';

    public function getOppositeStatus(): string
    {
        return match ($this) {
            self::Active => self::InActive->value,
            self::InActive => self::Active->value,
        };
    }

    public function isActive(): int
    {
        return $this === StatusEnum::Active ? 1 : 0;
    }
}
