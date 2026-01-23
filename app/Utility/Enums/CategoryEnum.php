<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum CategoryEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case General = 'general';

    case Sc = 'sc';

    case St = 'st';

    case Obc = 'obc';

    public function getName()
    {
        return str($this->value)->upper();
    }
}
