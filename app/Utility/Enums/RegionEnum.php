<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum RegionEnum: string
{
    use CommonEnumTrait, EnumConcern {
        getName as getLabel;
    }

    case Hindu = 'hindu';
    case Muslim = 'muslim';
    case Sikh = 'sikh';
    case Christian = 'christian';
    case Buddhist = 'buddhist';
    case Parsi = 'parsi';
    case Jain = 'jain';
    case Others = 'others';

    public function getName($type = 1): string
    {
        return match (SchoolBoardEnum::tryFrom($type ?? 1)) {
            SchoolBoardEnum::GSHSEB => match ($this) {
                self::Hindu => 'હિન્દુ',
                self::Muslim => 'મુસ્લિમ',
                self::Sikh => 'શીખ',
                self::Christian => 'ખ્રિસ્તી',
                self::Buddhist => 'બૌદ્ધ',
                self::Parsi => 'પારસી',
                self::Jain => 'જૈન',
                self::Others => 'અન્ય',
            },
            default => $this->getLabel(),//@phpstan-ignore-line
        };
    }
}
