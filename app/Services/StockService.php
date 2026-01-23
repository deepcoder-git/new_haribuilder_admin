<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Stock;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Stock::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'product_id' => 'nullable|exists:products,id',
            'site_id' => 'nullable|exists:sites,id',
            'name' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:0',
            'adjustment_type' => 'required|in:in,out,adjustment',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'status' => 'boolean',
        ];
    }

    protected function getUpdateRules(): array
    {
        return $this->getCreateRules();
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        return $data;
    }

    /**
     * Adjust stock for products (is_product IN (1, 2)) or materials (is_product = 0)
     * Stock is managed separately for products and materials based on is_product flag
     * 
     * @param int $productId Product/Material ID
     * @param int $quantity Quantity to adjust
     * @param string $adjustmentType 'in', 'out', or 'adjustment'
     * @param int|null $siteId Site ID (null for general stock)
     * @param string|null $notes Notes for the adjustment
     * @param mixed|null $reference Reference model (Order, etc.)
     * @param string|null $name Name for the stock entry
     * @param array|null $metadata Additional metadata
     * @param bool|null $isProduct Force check is_product flag (null = auto-detect)
     * @return Stock
     */
    public function adjustStock(int $productId, int $quantity, string $adjustmentType, ?int $siteId = null, ?string $notes = null, $reference = null, ?string $name = null, ?array $metadata = null, ?bool $isProduct = null): Stock
    {
        return DB::transaction(function () use ($productId, $quantity, $adjustmentType, $siteId, $notes, $reference, $name, $metadata, $isProduct) {
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('Quantity must be greater than 0');
            }

            // Get product/material and validate is_product flag
            $product = Product::find($productId);
            if (!$product) {
                throw new \InvalidArgumentException("Product/Material with ID {$productId} not found");
            }

            // Determine if this is a product or material
            // is_product = 0 is material, is_product IN (1, 2) is product
            $isProductFlag = $isProduct !== null ? $isProduct : (($product->is_product ?? 1) > 0);
            
            // Ensure stock queries filter by is_product condition through product relationship
            $query = Stock::where('product_id', $productId)
                ->where('status', true);
            
            // Only filter by is_product if column exists (check schema)
            if (Schema::hasColumn('products', 'is_product')) {
                $query->whereHas('product', function($q) use ($isProductFlag) {
                    if ($isProductFlag) {
                        $q->whereIn('is_product', [1, 2]);
                    } else {
                        $q->where('is_product', 0);
                    }
                });
            }
            
            if ($siteId !== null) {
                $query->where('site_id', $siteId);
            } else {
                $query->whereNull('site_id');
            }
            
            $stock = $query->latest('created_at')
                ->latest('id')
                ->first();

            // If no stock record exists, fall back to product's available_qty as opening balance (for general stock)
            if (!$stock && $siteId === null) {
                $currentQuantity = (int)($product->available_qty ?? 0);
            } else {
                $currentQuantity = $stock ? (int)$stock->quantity : 0;
            }
            
            $newQuantity = match($adjustmentType) {
                'in' => $currentQuantity + $quantity,
                'out' => $currentQuantity - $quantity,
                'adjustment' => $quantity,
                default => $currentQuantity,
            };
            $newQuantity = (int)$newQuantity;

            if ($adjustmentType === 'out' && $newQuantity < 0) {
                $typeLabel = $isProductFlag ? 'product' : 'material';
                throw new \RuntimeException("Insufficient {$typeLabel} stock. Available: {$currentQuantity}, Requested: {$quantity}");
            }

            $stockData = [
                'product_id' => $productId,
                'site_id' => $siteId,
                'name' => $name,
                'quantity' => $newQuantity,
                'adjustment_type' => $adjustmentType,
                'notes' => $notes,
                'metadata' => $metadata,
                'status' => true,
                'reference_id' => null,
                'reference_type' => null,
            ];

            if ($reference) {
                $stockData['reference_id'] = $reference->id;
                $stockData['reference_type'] = get_class($reference);
            }

            $stock = Stock::create($stockData);

            // Update product's available_qty to match the latest stock quantity (for general stock, site_id = null)
            if ($siteId === null) {
                $this->syncProductAvailableQty($productId, $isProductFlag);
            }

            return $stock;
        });
    }

    /**
     * Adjust stock specifically for products (is_product IN (1, 2))
     */
    public function adjustProductStock(int $productId, int $quantity, string $adjustmentType, ?int $siteId = null, ?string $notes = null, $reference = null, ?string $name = null, ?array $metadata = null): Stock
    {
        return $this->adjustStock($productId, $quantity, $adjustmentType, $siteId, $notes, $reference, $name, $metadata, true);
    }

    /**
     * Adjust stock specifically for materials (is_product = 0)
     */
    public function adjustMaterialStock(int $productId, int $quantity, string $adjustmentType, ?int $siteId = null, ?string $notes = null, $reference = null, ?string $name = null, ?array $metadata = null): Stock
    {
        return $this->adjustStock($productId, $quantity, $adjustmentType, $siteId, $notes, $reference, $name, $metadata, false);
    }

    /**
     * Sync product/material available_qty with latest stock entry
     * Respects is_product flag to manage products and materials separately
     */
    protected function syncProductAvailableQty(int $productId, ?bool $isProduct = null): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        // Determine is_product flag
        // is_product = 0 is material, is_product IN (1, 2) is product
        $isProductFlag = $isProduct !== null ? $isProduct : (($product->is_product ?? 1) > 0);

        // Get latest stock entry for this product/material (respecting is_product condition)
        $query = Stock::where('product_id', $productId)
            ->whereNull('site_id')
            ->where('status', true);
        
        // Only filter by is_product if column exists
        if (Schema::hasColumn('products', 'is_product')) {
            $query->whereHas('product', function($q) use ($isProductFlag) {
                if ($isProductFlag) {
                    $q->whereIn('is_product', [1, 2]);
                } else {
                    $q->where('is_product', 0);
                }
            });
        }
        
        $latestStock = $query->latest('created_at')
            ->latest('id')
            ->first();

        $newAvailableQty = $latestStock ? (int)$latestStock->quantity : 0;

        Product::where('id', $productId)->update([
            'available_qty' => $newAvailableQty
        ]);
    }

    /**
     * Get current stock for product or material
     * Stock is managed separately based on is_product flag
     * 
     * @param int $productId Product/Material ID
     * @param int|null $siteId Site ID (null for general stock)
     * @param bool|null $isProduct Force check is_product flag (null = auto-detect)
     * @return int Current stock quantity
     */
    public function getCurrentStock(int $productId, ?int $siteId = null, ?bool $isProduct = null): int
    {
        // Get product/material to determine is_product flag
        $product = Product::find($productId);
        if (!$product) {
            return 0;
        }

        // Determine if this is a product or material
        // is_product = 0 is material, is_product IN (1, 2) is product
        $isProductFlag = $isProduct !== null ? $isProduct : (($product->is_product ?? 1) > 0);

        // Query stock with is_product condition
        $query = Stock::where('product_id', $productId)
            ->where('status', true);
        
        // Only filter by is_product if column exists
        if (Schema::hasColumn('products', 'is_product')) {
            $query->whereHas('product', function($q) use ($isProductFlag) {
                if ($isProductFlag) {
                    $q->whereIn('is_product', [1, 2]);
                } else {
                    $q->where('is_product', 0);
                }
            });
        }
        
        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        } else {
            $query->whereNull('site_id');
        }
        
        $stock = $query->latest('created_at')
            ->latest('id')
            ->first();

        return $stock ? (int) $stock->quantity : 0;
    }

    /**
     * Get current stock specifically for products (is_product IN (1, 2))
     */
    public function getCurrentProductStock(int $productId, ?int $siteId = null): int
    {
        return $this->getCurrentStock($productId, $siteId, true);
    }

    /**
     * Get current stock specifically for materials (is_product = 0)
     */
    public function getCurrentMaterialStock(int $productId, ?int $siteId = null): int
    {
        return $this->getCurrentStock($productId, $siteId, false);
    }
}

