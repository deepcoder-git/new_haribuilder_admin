<?php

declare(strict_types=1);

namespace App\Utility\Enums\Fees;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum FeePaymentMethodEnum: string
{
    use CommonEnumTrait, EnumConcern;
    case Cash = 'cash';

    case Cheque = 'Cheque';
    case Upi = 'upi';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    public function getName(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::Upi => 'UPI',
            self::Card => 'Card',
            self::BankTransfer => 'Bank Transfer',
        };
    }
}
