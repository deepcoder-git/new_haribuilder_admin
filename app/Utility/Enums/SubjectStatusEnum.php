<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum SubjectStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Regular = 'regular';
    case Additional = 'additional';

}
