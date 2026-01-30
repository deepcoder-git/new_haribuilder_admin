<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class ReturnService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return OrderReturn::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['required', 'exists:moderators,id'],
            'creator_type' => ['nullable', 'string', 'in:store_manager,site_manager,other'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:pending,approved,rejected,completed'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.ordered_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.return_quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_type' => ['nullable', 'string', 'max:255'],
            'items.*.adjust_stock' => ['sometimes', 'boolean'],
        ];
    }

    protected function getUpdateRules(): array
    {
        return $this->getCreateRules();
    }

    protected function prepareCreateData(array $data): array
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        // Validate return_quantity doesn't exceed ordered_quantity for order-wise returns
        if (!empty($data['order_id']) && !empty($items)) {
            $this->validateReturnQuantities($data['order_id'], $items);
        }

        return $data;
    }
    
    /**
     * Validate that return_quantity doesn't exceed ordered_quantity for each product
     * 
     * @param int $orderId
     * @param array $items
     * @param int|null $excludeReturnId Exclude this return ID from total calculation (for edit)
     */
    protected function validateReturnQuantities(int $orderId, array $items, ?int $excludeReturnId = null): void
    {
        $order = Order::with('products')->findOrFail($orderId);
        
        // Get ordered quantities for each product
        $orderedQuantities = [];
        foreach ($order->products as $orderProduct) {
            $productId = $orderProduct->id;
            $orderedQty = (int) ($orderProduct->pivot->quantity ?? 0);
            $orderedQuantities[$productId] = ($orderedQuantities[$productId] ?? 0) + $orderedQty;
        }
        
        // Validate each return item
        foreach ($items as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $returnQty = (int) ($item['return_quantity'] ?? 0);
            $orderedQty = (int) ($item['ordered_quantity'] ?? 0);
            
            // If ordered_quantity not provided, get from order
            if ($orderedQty === 0) {
                $orderedQty = $orderedQuantities[$productId] ?? 0;
            }
            
            if ($returnQty > $orderedQty) {
                throw new \Exception(
                    "Return quantity ({$returnQty}) cannot exceed ordered quantity ({$orderedQty}) for product ID {$productId}."
                );
            }
        }
        
        // Check total return for each product across all returns for this order
        $existingReturnsQuery = OrderReturn::where('order_id', $orderId)
            ->where('status', 'approved');
        
        if ($excludeReturnId) {
            $existingReturnsQuery->where('id', '!=', $excludeReturnId);
        }
        
        $existingReturns = $existingReturnsQuery->with('items')->get();
        
        $totalReturnQuantities = [];
        foreach ($existingReturns as $existingReturn) {
            foreach ($existingReturn->items as $returnItem) {
                $productId = $returnItem->product_id;
                $returnQty = (int) ($returnItem->return_quantity ?? 0);
                $totalReturnQuantities[$productId] = ($totalReturnQuantities[$productId] ?? 0) + $returnQty;
            }
        }
        
        // Add new return quantities
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $returnQty = (int) ($item['return_quantity'] ?? 0);
            $totalReturnQuantities[$productId] = ($totalReturnQuantities[$productId] ?? 0) + $returnQty;
        }
        
        // Final validation: total return should not exceed ordered qty
        foreach ($totalReturnQuantities as $productId => $totalReturnQty) {
            $orderedQty = $orderedQuantities[$productId] ?? 0;
            if ($totalReturnQty > $orderedQty) {
                throw new \Exception(
                    "Total return quantity ({$totalReturnQty}) cannot exceed ordered quantity ({$orderedQty}) for product ID {$productId}."
                );
            }
        }
    }

    protected function prepareUpdateData(array $data): array
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        // Validate return_quantity doesn't exceed ordered_quantity for order-wise returns
        if (!empty($data['order_id']) && !empty($items)) {
            $this->validateReturnQuantities($data['order_id'], $items, $data['id'] ?? null);
        }

        return $data;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        /** @var OrderReturn $return */
        $return = $model instanceof OrderReturn ? $model : OrderReturn::findOrFail($model->getKey());

        $this->syncItems($return, $data['items'] ?? []);
        $this->syncReturnStock($return);
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        /** @var OrderReturn $return */
        $return = $model instanceof OrderReturn ? $model : OrderReturn::findOrFail($model->getKey());

        $this->syncItems($return, $data['items'] ?? []);
        $this->syncReturnStock($return);
    }

    /**
     * Sync return items for an order return.
     *
     * @param  OrderReturn  $return
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncItems(OrderReturn $return, array $items): void
    {
        // Remove existing items and recreate from payload for simplicity
        $return->items()->delete();

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['return_quantity'])) {
                continue;
            }

            $return->items()->create([
                'order_id' => $return->order_id,
                'product_id' => $item['product_id'],
                'ordered_quantity' => $item['ordered_quantity'] ?? 0,
                'return_quantity' => $item['return_quantity'],
                'unit_type' => $item['unit_type'] ?? null,
                'adjust_stock' => !empty($item['adjust_stock']),
            ]);
        }
    }

    /**
     * Create / refresh stock "in" entries for a given return so that StockReport reflects it.
     */
    protected function syncReturnStock(OrderReturn $return): void
    {
        /** @var StockService $stockService */
        $stockService = app(StockService::class);

        // Remove previous stock adjustments for this return to keep it idempotent
        Stock::where('reference_type', OrderReturn::class)
            ->where('reference_id', $return->id)
            ->delete();

        $return->loadMissing('items.product');

        $siteId = $return->site_id ? (int) $return->site_id : null;

        foreach ($return->items as $item) {
            if (empty($item->adjust_stock) || !$item->product || $item->return_quantity <= 0) {
                continue;
            }

            $qty = (int) $item->return_quantity;
            $product = $item->product;

            // Decide whether this is product or material based on is_product flag
            $isProduct = ($product->is_product ?? 1) > 0;

            $notes = "Stock increased from Return #{$return->id} (qty: " . number_format($qty, 2) . ")";
            $name = "Return #{$return->id} - Stock In";

            if ($isProduct) {
                $stockService->adjustProductStock((int) $product->id, $qty, 'in', $siteId, $notes, $return, $name);
            } else {
                $stockService->adjustMaterialStock((int) $product->id, $qty, 'in', $siteId, $notes, $return, $name);
            }
        }
    }
}

