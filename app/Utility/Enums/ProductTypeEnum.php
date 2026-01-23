<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum ProductTypeEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case ReadOnly = 'read_only';

    case Customize = 'customize';

    case LPO = 'lpo';

    case Material = 'material';

    case Product = 'product';

    public function getName(): string
    {
        return match($this) {
            self::ReadOnly => 'Ready to use',
            self::Customize => 'Customize',
            self::LPO => 'LPO (local provider order)',
            self::Material => 'Material',
            self::Product => 'Product',
        };
    }
}

