<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum OrderStatusEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case Pending = 'pending';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case Delivery = 'delivered';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case OutOfDelivery = 'outfordelivery';

    public function getName(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::InTransit => 'In Transit',
            self::Delivery => 'delivered',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::OutOfDelivery => 'Out of Delivery',
        };
    }
}

