<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum SchoolBoardEnum: int
{
    use CommonEnumTrait, EnumConcern;

    case GSHSEB = 1; //Gujarat Secondary and Higher Secondary Education Board
    case CBSE = 2; //Central Board of Secondary Education
    case GU = 3; //Gujarat University

    public function getName(): string
    {
        return match ($this) {
            self::CBSE => 'English',
            self::GSHSEB => 'Gujarati',
            self::GU => 'SGJ',
        };
    }

    //    public function getShortCode(): string
    //    {
    //        return match ($this) {
    //            self::GSHSEB => 'GSHSEB',
    //            self::CBSE => 'CBSE',
    //            self::GU => 'GU',
    //        };
    //    }
}
