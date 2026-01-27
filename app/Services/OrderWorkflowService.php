<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Utility\Enums\StoreEnum;
use Illuminate\Support\Facades\Log;

/**
 * Centralized workflow helpers for order stock adjustment and status-related side effects.
 *
 * NOTE:
 * - This service currently focuses on stock-deduction rules for hardware vs warehouse/custom.
 * - Controllers (API/Admin) remain responsible for HTTP concerns and response formatting.
 */
class OrderWorkflowService
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }


    public function extractRegularProductsAndSuppliers(array $rawProducts): array
    {
        $productsData = [];
        $supplierMapping = [];

        foreach ($rawProducts as $product) {
            $isCustom = filter_var($product['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);
            if ($isCustom) {
                continue;
            }

            $productId = $product['product_id'] ?? null;
            $quantity = $product['quantity'] ?? null;

            if (empty($productId) || empty($quantity)) {
                continue;
            }

            $productIdInt = (int) $productId;
            $quantityInt = (int) $quantity;

            if ($productIdInt <= 0 || $quantityInt <= 0) {
                continue;
            }

            $productsData[$productIdInt] = [
                'quantity' => $quantityInt,
            ];

            // Handle LPO supplier mapping when supplier_id is present
            if (!empty($product['supplier_id'])) {
                $productModel = Product::find($productIdInt);
                if ($productModel && $productModel->store === StoreEnum::LPO) {
                    $supplierMapping[(string) $productIdInt] = (int) $product['supplier_id'];
                }
            }
        }

        return [$productsData, $supplierMapping];
    }

    public function deductHardwareStockOnApproval(Order $order, ?int $siteId = null): void
    {
        // Ensure products are loaded
        $order->loadMissing('products');

        if ($order->is_lpo == 1) {
            return;
        }

        foreach ($order->products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            // Only hardware products are deducted on approval
            if ($product->store !== StoreEnum::HardwareStore) {
                continue;
            }

            $quantity = (int) ($product->pivot->quantity ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            try {
                $this->stockService->adjustStock(
                    (int) $product->id,
                    $quantity,
                    'out',
                    $siteId,
                    "Stock deducted for Order #{$order->id} (hardware, quantity: " . number_format($quantity, 2) . ")",
                    $order,
                    "Order #{$order->id} - Hardware Stock Deducted (Approved)"
                );
            } catch (\Throwable $e) {
                Log::error(
                    "OrderWorkflowService::deductHardwareStockOnApproval: Failed for product {$product->id} in order {$order->id}: "
                    . $e->getMessage(),
                    ['trace' => $e->getTraceAsString()]
                );
                throw $e;
            }
        }
    }

    public function deductWarehouseStockOnOutForDelivery(Order $order, ?int $siteId = null): void
    {
        // Ensure products are loaded
        $order->loadMissing('products');

        if ($order->is_lpo == 1) {
            return;
        }

        foreach ($order->products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            // Only warehouse products are deducted on out for delivery
            if ($product->store !== StoreEnum::WarehouseStore) {
                continue;
            }

            $quantity = (int) ($product->pivot->quantity ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            try {
                $this->stockService->adjustStock(
                    (int) $product->id,
                    $quantity,
                    'out',
                    $siteId,
                    "Stock deducted for Order #{$order->id} (warehouse, out for delivery, quantity: "
                    . number_format($quantity, 2) . ")",
                    $order,
                    "Order #{$order->id} - Warehouse Stock Deducted (Out for Delivery)"
                );
            } catch (\Throwable $e) {
                Log::error(
                    "OrderWorkflowService::deductWarehouseStockOnOutForDelivery: Failed for product {$product->id} in order {$order->id}: "
                    . $e->getMessage(),
                    ['trace' => $e->getTraceAsString()]
                );
                throw $e;
            }
        }
    }
}

