<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum EventEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Event = 'event';

    case Holiday = 'holiday';

    case Exam = 'exam';

    case PTM = 'ptm';

    public function getName()
    {
        return str($this->value)->upper();
    }
}
