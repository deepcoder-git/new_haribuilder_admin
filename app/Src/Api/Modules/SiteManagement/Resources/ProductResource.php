<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrls = [];
        
        if ($this->relationLoaded('productImages') && $this->productImages && $this->productImages->isNotEmpty()) {
            foreach ($this->productImages->sortBy('order') as $productImage) {
                $imagePath = $productImage->image_url;

                if (!empty($imagePath)) {
                    // If it's already an absolute URL, keep it as is
                    if (preg_match('#^https?://#i', $imagePath)) {
                        $imageUrls[] = $imagePath;
                    } else {
                        // Convert stored path to full URL
                        $imageUrls[] = url(Storage::url($imagePath));
                    }
                }
            }
        }
        
        if (empty($imageUrls) && $this->image) {
            $imageUrls[] = url(Storage::url($this->image));
        }
        
        // Format materials with pivot data
        $materials = [];
        if ($this->relationLoaded('materials') && $this->materials && $this->materials->isNotEmpty()) {
            foreach ($this->materials as $material) {
                $materialImageUrls = [];
                
                // Get material images
                if ($material->relationLoaded('productImages') && $material->productImages && $material->productImages->isNotEmpty()) {
                    foreach ($material->productImages->sortBy('order') as $productImage) {
                        $imagePath = $productImage->image_url ?? null;
                        if (!empty($imagePath)) {
                            // If it's already an absolute URL, keep it as is
                            if (preg_match('#^https?://#i', $imagePath)) {
                                $materialImageUrls[] = $imagePath;
                            } else {
                                // Convert stored path to full URL
                                $materialImageUrls[] = url(Storage::url($imagePath));
                            }
                        }
                    }
                }
                
                // Fallback to single image field if no productImages
                if (empty($materialImageUrls) && $material->image) {
                    $imagePath = $material->image;
                    if (preg_match('#^https?://#i', $imagePath)) {
                        $materialImageUrls[] = $imagePath;
                    } else {
                        $materialImageUrls[] = url(Storage::url($imagePath));
                    }
                }
                
                // Get pivot data (quantity and unit_type from product_materials table)
                $pivotQuantity = null;
                $pivotUnitType = null;
                if ($material->pivot) {
                    $pivotQuantity = $material->pivot->quantity ?? null;
                    $pivotUnitType = $material->pivot->unit_type ?? null;
                }
                
                $materials[] = [
                    'id' => $material->id,
                    'name' => $material->product_name ?? $material->material_name ?? null,
                    'material_name' => $material->product_name ?? $material->material_name ?? null,
                    'category_id' => $material->relationLoaded('category') && $material->category ? $material->category->id : null,
                    'category_name' => $material->relationLoaded('category') && $material->category ? $material->category->name : null,
                    'unit' => $material->unit_type ?? null,
                    'unit_type' => $material->unit_type ?? null,
                    'images' => $materialImageUrls,
                    'quantity' => $pivotQuantity !== null ? (float) $pivotQuantity : null,
                    'pivot_unit_type' => $pivotUnitType,
                ];
            }
        }
        
        return [
            'id' => $this->id,
            'name' => $this->product_name,
            'product_name' => $this->product_name,
            'type_name' => $this->store?->getName() ?? 'Workshop store',
            'category_id' => $this->category->id ?? null,
            'category_name' => $this->category->name ?? null,
            'unit' => $this->unit_type,
            'unit_type' => $this->unit_type,
            // Ensure low_stock is always an integer in the API response
            'low_stock' => $this->low_stock_threshold !== null
                ? (int) $this->low_stock_threshold
                : null,
            // Ensure quantity/available stock is always an integer in the API response
            'quantity' => (int) $this->total_stock_quantity,
            'status' => $this->status,
            'images' => $imageUrls,
            'description' => $this->description ?? null,
            'materials' => $materials,
            'created_at' => formatApiDate($this->created_at, 'd/m/Y', false),
            'updated_at' => formatApiDate($this->updated_at, 'd/m/Y', false),
        ];
    }
}
