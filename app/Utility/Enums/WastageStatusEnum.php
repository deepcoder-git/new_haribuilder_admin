<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum WastageStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getName(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
