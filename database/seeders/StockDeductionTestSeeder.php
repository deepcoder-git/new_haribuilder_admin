<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\Moderator;
use App\Models\Stock;
use App\Services\StockService;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Test seeder for stock deduction scenarios when product status changes to 'approved'
 * 
 * Scenarios covered:
 * 1. Hardware product with stock 10, order quantity 5 - should deduct 5
 * 2. Warehouse product with stock 20, order quantity 10 - should deduct 10
 * 3. Multiple products in same order - all should deduct when approved
 * 4. Product with insufficient stock - should show error
 * 5. Product already deducted - should skip double deduction
 * 6. Custom products with connected materials - should deduct both
 */
class StockDeductionTestSeeder extends Seeder
{
    protected StockService $stockService;

    public function __construct()
    {
        $this->stockService = app(StockService::class);
    }

    public function run(): void
    {
        $this->command?->info('Seeding stock deduction test scenarios...');

        $site = Site::first();
        if (!$site) {
            $this->command?->warn('No sites found. Skipping StockDeductionTestSeeder.');
            return;
        }

        // Get or create a site manager
        $siteManager = Moderator::where('role', RoleEnum::SiteSupervisor->value)->first()
            ?? Moderator::first();

        if (!$siteManager) {
            $this->command?->warn('No moderators found. Skipping StockDeductionTestSeeder.');
            return;
        }

        $now = Carbon::now();

        DB::beginTransaction();

        try {
            // Scenario 1: Hardware product with stock 10, order quantity 5
            $this->command?->info('Creating Scenario 1: Hardware product (stock: 10, order: 5)');
            $hardwareProduct1 = $this->createProductWithStock('Test Hardware Product 1', StoreEnum::HardwareStore, 10);
            $order1 = $this->createOrder($siteManager, $site, 'Hardware Order - Stock 10, Order 5', PriorityEnum::High);
            DB::table('order_products')->insert([
                'order_id' => $order1->id,
                'product_id' => $hardwareProduct1->id,
                'quantity' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $order1->update(['product_status' => ['hardware' => 'pending']]);
            $this->command?->info("  ✅ Order #{$order1->id} created with product #{$hardwareProduct1->id} (qty: 5)");

            // Scenario 2: Warehouse product with stock 20, order quantity 10
            $this->command?->info('Creating Scenario 2: Warehouse product (stock: 20, order: 10)');
            $warehouseProduct1 = $this->createProductWithStock('Test Warehouse Product 1', StoreEnum::WarehouseStore, 20);
            $order2 = $this->createOrder($siteManager, $site, 'Warehouse Order - Stock 20, Order 10', PriorityEnum::Medium);
            DB::table('order_products')->insert([
                'order_id' => $order2->id,
                'product_id' => $warehouseProduct1->id,
                'quantity' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $order2->update(['product_status' => ['warehouse' => 'pending']]);
            $this->command?->info("  ✅ Order #{$order2->id} created with product #{$warehouseProduct1->id} (qty: 10)");

            // Scenario 3: Multiple products in same order
            $this->command?->info('Creating Scenario 3: Multiple products in same order');
            $hardwareProduct2 = $this->createProductWithStock('Test Hardware Product 2', StoreEnum::HardwareStore, 15);
            $warehouseProduct2 = $this->createProductWithStock('Test Warehouse Product 2', StoreEnum::WarehouseStore, 25);
            $order3 = $this->createOrder($siteManager, $site, 'Mixed Order - Multiple Products', PriorityEnum::High);
            DB::table('order_products')->insert([
                [
                    'order_id' => $order3->id,
                    'product_id' => $hardwareProduct2->id,
                    'quantity' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'order_id' => $order3->id,
                    'product_id' => $warehouseProduct2->id,
                    'quantity' => 7,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
            $order3->update(['product_status' => ['hardware' => 'pending', 'warehouse' => 'pending']]);
            $this->command?->info("  ✅ Order #{$order3->id} created with 2 products");

            // Scenario 4: Product with insufficient stock (stock: 5, order: 10)
            $this->command?->info('Creating Scenario 4: Insufficient stock (stock: 5, order: 10)');
            $hardwareProduct3 = $this->createProductWithStock('Test Hardware Product 3 - Low Stock', StoreEnum::HardwareStore, 5);
            $order4 = $this->createOrder($siteManager, $site, 'Order - Insufficient Stock Test', PriorityEnum::High);
            DB::table('order_products')->insert([
                'order_id' => $order4->id,
                'product_id' => $hardwareProduct3->id,
                'quantity' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $order4->update(['product_status' => ['hardware' => 'pending']]);
            $this->command?->info("  ✅ Order #{$order4->id} created with product #{$hardwareProduct3->id} (qty: 10, stock: 5) - Will show error when approved");

            // Scenario 5: Already approved order (to test double deduction prevention)
            $this->command?->info('Creating Scenario 5: Already approved order (double deduction test)');
            $hardwareProduct4 = $this->createProductWithStock('Test Hardware Product 4', StoreEnum::HardwareStore, 30);
            $order5 = $this->createOrder($siteManager, $site, 'Order - Already Approved', PriorityEnum::Medium);
            DB::table('order_products')->insert([
                'order_id' => $order5->id,
                'product_id' => $hardwareProduct4->id,
                'quantity' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $order5->update([
                'product_status' => ['hardware' => 'approved'],
                'status' => OrderStatusEnum::Approved->value,
            ]);
            // Manually create a stock entry to simulate already deducted
            Stock::create([
                'product_id' => $hardwareProduct4->id,
                'site_id' => null,
                'quantity' => 22, // 30 - 8 = 22
                'adjustment_type' => 'out',
                'reference_id' => $order5->id,
                'reference_type' => Order::class,
                'notes' => "Stock deducted for Order #{$order5->id} (quantity: 8)",
                'name' => "Order #{$order5->id} - Stock Deducted (hardware)",
                'status' => true,
            ]);
            $hardwareProduct4->update(['available_qty' => 22]);
            $this->command?->info("  ✅ Order #{$order5->id} created and marked as approved (stock already deducted)");

            // Scenario 6: Large quantity order
            $this->command?->info('Creating Scenario 6: Large quantity order');
            $warehouseProduct3 = $this->createProductWithStock('Test Warehouse Product 3 - Large Stock', StoreEnum::WarehouseStore, 1000);
            $order6 = $this->createOrder($siteManager, $site, 'Order - Large Quantity', PriorityEnum::High);
            DB::table('order_products')->insert([
                'order_id' => $order6->id,
                'product_id' => $warehouseProduct3->id,
                'quantity' => 250,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $order6->update(['product_status' => ['warehouse' => 'pending']]);
            $this->command?->info("  ✅ Order #{$order6->id} created with product #{$warehouseProduct3->id} (qty: 250, stock: 1000)");

            DB::commit();

            $this->command?->info('');
            $this->command?->info('✅ Stock deduction test scenarios seeded successfully!');
            $this->command?->info('');
            $this->command?->info('Test Scenarios:');
            $this->command?->info('  1. Order #' . $order1->id . ' - Hardware (stock: 10, order: 5) - Change status to approved');
            $this->command?->info('  2. Order #' . $order2->id . ' - Warehouse (stock: 20, order: 10) - Change status to approved');
            $this->command?->info('  3. Order #' . $order3->id . ' - Mixed products - Change hardware/warehouse status to approved');
            $this->command?->info('  4. Order #' . $order4->id . ' - Insufficient stock (stock: 5, order: 10) - Should show error');
            $this->command?->info('  5. Order #' . $order5->id . ' - Already approved - Should skip double deduction');
            $this->command?->info('  6. Order #' . $order6->id . ' - Large quantity (stock: 1000, order: 250) - Change status to approved');
            $this->command?->info('');
            $this->command?->info('To test: Go to admin panel > Orders > Edit each order > Change product status to "Approved"');
            $this->command?->info('Check stock entries at: Stock Report > Click history icon on any product');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error('StockDeductionTestSeeder failed: ' . $e->getMessage());
            $this->command?->error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    protected function createProductWithStock(string $name, StoreEnum $store, int $initialStock): Product
    {
        $product = Product::create([
            'product_name' => $name,
            'store' => $store->value,
            'available_qty' => $initialStock,
            'is_product' => 1,
            'unit_type' => 'Pcs',
            'low_stock_threshold' => 10,
        ]);

        // Create initial stock entry
        $this->stockService->adjustStock(
            $product->id,
            $initialStock,
            'adjustment',
            null,
            "Initial stock for test product",
            null,
            "Initial Stock - {$name}",
            ['seeder' => 'StockDeductionTestSeeder']
        );

        return $product;
    }

    protected function createOrder(Moderator $siteManager, Site $site, string $note, PriorityEnum $priority): Order
    {
        $now = Carbon::now();

        return Order::create([
            'site_manager_id' => $siteManager->id,
            'site_id' => $site->id,
            'sale_date' => $now,
            'expected_delivery_date' => $now->copy()->addDays(2),
            'drop_location' => $site->address ?? 'Test Address',
            'priority' => $priority->value,
            'note' => $note,
            'status' => OrderStatusEnum::Pending->value,
            'is_completed' => false,
        ]);
    }
}
