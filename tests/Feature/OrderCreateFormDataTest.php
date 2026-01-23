<?php

namespace Tests\Feature;

use App\Models\Moderator;
use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use App\Utility\Enums\StatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCreateFormDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_separate_orders_for_regular_custom_and_lpo_products(): void
    {
        // Create a site manager (moderator) user
        /** @var \App\Models\Moderator $moderator */
        $moderator = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::SiteSupervisor,
        ]);

        Sanctum::actingAs($moderator, ['*']);

        // Create active store managers for routing
        $storeManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::StoreManager,
        ]);

        $workshopManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::WorkshopStoreManager,
        ]);

        // Create a site for this site manager
        $site = Site::factory()->create([
            'site_manager_id' => $moderator->id,
        ]);

        // Create a regular store product
        $regularProduct = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'status' => 1,
        ]);

        // Create an LPO product
        $lpoProduct = Product::factory()->create([
            'store' => StoreEnum::LPO,
            'status' => 1,
        ]);

        // Prepare multipart/form-data payload
        $payload = [
            'site_id' => $site->id,
            'notes' => 'Test order with regular, custom and LPO products',
            'expected_delivery_date' => now()->format('d/m/Y'),
            'priority' => PriorityEnum::High->value,
            'products' => [
                [
                    'product_id' => $regularProduct->id,
                    'quantity' => 2,
                    'is_custom' => 0,
                ],
                [
                    // Custom product (no stock check, stored as custom record)
                    'is_custom' => 1,
                    'custom_note' => 'Custom product example',
                    'h1' => 10,
                    'w1' => 20,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $lpoProduct->id,
                    'quantity' => 3,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson(
            '/api/v1/sites/order-request/form-data',
            $payload
        );

        $response->assertStatus(200);

        // Assert 3 orders created: 1 regular, 1 custom-only, 1 LPO
        $this->assertEquals(3, Order::count());

        $orders = Order::orderBy('id')->get();

        $regularOrders = $orders->where('is_lpo', false)
            ->filter(fn (Order $order) => $order->products()->exists() && !$order->customProducts()->exists());

        $customOrders = $orders->where('is_lpo', false)
            ->filter(fn (Order $order) => !$order->products()->exists() && $order->customProducts()->exists());

        $lpoOrders = $orders->where('is_lpo', true);

        $this->assertCount(1, $regularOrders, 'Expected one regular products order');
        $this->assertCount(1, $customOrders, 'Expected one custom products order');
        $this->assertCount(1, $lpoOrders, 'Expected one LPO order');

        $regularOrder = $regularOrders->first();
        $customOrder = $customOrders->first();
        $lpoOrder = $lpoOrders->first();

        // Regular order: non-LPO, non-custom, routed to StoreManager
        $this->assertFalse((bool) $regularOrder->is_lpo);
        $this->assertFalse((bool) $regularOrder->is_custom_product);
        $this->assertEquals($storeManager->id, $regularOrder->store_manager_id);
        $this->assertEquals(1, $regularOrder->products()->count());
        $this->assertEquals(0, $regularOrder->customProducts()->count());

        // Custom order: non-LPO, custom-only, routed to WorkshopStoreManager
        $this->assertFalse((bool) $customOrder->is_lpo);
        $this->assertTrue((bool) $customOrder->is_custom_product);
        $this->assertEquals($workshopManager->id, $customOrder->store_manager_id);
        $this->assertEquals(0, $customOrder->products()->count());
        $this->assertEquals(1, $customOrder->customProducts()->count());

        // LPO order: LPO flag true, no store manager, not custom
        $this->assertTrue((bool) $lpoOrder->is_lpo);
        $this->assertFalse((bool) $lpoOrder->is_custom_product);
        $this->assertNull($lpoOrder->store_manager_id);
        $this->assertEquals(1, $lpoOrder->products()->count());
        $this->assertEquals(0, $lpoOrder->customProducts()->count());
    }
}


