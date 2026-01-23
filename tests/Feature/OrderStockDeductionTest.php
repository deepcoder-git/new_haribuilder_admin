<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Utility\Enums\StoreEnum;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected Product $hardwareProduct;
    protected Product $warehouseProduct;
    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        // Hardware product
        $this->hardwareProduct = Product::create([
            'product_name' => 'Hardware Product',
            'store' => StoreEnum::HardwareStore,
            'status' => true,
            'is_product' => 1,
        ]);

        // Warehouse product
        $this->warehouseProduct = Product::create([
            'product_name' => 'Warehouse Product',
            'store' => StoreEnum::WarehouseStore,
            'status' => true,
            'is_product' => 1,
        ]);

        $this->stockService = app(StockService::class);

        // Seed initial stock so we can see deductions
        $this->stockService->adjustStock(
            $this->hardwareProduct->id,
            100,
            'adjustment',
            null,
            'Seed hardware stock'
        );

        $this->stockService->adjustStock(
            $this->warehouseProduct->id,
            200,
            'adjustment',
            null,
            'Seed warehouse stock'
        );
    }

    protected function getCurrentStock(Product $product): int
    {
        return (int) Stock::where('product_id', $product->id)
            ->where('status', true)
            ->latest('created_at')
            ->latest('id')
            ->value('quantity');
    }

    public function test_stock_out_deducts_quantity_for_hardware_product(): void
    {
        $hardwareBefore = $this->getCurrentStock($this->hardwareProduct);

        // Deduct 10 units from hardware product
        $this->stockService->adjustStock(
            $this->hardwareProduct->id,
            10,
            'out',
            null,
            'Test hardware stock deduction'
        );

        $this->assertEquals($hardwareBefore, $this->getCurrentStock($this->hardwareProduct));
        $this->assertEquals($hardwareBefore - 10, $this->getCurrentStock($this->hardwareProduct));
    }

    public function test_stock_out_for_warehouse_does_not_affect_hardware_stock(): void
    {
        $hardwareBefore = $this->getCurrentStock($this->hardwareProduct);
        $warehouseBefore = $this->getCurrentStock($this->warehouseProduct);

        // Deduct 20 units from warehouse product
        $this->stockService->adjustStock(
            $this->warehouseProduct->id,
            20,
            'out',
            null,
            'Test warehouse stock deduction'
        );

        $this->assertEquals($hardwareBefore, $this->getCurrentStock($this->hardwareProduct));
        $this->assertEquals($warehouseBefore - 20, $this->getCurrentStock($this->warehouseProduct));
    }
}

