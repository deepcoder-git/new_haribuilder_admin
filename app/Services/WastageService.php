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
        // Include both approved and pending wastages (rejected wastages don't count)
        $existingWastagesQuery = Wastage::where('order_id', $orderId)
            ->whereIn('status', [
                WastageStatusEnum::Approved->value,
                WastageStatusEnum::Pending->value
            ]);
        
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
                // Get product name for better error message
                $product = Product::find($productId);
                $productName = $product ? $product->product_name : "Product ID {$productId}";
                
                // Calculate existing wastage (sum of all existing wastages for this product)
                $existingTotal = 0;
                foreach ($existingWastages as $existingWastage) {
                    foreach ($existingWastage->products as $wastageProduct) {
                        if ($wastageProduct->id == $productId) {
                            $existingTotal += (int) ($wastageProduct->pivot->wastage_qty ?? 0);
                        }
                    }
                }
                
                // Calculate new wastage quantity being added
                $newWastageQty = 0;
                foreach ($products as $product) {
                    if ((int) ($product['product_id'] ?? 0) == $productId) {
                        $newWastageQty += (int) ($product['wastage_qty'] ?? 0);
                    }
                }
                
                // Build detailed error message
                $message = "Total wastage quantity ({$totalWastageQty}) cannot exceed ordered quantity ({$orderedQty}) for {$productName} (ID: {$productId}). ";
                if ($existingTotal > 0) {
                    $message .= "Existing wastage: {$existingTotal}. ";
                }
                if ($newWastageQty > 0) {
                    $message .= "New wastage: {$newWastageQty}. ";
                }
                $message .= "Please reduce the wastage quantity or remove existing wastages for this product.";
                
                throw new \Exception($message);
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
        
        // Check both model and data for order_id (model might not be refreshed yet)
        // Refresh model if order_id is not found to ensure we have the latest data
        $orderId = $wastage->order_id ?? $data['order_id'] ?? null;
        if (empty($orderId) && $wastage->id) {
            $wastage->refresh();
            $orderId = $wastage->order_id ?? null;
        }
        
        $isOrderWastage = !empty($orderId);

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
            if ($isOrderWastage && $orderId) {
                $notes .= " - Order #{$orderId}";
            }
            $name = "Wastage #{$wastage->id} - Stock Out";

            try {
                // Always use createWastageStockEntry to bypass stock availability checks
                // This allows recording wastage even if current stock is 0 or insufficient
                // Wastage is a record of loss/damage, not a stock transaction that requires availability
                $this->createWastageStockEntry($productId, $qty, $siteId, $notes, $wastage, $name, $isProduct);
            } catch (\Throwable $e) {
                // Let caller bubble up; we don't swallow stock errors silently
                throw $e;
            }
        }
    }

    /**
     * Create stock entry for order-wise wastage without availability check
     * This allows recording wastage of products that were already delivered/used
     */
    protected function createWastageStockEntry(int $productId, int $quantity, ?int $siteId, string $notes, Wastage $wastage, string $name, bool $isProduct): void
    {
        // Get current stock (may be 0 or negative)
        // For order-wise wastage, we allow negative stock since products were already delivered
        $query = Stock::where('product_id', $productId)
            ->where('status', true);
        
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        } else {
            $query->whereNull('site_id');
        }
        
        $stock = $query->orderByDesc('id')->first();

        // If no stock record exists, check product's available_qty (for general stock only)
        $currentQuantity = 0;
        if ($stock) {
            $currentQuantity = (int)$stock->quantity;
        } elseif ($siteId === null) {
            $product = Product::find($productId);
            $currentQuantity = $product ? (int)($product->available_qty ?? 0) : 0;
        }
        
        $newQuantity = $currentQuantity - $quantity; // Allow negative for order-wise wastage

        $stockData = [
            'product_id' => $productId,
            'site_id' => $siteId,
            'name' => $name,
            'quantity' => $newQuantity,
            'adjustment_type' => 'out',
            'notes' => $notes,
            'status' => true,
            'reference_id' => $wastage->id,
            'reference_type' => Wastage::class,
        ];

        Stock::create($stockData);

        // Update product's available_qty to match the latest stock quantity (for general stock, site_id = null)
        if ($siteId === null) {
            $latestStock = Stock::where('product_id', $productId)
                ->whereNull('site_id')
                ->where('status', true)
                ->orderByDesc('id')
                ->first();
            
            $newAvailableQty = $latestStock ? (int)$latestStock->quantity : 0;
            
            Product::where('id', $productId)->update([
                'available_qty' => $newAvailableQty
            ]);
        }
    }
}

