<?php

declare(strict_types=1);

namespace App\Utility\Enums\Fees;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum FeeGroupStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    case Cancelled = 'cancelled';

    public function getBadge(): string
    {
        $string = match ($this) {
            self::Pending => [
                'bg-info text-white',
                'Not Paid',
            ],
            self::Paid => [
                'bg-success text-white',
                $this->getName(),
            ],
            self::Cancelled => [
                'bg-warning text-black',
                $this->getName(),
            ],
            self::PartiallyPaid => [
                'bg-danger text-white',
                $this->getName(),
            ],
        };

        return vsprintf('<span class="badge %s">%s</span>', $string);
    }
}
