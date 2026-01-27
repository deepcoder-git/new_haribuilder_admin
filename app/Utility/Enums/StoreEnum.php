<?php

declare(strict_types=1);

namespace App\Utility\Enums;

use App\Utility\Traits\CommonEnumTrait;
use EmreYarligan\EnumConcern\EnumConcern;

enum StoreEnum: string
{
    use CommonEnumTrait, EnumConcern;

    case HardwareStore = 'hardware_store';

    case WarehouseStore  = 'workshop_store';

    case WorkshopStore = 'workshop_store';

    case LPO = 'lpo';
    

    public function getName(): string
    {
        return match($this) {
            self::HardwareStore => 'Hardware Store',
            self::WarehouseStore => 'Workshop Store',
            self::LPO => 'LPO(Local Purchase Order)',
            self::WorkshopStore => 'Workshop Store',
        };
    }
}

