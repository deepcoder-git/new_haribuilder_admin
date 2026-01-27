<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\StoreManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $available_stock = (int)( $this->total_in - $this->total_out );
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product->product_name,
            'unit_type' => $this->product->unit_type,
            'category' => $this->product->category->name,
            'available_stock' => $available_stock,
            'low_stock' => ($available_stock < $this->product->low_stock_threshold) ? 1 : 0, 
        ];
    }
}
