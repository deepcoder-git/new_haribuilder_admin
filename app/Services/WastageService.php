<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Wastage;
use App\Models\Stock;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Services\StockService;

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
                    'adjust_stock' => !empty($product['adjust_stock']),
                ];
            }
            $model->products()->sync($productsData);
        }

        $this->syncWastageStock($model, $data);
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
                    'adjust_stock' => !empty($product['adjust_stock']),
                ];
            }
            $model->products()->sync($productsData);
        }

        $this->syncWastageStock($model, $data);
    }

    /**
     * Create / refresh stock "out" entries for wastage so that StockReport reflects deductions.
     */
    protected function syncWastageStock(Wastage $wastage, array $data): void
    {
        /** @var StockService $stockService */
        $stockService = app(StockService::class);

        // Remove previous stock adjustments for this wastage to keep it idempotent
        Stock::where('reference_type', Wastage::class)
            ->where('reference_id', $wastage->id)
            ->delete();

        $products = $data['products'] ?? [];
        if (empty($products)) {
            return;
        }

        $siteId = $wastage->site_id ? (int) $wastage->site_id : null;

        foreach ($products as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['wastage_qty'] ?? 0);
            $adjustStock = !empty($row['adjust_stock']);

            if ($productId <= 0 || $qty <= 0 || !$adjustStock) {
                continue;
            }

            /** @var Product|null $product */
            $product = Product::find($productId);
            if (!$product) {
                continue;
            }

            $isProduct = ($product->is_product ?? 1) > 0;

            $notes = "Stock reduced from Wastage #{$wastage->id} (qty: " . number_format($qty, 2) . ")";
            $name = "Wastage #{$wastage->id} - Stock Out";

            try {
                if ($isProduct) {
                    $stockService->adjustProductStock($productId, $qty, 'out', $siteId, $notes, $wastage, $name);
                } else {
                    $stockService->adjustMaterialStock($productId, $qty, 'out', $siteId, $notes, $wastage, $name);
                }
            } catch (\Throwable $e) {
                // Let caller bubble up; we don't swallow stock errors silently
                throw $e;
            }
        }
    }
}

