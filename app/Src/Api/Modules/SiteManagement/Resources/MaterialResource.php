<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrls = [];
        
        // Handle material images (can be array or single image)
        if ($this->image) {
            $imagePaths = $this->image_paths ?? [];
            
            foreach ($imagePaths as $imagePath) {
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
        
        return [
            'id' => $this->id,
            'name' => $this->product_name ?? $this->material_name ?? null,
            'material_name' => $this->product_name ?? $this->material_name ?? null,
            'category_id' => $this->category->id ?? null,
            'category_name' => $this->category->name ?? null,
            'unit' => $this->unit_type,
            'unit_type' => $this->unit_type,
            'low_stock' => $this->low_stock_threshold !== null
                ? (int) $this->low_stock_threshold
                : null,
            'quantity' => (int) $this->total_stock_quantity,
            'status' => $this->status,
            'images' => $imageUrls,
            'description' => $this->description ?? null,
            'is_product' => $this->is_product ?? false,
            'created_at' => formatApiDate($this->created_at, 'd/m/Y', false),
            'updated_at' => formatApiDate($this->updated_at, 'd/m/Y', false),
            'type' => 'material', // Add type identifier for dropdown
        ];
    }
}

