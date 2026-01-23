<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum GenderEnum: string
{
    use CommonEnumTrait, EnumConcern {
        getName as getLabel;
    }

    case Male = 'male';

    case Female = 'female';

    public function getName($type = 1): string
    {
        return match (SchoolBoardEnum::tryFrom($type ?? 1)) {
            SchoolBoardEnum::GSHSEB => match ($this) {
                self::Male => 'પુરુષ',
                self::Female => 'સ્ત્રી',
            },
            default => $this->getLabel(),//@phpstan-ignore-line
        };
    }
}
