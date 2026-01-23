<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

class MaterialService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Product::class;
    }
    
    /**
     * Get base query for materials (is_product = 0)
     */
    protected function getBaseQuery()
    {
        return Product::where('is_product', 0);
    }

    protected function getCreateRules(): array
    {
        return [
            'status' => 'boolean',
            'product_name' => 'required|string|max:255', // Use product_name instead of material_name
            'category_id' => 'required|exists:categories,id',
            'unit_type' => 'nullable|string|max:255',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'available_qty' => 'nullable|integer|min:0',
            'image' => 'required|image|max:2048',
            'is_product' => 'boolean',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        $rules['image'] = 'nullable|image|max:2048';
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        $data['is_product'] = false; // Materials always have is_product = 0
        // Map material_name to product_name if provided
        if (isset($data['material_name'])) {
            $data['product_name'] = $data['material_name'];
            unset($data['material_name']);
        }
        return $data;
    }
}

