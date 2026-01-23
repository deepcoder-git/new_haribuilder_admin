<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum LeaveStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Pending = 'Pending';
    case Approved = 'Approved';
    case Rejected = 'Rejected';

    public function isPending(): int
    {
        return $this === LeaveStatusEnum::Pending ? Pending : Approved;
    }
}
