<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\Moderator;
use App\Models\OrderCustomProduct;
use App\Services\StockService;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seed a set of orders that cover all main combinations for testing:
 * - Hardware only order
 * - Warehouse only order
 * - LPO only order
 * - Mixed hardware + warehouse
 * - Mixed hardware + warehouse + LPO
 * - Orders containing custom products (with and without connected materials)
 *
 * This seeder focuses on creating realistic but minimal data so that
 * admin/store-manager/transport flows and API resources can be tested.
 */
class OrderTestSeeder extends Seeder
{
    protected StockService $stockService;

    public function __construct()
    {
        $this->stockService = app(StockService::class);
    }

    public function run(): void
    {
        $this->command?->info('Seeding test orders for all combinations...');

        $site = Site::first();
        if (!$site) {
            $this->command?->warn('No sites found. Skipping OrderTestSeeder.');
            return;
        }

        // Prefer a Site Supervisor for seeding orders (closest to "site manager" in roles)
        $siteManager = Moderator::where('role', RoleEnum::SiteSupervisor->value)->first()
            ?? Moderator::first();

        $now = Carbon::now();

        // Helper to pick one product per store type
        $hardwareProduct = Product::where('store', StoreEnum::HardwareStore->value)->first();
        $warehouseProduct = Product::where('store', StoreEnum::WarehouseStore->value)->first();
        $lpoProduct = Product::where('store', StoreEnum::LPO->value)->first();

        // Fallbacks: just grab any products if specific store types don't exist yet
        $fallbackProduct = Product::first();

        if (!$hardwareProduct) {
            $hardwareProduct = $fallbackProduct;
        }
        if (!$warehouseProduct) {
            $warehouseProduct = $fallbackProduct;
        }
        if (!$lpoProduct) {
            $lpoProduct = $fallbackProduct;
        }

        if (!$hardwareProduct || !$warehouseProduct || !$lpoProduct) {
            $this->command?->warn('Not enough products found to seed all order types. Skipping.');
            return;
        }

        // Ensure products have sufficient stock before creating orders
        $this->ensureProductStock($hardwareProduct, 50, $site->id);
        $this->ensureProductStock($warehouseProduct, 50, $site->id);
        // LPO products don't need stock validation, but we can still ensure they have stock for consistency
        $this->ensureProductStock($lpoProduct, 20, $site->id);

        DB::beginTransaction();

        try {
            // 1) Hardware only order
            $hardwareQuantity = 5;
            if (!$this->checkStockAvailability($hardwareProduct, $hardwareQuantity, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($hardwareProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($hardwareProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for hardware product #{$hardwareProduct->id}. Available: {$availableStock}, Requested: {$hardwareQuantity}. Adjusting quantity to {$availableStock}.");
                $hardwareQuantity = max(1, $availableStock); // Use available stock or minimum 1
            }
            
            $hardwareOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(2),
                'drop_location' => $site->address ?? 'Test Hardware Address',
                'priority' => PriorityEnum::High->value,
                'note' => 'Test hardware-only order',
                'status' => OrderStatusEnum::Pending->value,
                'store' => StoreEnum::HardwareStore->value,
                'is_completed' => false,
            ]);
            DB::table('order_products')->insert([
                'order_id' => $hardwareOrder->id,
                'product_id' => $hardwareProduct->id,
                'quantity' => $hardwareQuantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 2) Warehouse only order
            $warehouseQuantity = 10;
            if (!$this->checkStockAvailability($warehouseProduct, $warehouseQuantity, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($warehouseProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($warehouseProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for warehouse product #{$warehouseProduct->id}. Available: {$availableStock}, Requested: {$warehouseQuantity}. Adjusting quantity to {$availableStock}.");
                $warehouseQuantity = max(1, $availableStock); // Use available stock or minimum 1
            }
            
            $warehouseOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(3),
                'drop_location' => $site->address ?? 'Test Warehouse Address',
                'priority' => PriorityEnum::Medium->value,
                'note' => 'Test warehouse-only order',
                'status' => OrderStatusEnum::Pending->value,
                'store' => StoreEnum::WarehouseStore->value,
                'is_completed' => false,
            ]);
            DB::table('order_products')->insert([
                'order_id' => $warehouseOrder->id,
                'product_id' => $warehouseProduct->id,
                'quantity' => $warehouseQuantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 3) LPO only order
            $lpoOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(5),
                'drop_location' => $site->address ?? 'Test LPO Address',
                'priority' => PriorityEnum::Low->value,
                'note' => 'Test LPO-only order',
                'status' => OrderStatusEnum::Pending->value,
                'store' => StoreEnum::LPO->value,
                'is_lpo' => true,
                'is_completed' => false,
            ]);
            DB::table('order_products')->insert([
                'order_id' => $lpoOrder->id,
                'product_id' => $lpoProduct->id,
                'quantity' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 4) Mixed hardware + warehouse
            $mixedHWQuantity1 = 4;
            $mixedHWQuantity2 = 6;
            if (!$this->checkStockAvailability($hardwareProduct, $mixedHWQuantity1, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($hardwareProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($hardwareProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for hardware product #{$hardwareProduct->id} in mixed order. Available: {$availableStock}, Requested: {$mixedHWQuantity1}. Adjusting quantity to {$availableStock}.");
                $mixedHWQuantity1 = max(1, $availableStock);
            }
            if (!$this->checkStockAvailability($warehouseProduct, $mixedHWQuantity2, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($warehouseProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($warehouseProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for warehouse product #{$warehouseProduct->id} in mixed order. Available: {$availableStock}, Requested: {$mixedHWQuantity2}. Adjusting quantity to {$availableStock}.");
                $mixedHWQuantity2 = max(1, $availableStock);
            }
            
            $mixedHWOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(4),
                'drop_location' => $site->address ?? 'Test Mixed HW Address',
                'priority' => PriorityEnum::High->value,
                'note' => 'Test mixed hardware + warehouse order',
                'status' => OrderStatusEnum::Pending->value,
                'store' => StoreEnum::HardwareStore->value,
                'is_completed' => false,
            ]);
            DB::table('order_products')->insert([
                [
                    'order_id' => $mixedHWOrder->id,
                    'product_id' => $hardwareProduct->id,
                    'quantity' => $mixedHWQuantity1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'order_id' => $mixedHWOrder->id,
                    'product_id' => $warehouseProduct->id,
                    'quantity' => $mixedHWQuantity2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // 5) Mixed hardware + warehouse + LPO
            $mixedHWLpoQuantity1 = 2;
            $mixedHWLpoQuantity2 = 8;
            $mixedHWLpoQuantity3 = 5; // LPO - no stock check needed
            
            if (!$this->checkStockAvailability($hardwareProduct, $mixedHWLpoQuantity1, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($hardwareProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($hardwareProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for hardware product #{$hardwareProduct->id} in mixed HWLPO order. Available: {$availableStock}, Requested: {$mixedHWLpoQuantity1}. Adjusting quantity to {$availableStock}.");
                $mixedHWLpoQuantity1 = max(1, $availableStock);
            }
            if (!$this->checkStockAvailability($warehouseProduct, $mixedHWLpoQuantity2, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($warehouseProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($warehouseProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for warehouse product #{$warehouseProduct->id} in mixed HWLPO order. Available: {$availableStock}, Requested: {$mixedHWLpoQuantity2}. Adjusting quantity to {$availableStock}.");
                $mixedHWLpoQuantity2 = max(1, $availableStock);
            }
            
            $mixedHWLpoOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(6),
                'drop_location' => $site->address ?? 'Test Mixed HW+Warehouse+LPO Address',
                'priority' => PriorityEnum::High->value,
                'note' => 'Test mixed hardware + warehouse + LPO order',
                'status' => OrderStatusEnum::Pending->value,
                'store' => StoreEnum::HardwareStore->value,
                'is_lpo' => true,
                'is_completed' => false,
            ]);
            DB::table('order_products')->insert([
                [
                    'order_id' => $mixedHWLpoOrder->id,
                    'product_id' => $hardwareProduct->id,
                    'quantity' => $mixedHWLpoQuantity1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'order_id' => $mixedHWLpoOrder->id,
                    'product_id' => $warehouseProduct->id,
                    'quantity' => $mixedHWLpoQuantity2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'order_id' => $mixedHWLpoOrder->id,
                    'product_id' => $lpoProduct->id,
                    'quantity' => $mixedHWLpoQuantity3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // 6) Order with a custom product (site-manager style) plus connected materials
            $customOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(7),
                'drop_location' => $site->address ?? 'Test Custom Product Address',
                'priority' => PriorityEnum::High->value,
                'note' => 'Test order with custom product and materials',
                'status' => OrderStatusEnum::Pending->value,
                'store' => StoreEnum::WarehouseStore->value,
                'is_custom_product' => true,
                'is_completed' => false,
            ]);

            // Attach at least one normal warehouse product so splitting logic has regular items
            $customOrderQuantity = 5;
            if (!$this->checkStockAvailability($warehouseProduct, $customOrderQuantity, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($warehouseProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($warehouseProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for warehouse product #{$warehouseProduct->id} in custom order. Available: {$availableStock}, Requested: {$customOrderQuantity}. Adjusting quantity to {$availableStock}.");
                $customOrderQuantity = max(1, $availableStock);
            }
            
            DB::table('order_products')->insert([
                'order_id' => $customOrder->id,
                'product_id' => $warehouseProduct->id,
                'quantity' => $customOrderQuantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create a custom product row
            $customProduct = OrderCustomProduct::create([
                'order_id' => $customOrder->id,
                'custom_note' => 'Site-manager custom product for testing',
                'product_details' => [
                    'materials' => [],
                    'quantity' => 0,
                ],
            ]);

            // 7) Single mixed order that has hardware + warehouse + LPO + a custom product
            $mixedFullOrder = Order::create([
                'site_manager_id' => $siteManager?->id,
                'site_id' => $site->id,
                'sale_date' => $now,
                'expected_delivery_date' => $now->copy()->addDays(8),
                'drop_location' => $site->address ?? 'Test Mixed Full Address',
                'priority' => PriorityEnum::High->value,
                'note' => 'Test mixed order with hardware, warehouse, LPO and custom product',
                'status' => OrderStatusEnum::Pending->value,
                // From UI point of view, this order will appear in all three groups
                // because items are split by product store type, not this field
                'store' => StoreEnum::HardwareStore->value,
                'is_lpo' => true,
                'is_custom_product' => true,
                'is_completed' => false,
            ]);

            // Attach one product from each store type
            $mixedFullQuantity1 = 3;
            $mixedFullQuantity2 = 7;
            $mixedFullQuantity3 = 4; // LPO - no stock check needed
            
            if (!$this->checkStockAvailability($hardwareProduct, $mixedFullQuantity1, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($hardwareProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($hardwareProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for hardware product #{$hardwareProduct->id} in mixed full order. Available: {$availableStock}, Requested: {$mixedFullQuantity1}. Adjusting quantity to {$availableStock}.");
                $mixedFullQuantity1 = max(1, $availableStock);
            }
            if (!$this->checkStockAvailability($warehouseProduct, $mixedFullQuantity2, $site->id)) {
                $generalStock = $this->stockService->getCurrentStock($warehouseProduct->id, null);
                $siteStock = $this->stockService->getCurrentStock($warehouseProduct->id, $site->id);
                $availableStock = $generalStock + $siteStock;
                $this->command?->warn("  ⚠ Insufficient stock for warehouse product #{$warehouseProduct->id} in mixed full order. Available: {$availableStock}, Requested: {$mixedFullQuantity2}. Adjusting quantity to {$availableStock}.");
                $mixedFullQuantity2 = max(1, $availableStock);
            }
            
            DB::table('order_products')->insert([
                [
                    'order_id' => $mixedFullOrder->id,
                    'product_id' => $hardwareProduct->id,
                    'quantity' => $mixedFullQuantity1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'order_id' => $mixedFullOrder->id,
                    'product_id' => $warehouseProduct->id,
                    'quantity' => $mixedFullQuantity2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'order_id' => $mixedFullOrder->id,
                    'product_id' => $lpoProduct->id,
                    'quantity' => $mixedFullQuantity3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // Create a custom product linked to this mixed order,
            // with product_ids pointing at the warehouse product so
            // the grey "Connected Warehouse Products" section has data
            OrderCustomProduct::create([
                'order_id' => $mixedFullOrder->id,
                'custom_note' => 'Mixed-order custom product',
                'product_details' => [
                    'materials' => [],
                    'quantity' => 1,
                ],
                'product_ids' => [$warehouseProduct->id],
            ]);

            DB::commit();

            $this->command?->info('✅ Test orders seeded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error('OrderTestSeeder failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ensure product has sufficient stock, creating stock entry if needed
     * 
     * @param Product $product Product to check
     * @param int $minimumStock Minimum stock quantity required
     * @param int|null $siteId Site ID (null for general stock)
     */
    protected function ensureProductStock(Product $product, int $minimumStock, ?int $siteId = null): void
    {
        // Skip stock check for LPO products
        if ($product->store && $product->store === StoreEnum::LPO) {
            return;
        }

        $generalStock = $this->stockService->getCurrentStock($product->id, null);
        $siteStock = $siteId ? $this->stockService->getCurrentStock($product->id, $siteId) : 0;
        $totalStock = $generalStock + $siteStock;

        if ($totalStock < $minimumStock) {
            $neededStock = $minimumStock - $totalStock;
            
            // Create stock entry for general stock if needed
            if ($generalStock < $minimumStock) {
                $this->stockService->adjustStock(
                    $product->id,
                    $neededStock,
                    'in',
                    null,
                    "Initial stock for OrderTestSeeder - ensuring minimum stock of {$minimumStock}",
                    null,
                    "Initial Stock - OrderTestSeeder",
                    ['seeder' => 'OrderTestSeeder']
                );
                $productName = $product->product_name ?? 'N/A';
                $this->command?->info("  ✓ Created stock entry for product #{$product->id} ({$productName}) - Added {$neededStock} units");
            }
        } else {
            $productName = $product->product_name ?? 'N/A';
            $this->command?->info("  ✓ Product #{$product->id} ({$productName}) has sufficient stock: {$totalStock} (General: {$generalStock}, Site: {$siteStock})");
        }
    }

    /**
     * Check if product has sufficient stock for order quantity
     * 
     * @param Product $product Product to check
     * @param int $quantity Required quantity
     * @param int|null $siteId Site ID (null for general stock)
     * @return bool True if sufficient stock available
     */
    protected function checkStockAvailability(Product $product, int $quantity, ?int $siteId = null): bool
    {
        // Skip stock check for LPO products
        if ($product->store && $product->store === StoreEnum::LPO) {
            return true;
        }

        $generalStock = $this->stockService->getCurrentStock($product->id, null);
        $siteStock = $siteId ? $this->stockService->getCurrentStock($product->id, $siteId) : 0;
        $totalStock = $generalStock + $siteStock;

        return $totalStock >= $quantity;
    }
}

