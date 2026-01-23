<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Wastage;
use Illuminate\Support\Facades\DB;

class WastageService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Wastage::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'type' => 'required|string|in:site_wastage,store_wastage',
            'manager_id' => 'required|exists:moderators,id',
            'site_id' => 'nullable|exists:sites,id',
            'order_id' => 'nullable|exists:orders,id',
            'date' => 'required|date',
            'reason' => 'nullable|string|max:1000',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.wastage_qty' => 'required|integer|min:1',
            'products.*.unit_type' => 'nullable|string|max:255',
        ];
    }

    protected function getUpdateRules(): array
    {
        return $this->getCreateRules();
    }

    protected function prepareCreateData(array $data): array
    {
        $products = $data['products'] ?? [];
        unset($data['products']);
        return $data;
    }

    protected function afterCreate($model, array $data): void
    {
        if (isset($data['products']) && is_array($data['products'])) {
            $productsData = [];
            foreach ($data['products'] as $product) {
                $productsData[$product['product_id']] = [
                    'quantity' => $product['quantity'],
                    'wastage_qty' => $product['wastage_qty'],
                    'unit_type' => $product['unit_type'] ?? null,
                ];
            }
            $model->products()->sync($productsData);
        }
    }

    protected function afterUpdate($model, array $data): void
    {
        if (isset($data['products']) && is_array($data['products'])) {
            $productsData = [];
            foreach ($data['products'] as $product) {
                $productsData[$product['product_id']] = [
                    'quantity' => $product['wastage_qty'],
                    'wastage_qty' => $product['wastage_qty'],
                    'unit_type' => $product['unit_type'] ?? null,
                ];
            }
            $model->products()->sync($productsData);
        }
    }
}

