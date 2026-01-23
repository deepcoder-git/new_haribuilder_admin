<?php

declare(strict_types=1);

namespace App\Utility\Enums\Fees;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum FeePaymentTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;
    case Receipt = 'receipt';
    case Discount = 'discount';
    case ScholarShip = 'scholarship';

}
