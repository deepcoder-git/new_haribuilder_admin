<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductPurchase;
use App\Models\ProductPurchaseItem;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class ProductPurchaseService extends BaseCrudService
{
    protected ?StockService $stockService = null;

    public function __construct()
    {
        $this->stockService = app(StockService::class);
    }

    protected function getModelClass(): string
    {
        return ProductPurchase::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'purchase_number' => 'required|string|max:255|unique:product_purchases,purchase_number',
            'total_amount' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'status' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|integer|min:0',
            'items.*.total_price' => 'nullable|integer|min:0',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        $rules['purchase_number'] = 'required|string|max:255|unique:product_purchases,purchase_number,' . request()->route('id');
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        $data['created_by'] = $data['created_by'] ?? auth('moderator')->id();
        
        // Calculate total amount from items if not provided
        if (!isset($data['total_amount']) || $data['total_amount'] == 0) {
            $totalAmount = 0;
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemTotal = $item['total_price'] ?? ($item['quantity'] * ($item['unit_price'] ?? 0));
                    $totalAmount += $itemTotal;
                }
            }
            $data['total_amount'] = (int)$totalAmount;
        }
        
        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareCreateData($data);
    }

    protected function afterCreate(\Illuminate\Database\Eloquent\Model $model, array $data): void
    {
        $this->syncPurchaseItems($model, $data['items'] ?? []);
        
        // Update stock for each product
        if ($model->status && isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $this->stockService->adjustStock(
                    (int)$item['product_id'],
                    (int)$item['quantity'],
                    'in',
                    null,
                    "Stock added from Purchase #{$model->purchase_number}",
                    $model,
                    "Purchase #{$model->purchase_number}"
                );
            }
        }
    }

    protected function afterUpdate(\Illuminate\Database\Eloquent\Model $model, array $data): void
    {
        $oldItems = $model->items()->get()->keyBy('product_id');
        $newItems = collect($data['items'] ?? [])->keyBy('product_id');
        
        // Calculate stock adjustments
        $itemsToAdd = $newItems->diffKeys($oldItems);
        $itemsToRemove = $oldItems->diffKeys($newItems);
        $itemsToUpdate = $newItems->intersectByKeys($oldItems);
        
        // Remove stock for deleted items
        foreach ($itemsToRemove as $oldItem) {
            if ($model->status) {
                $this->stockService->adjustStock(
                    (int)$oldItem->product_id,
                    (int)$oldItem->quantity,
                    'out',
                    null,
                    "Stock removed from Purchase #{$model->purchase_number} (item deleted)",
                    $model,
                    "Purchase #{$model->purchase_number} - Item Removed"
                );
            }
        }
        
        // Add stock for new items
        foreach ($itemsToAdd as $newItem) {
            if ($model->status) {
                $this->stockService->adjustStock(
                    (int)$newItem['product_id'],
                    (int)$newItem['quantity'],
                    'in',
                    null,
                    "Stock added from Purchase #{$model->purchase_number} (new item)",
                    $model,
                    "Purchase #{$model->purchase_number} - New Item"
                );
            }
        }
        
        // Update stock for modified items
        foreach ($itemsToUpdate as $productId => $newItem) {
            $oldItem = $oldItems[$productId];
            $oldQty = (int)$oldItem->quantity;
            $newQty = (int)$newItem['quantity'];
            
            if ($model->status && abs($oldQty - $newQty) > 0.001) {
                $difference = $newQty - $oldQty;
                if ($difference > 0) {
                    $this->stockService->adjustStock(
                        (int)$productId,
                        $difference,
                        'in',
                        null,
                        "Stock adjusted from Purchase #{$model->purchase_number} (+{$difference})",
                        $model,
                        "Purchase #{$model->purchase_number} - Quantity Updated"
                    );
                } else {
                    $this->stockService->adjustStock(
                        (int)$productId,
                        abs($difference),
                        'out',
                        null,
                        "Stock adjusted from Purchase #{$model->purchase_number} (-{$difference})",
                        $model,
                        "Purchase #{$model->purchase_number} - Quantity Updated"
                    );
                }
            }
        }
        
        $this->syncPurchaseItems($model, $data['items'] ?? []);
    }

    protected function beforeDelete(\Illuminate\Database\Eloquent\Model $model): void
    {
        // Remove stock for all items when purchase is deleted
        if ($model->status) {
            foreach ($model->items as $item) {
                $this->stockService->adjustStock(
                    (int)$item->product_id,
                    (int)$item->quantity,
                    'out',
                    null,
                    "Stock removed from deleted Purchase #{$model->purchase_number}",
                    $model,
                    "Purchase #{$model->purchase_number} - Deleted"
                );
            }
        }
    }

    protected function syncPurchaseItems(ProductPurchase $purchase, array $items): void
    {
        DB::transaction(function () use ($purchase, $items) {
            // Delete existing items
            $purchase->items()->delete();
            
            // Create new items
            foreach ($items as $item) {
                $itemTotal = $item['total_price'] ?? ($item['quantity'] * ($item['unit_price'] ?? 0));
                
                ProductPurchaseItem::create([
                    'product_purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $itemTotal,
                ]);
            }
            
            // Recalculate and update total amount
            $totalAmount = (int)$purchase->items()->sum('total_price');
            $purchase->update(['total_amount' => $totalAmount]);
        });
    }

    /**
     * Generate unique purchase number
     */
    public function generatePurchaseNumber(): string
    {
        $prefix = 'PUR-';
        $date = now()->format('Ymd');
        $lastPurchase = ProductPurchase::where('purchase_number', 'like', "{$prefix}{$date}%")
            ->orderBy('purchase_number', 'desc')
            ->first();
        
        if ($lastPurchase) {
            $lastNumber = (int)substr($lastPurchase->purchase_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . '-' . str_pad((string)$newNumber, 4, '0', STR_PAD_LEFT);
    }
}

