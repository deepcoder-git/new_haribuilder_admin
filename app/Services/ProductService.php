<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

class ProductService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Product::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'status' => 'boolean',
            'product_name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_type' => 'nullable|string|max:255',
            'low_stock_threshold' => 'nullable|numeric|min:0',
            'available_qty' => 'nullable|integer|min:0',
            'images' => 'required|array|min:1',
            'images.*' => 'required|image|max:2048',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        $rules['images'] = 'nullable|array';
        $rules['images.*'] = 'nullable|image|max:2048';
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        return $data;
    }
}

