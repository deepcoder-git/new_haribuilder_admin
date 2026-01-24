<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Report;

class LowStockReport extends StockReport
{
    public bool $onlyLowStock = true;
}


