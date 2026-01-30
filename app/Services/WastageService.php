<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Wastage;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Order;
use App\Utility\Enums\WastageStatusEnum;
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
            'creator_type' => 'nullable|string|in:store_manager,site_manager,other',
            'site_id' => 'nullable|exists:sites,id',
            'order_id' => 'nullable|exists:orders,id',
            'date' => 'required|date',
            'reason' => 'nullable|string|max:1000',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.wastage_qty' => 'required|integer|min:1',
            'products.*.unit_type' => 'nullable|string|max:255',
            'products.*.adjust_stock' => 'sometimes|boolean',
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
        
        // Set default status to approved if not provided
        if (!isset($data['status'])) {
            $data['status'] = WastageStatusEnum::Approved->value;
        }
        
        // Validate wastage_qty doesn't exceed ordered qty for order-wise wastage
        if (!empty($data['order_id']) && !empty($products)) {
            $this->validateWastageQuantities($data['order_id'], $products);
        }
        
        return $data;
    }
    
    /**
     * Validate that wastage_qty doesn't exceed ordered qty for each product
     * 
     * @param int $orderId
     * @param array $products
     * @param int|null $excludeWastageId Exclude this wastage ID from total calculation (for edit)
     */
    protected function validateWastageQuantities(int $orderId, array $products, ?int $excludeWastageId = null): void
    {
        $order = Order::with('products')->findOrFail($orderId);
        
        // Get ordered quantities for each product
        $orderedQuantities = [];
        foreach ($order->products as $orderProduct) {
            $productId = $orderProduct->id;
            $orderedQty = (int) ($orderProduct->pivot->quantity ?? 0);
            $orderedQuantities[$productId] = ($orderedQuantities[$productId] ?? 0) + $orderedQty;
        }
        
        // Check custom products too
        if ($order->customProducts) {
            foreach ($order->customProducts as $customProduct) {
                // Custom products don't have standard quantity, skip for now
                // Or handle based on your custom product structure
            }
        }
        
        // Validate each wastage product
        foreach ($products as $index => $product) {
            $productId = (int) ($product['product_id'] ?? 0);
            $wastageQty = (int) ($product['wastage_qty'] ?? 0);
            $orderedQty = $orderedQuantities[$productId] ?? 0;
            
            if ($wastageQty > $orderedQty) {
                throw new \Exception(
                    "Wastage quantity ({$wastageQty}) cannot exceed ordered quantity ({$orderedQty}) for product ID {$productId}."
                );
            }
        }
        
        // Check total wastage for each product across all wastages for this order
        $existingWastagesQuery = Wastage::where('order_id', $orderId)
            ->where('status', WastageStatusEnum::Approved->value);
        
        if ($excludeWastageId) {
            $existingWastagesQuery->where('id', '!=', $excludeWastageId);
        }
        
        $existingWastages = $existingWastagesQuery->with('products')->get();
        
        $totalWastageQuantities = [];
        foreach ($existingWastages as $existingWastage) {
            foreach ($existingWastage->products as $wastageProduct) {
                $productId = $wastageProduct->id;
                $wastageQty = (int) ($wastageProduct->pivot->wastage_qty ?? 0);
                $totalWastageQuantities[$productId] = ($totalWastageQuantities[$productId] ?? 0) + $wastageQty;
            }
        }
        
        // Add new wastage quantities
        foreach ($products as $product) {
            $productId = (int) ($product['product_id'] ?? 0);
            $wastageQty = (int) ($product['wastage_qty'] ?? 0);
            $totalWastageQuantities[$productId] = ($totalWastageQuantities[$productId] ?? 0) + $wastageQty;
        }
        
        // Final validation: total wastage should not exceed ordered qty
        foreach ($totalWastageQuantities as $productId => $totalWastageQty) {
            $orderedQty = $orderedQuantities[$productId] ?? 0;
            if ($totalWastageQty > $orderedQty) {
                throw new \Exception(
                    "Total wastage quantity ({$totalWastageQty}) cannot exceed ordered quantity ({$orderedQty}) for product ID {$productId}."
                );
            }
        }
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

        // Only sync stock if status is approved
        if ($model->status === WastageStatusEnum::Approved) {
            $this->syncWastageStock($model, $data);
        }
    }

    protected function afterUpdate($model, array $data): void
    {
        // Validate wastage_qty doesn't exceed ordered qty for order-wise wastage
        if (!empty($data['order_id']) && !empty($data['products'])) {
            // Exclude current wastage from validation (for edit)
            $this->validateWastageQuantities($data['order_id'], $data['products'], $model->id);
        }
        
        if (isset($data['products']) && is_array($data['products'])) {
            $productsData = [];
            foreach ($data['products'] as $product) {
                $productsData[$product['product_id']] = [
                    'quantity' => $product['quantity'] ?? $product['wastage_qty'],
                    'wastage_qty' => $product['wastage_qty'],
                    'unit_type' => $product['unit_type'] ?? null,
                    'adjust_stock' => !empty($product['adjust_stock']),
                ];
            }
            $model->products()->sync($productsData);
        }
        
        // Remove old stock adjustments if status changed
        $oldStatus = $model->getOriginal('status');
        if ($oldStatus && $oldStatus !== WastageStatusEnum::Approved->value) {
            Stock::where('reference_type', Wastage::class)
                ->where('reference_id', $model->id)
                ->delete();
        }

        // Only sync stock if status is approved
        if ($model->status === WastageStatusEnum::Approved) {
            $this->syncWastageStock($model, $data);
        }
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

