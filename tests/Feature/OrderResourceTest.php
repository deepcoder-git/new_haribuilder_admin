<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Moderator;
use App\Models\Order;
use App\Models\OrderCustomProduct;
use App\Models\OrderCustomProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Site;
use App\Src\Api\Modules\StoreManagement\Resources\OrderResource;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use App\Utility\Enums\StoreEnum;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Moderator $workshopStoreManager;
    protected Moderator $hardwareStoreManager;
    protected Moderator $siteManager;
    protected Site $site;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');

        // Create site manager
        $this->siteManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::SiteSupervisor->value,
        ]);

        // Create store managers
        $this->workshopStoreManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::WorkshopStoreManager->value,
        ]);

        $this->hardwareStoreManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::StoreManager->value,
        ]);

        // Create site
        $this->site = Site::factory()->create([
            'site_manager_id' => $this->siteManager->id,
        ]);

        // Create category
        $this->category = Category::factory()->create();
    }

    /**
     * Test OrderResource with Workshop Store Manager - Regular Products Only
     */
    public function test_order_resource_workshop_manager_regular_products(): void
    {
        // Create order with workshop product status
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
            'expected_delivery_date' => Carbon::today(),
        ]);

        // Create workshop product
        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
            'available_qty' => 100,
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 10]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertEquals($order->id, $response['id']);
        $this->assertArrayHasKey('products', $response);
        $this->assertCount(1, $response['products']);
        $this->assertEquals($product->id, $response['products'][0]['product_id']);
        $this->assertEquals(10, $response['products'][0]['quantity']);
        $this->assertEquals('Workshop Store', $response['products'][0]['type_name']);
    }

    /**
     * Test OrderResource with Hardware Store Manager - Regular Products Only
     */
    public function test_order_resource_hardware_manager_regular_products(): void
    {
        // Create order with hardware product status
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['hardware' => 'pending'],
            'expected_delivery_date' => Carbon::today(),
        ]);

        // Create hardware product
        $product = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
            'available_qty' => 50,
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 5]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertCount(1, $response['products']);
        $this->assertEquals('Hardware Store', $response['products'][0]['type_name']);
    }

    /**
     * Test OrderResource with Products Having Materials
     */
    public function test_order_resource_products_with_materials(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
        ]);

        // Create material
        $material = Product::factory()->create([
            'is_product' => 0,
            'category_id' => $this->category->id,
            'product_name' => 'Test Material',
        ]);

        // Create product with material
        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
            'available_qty' => 100,
        ]);

        // Attach material to product
        $product->materials()->attach($material->id, [
            'quantity' => 2.5,
            'unit_type' => 'kg',
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 10]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'products.materials.category', 'products.materials.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertCount(1, $response['products']);
        $this->assertArrayHasKey('materials', $response['products'][0]);
        $this->assertCount(1, $response['products'][0]['materials']);
        $this->assertEquals($material->id, $response['products'][0]['materials'][0]['material_id']);
        $this->assertEquals(25.0, $response['products'][0]['materials'][0]['quantity']); // 2.5 * 10
    }

    /**
     * Test OrderResource with Products Having Images
     */
    public function test_order_resource_products_with_images(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['hardware' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
            'available_qty' => 50,
        ]);

        // Create product images
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_path' => 'https://example.com/image1.jpg',
            'order' => 1,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_path' => 'https://example.com/image2.jpg',
            'order' => 2,
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 5]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('images', $response['products'][0]);
        $this->assertCount(2, $response['products'][0]['images']);
        $this->assertStringContainsString('image1.jpg', $response['products'][0]['images'][0]);
    }

    /**
     * Test OrderResource with Custom Products - Workshop Manager
     */
    public function test_order_resource_custom_products_workshop_manager(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending', 'custom' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        // Create custom product
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_details' => [
                'product_id' => $product->id,
                'quantity' => 5,
                'unit_id' => 1,
                'materials' => [],
            ],
            'custom_note' => 'Test custom note',
        ]);

        // Create custom product images
        OrderCustomProductImage::factory()->create([
            'order_custom_product_id' => $customProduct->id,
            'image_path' => 'custom/image1.jpg',
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $customProducts = collect($response['products'])->where('is_custom', 1);
        $this->assertGreaterThan(0, $customProducts->count());
        $customProductData = $customProducts->first();
        $this->assertEquals(1, $customProductData['is_custom']);
        $this->assertEquals('Test custom note', $customProductData['custom_note']);
        $this->assertArrayHasKey('custom_images', $customProductData);
    }

    /**
     * Test OrderResource with Custom Products - Hardware Manager
     */
    public function test_order_resource_custom_products_hardware_manager(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['hardware' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
        ]);

        // Create custom product
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_details' => [
                'product_id' => $product->id,
                'quantity' => 3,
                'materials' => [],
            ],
            'custom_note' => 'Hardware custom product',
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
    }

    /**
     * Test OrderResource with Custom Products Having Connected Products
     */
    public function test_order_resource_custom_products_with_connected_products(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending', 'custom' => 'pending'],
        ]);

        $connectedProduct = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
            'available_qty' => 50,
        ]);

        $displayProduct = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        // Create custom product with connected products
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_ids' => [$connectedProduct->id],
            'product_details' => [
                'product_id' => $displayProduct->id,
                'quantity' => 5,
                'connected_products' => [
                    [
                        'product_id' => $connectedProduct->id,
                        'quantity' => 2,
                    ],
                ],
                'materials' => [],
            ],
        ]);

        // Attach connected product to order as regular product too
        $order->products()->attach($connectedProduct->id, ['quantity' => 2]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
    }

    /**
     * Test OrderResource with Custom Products Having Materials with Measurements
     */
    public function test_order_resource_custom_products_with_materials_measurements(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending', 'custom' => 'pending'],
        ]);

        $material = Product::factory()->create([
            'is_product' => 0,
            'category_id' => $this->category->id,
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        // Create custom product with materials having measurements array
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_details' => [
                'product_id' => $product->id,
                'quantity' => 5,
                'materials' => [
                    [
                        'material_id' => $material->id,
                        'actual_pcs' => 10,
                        'measurements' => [100, 200, 300, 400],
                        'cal_qty' => 25.5,
                    ],
                ],
            ],
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $customProducts = collect($response['products'])->where('is_custom', 1);
        $this->assertGreaterThan(0, $customProducts->count());
        $customProductData = $customProducts->first();
        $this->assertArrayHasKey('materials', $customProductData);
        $this->assertCount(1, $customProductData['materials']);
        $this->assertArrayHasKey('measurements', $customProductData['materials'][0]);
        $this->assertEquals([100, 200, 300, 400], $customProductData['materials'][0]['measurements']);
        $this->assertEquals(25.5, $customProductData['materials'][0]['cal_qty']);
    }

    /**
     * Test OrderResource with Custom Products Having Materials with m* Fields
     */
    public function test_order_resource_custom_products_with_materials_m_fields(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending', 'custom' => 'pending'],
        ]);

        $material = Product::factory()->create([
            'is_product' => 0,
            'category_id' => $this->category->id,
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        // Create custom product with materials having m* fields
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_details' => [
                'product_id' => $product->id,
                'quantity' => 5,
                'materials' => [
                    [
                        'material_id' => $material->id,
                        'actual_pcs' => 10,
                        'm1' => 100,
                        'm2' => 200,
                        'm3' => 300,
                        'm4' => 400,
                    ],
                ],
            ],
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $customProducts = collect($response['products'])->where('is_custom', 1);
        $this->assertGreaterThan(0, $customProducts->count());
        $customProductData = $customProducts->first();
        $this->assertArrayHasKey('materials', $customProductData);
        $this->assertCount(1, $customProductData['materials']);
        $this->assertArrayHasKey('measurements', $customProductData['materials'][0]);
        $this->assertEquals([100, 200, 300, 400], $customProductData['materials'][0]['measurements']);
    }

    /**
     * Test OrderResource with Empty Products
     */
    public function test_order_resource_empty_products(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertIsIterable($response['products']);
    }

    /**
     * Test OrderResource with Null Relationships
     */
    public function test_order_resource_null_relationships(): void
    {
        $order = Order::factory()->create([
            'site_id' => null,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => null,
            'available_qty' => 0,
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 5]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions - should not throw errors
        $this->assertIsArray($response);
        $this->assertNull($response['site_id']);
        $this->assertNull($response['site_name']);
        $this->assertArrayHasKey('products', $response);
        if (count($response['products']) > 0) {
            $this->assertNull($response['products'][0]['category']);
        }
    }

    /**
     * Test OrderResource Date Formatting - Today
     */
    public function test_order_resource_date_formatting_today(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
            'expected_delivery_date' => Carbon::today(),
            'created_at' => Carbon::today(),
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertEquals('Today', $response['expected_delivery_date']);
        $this->assertEquals('Today', $response['requested_date']);
    }

    /**
     * Test OrderResource Date Formatting - Yesterday
     */
    public function test_order_resource_date_formatting_yesterday(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
            'expected_delivery_date' => Carbon::yesterday(),
            'created_at' => Carbon::yesterday(),
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertEquals('Yesterday', $response['expected_delivery_date']);
        $this->assertEquals('Yesterday', $response['requested_date']);
    }

    /**
     * Test OrderResource Date Formatting - Other Date
     */
    public function test_order_resource_date_formatting_other_date(): void
    {
        $date = Carbon::now()->subDays(5);
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
            'expected_delivery_date' => $date,
            'created_at' => $date,
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertStringContainsString('/', $response['expected_delivery_date']);
        $this->assertStringContainsString('/', $response['requested_date']);
    }

    /**
     * Test OrderResource with Out of Stock Products
     */
    public function test_order_resource_out_of_stock_products(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['hardware' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
            'available_qty' => 0,
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 5]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertEquals(0, $response['products'][0]['available_qty']);
        $this->assertEquals(1, $response['products'][0]['out_of_stock']);
    }

    /**
     * Test OrderResource with Mixed Regular and Custom Products
     */
    public function test_order_resource_mixed_regular_and_custom_products(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending', 'custom' => 'pending'],
        ]);

        // Regular product
        $regularProduct = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
            'available_qty' => 100,
        ]);

        // Custom product
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_details' => [
                'product_id' => $regularProduct->id,
                'quantity' => 5,
                'materials' => [],
            ],
        ]);

        // Attach regular product to order
        $order->products()->attach($regularProduct->id, ['quantity' => 10]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertGreaterThan(0, count($response['products']));
        
        $regularProducts = collect($response['products'])->where('is_custom', 0);
        $customProducts = collect($response['products'])->where('is_custom', 1);
        
        $this->assertGreaterThan(0, $regularProducts->count());
        $this->assertGreaterThan(0, $customProducts->count());
    }

    /**
     * Test OrderResource with Products Having Multiple Materials
     */
    public function test_order_resource_products_multiple_materials(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
        ]);

        // Create materials
        $material1 = Product::factory()->create([
            'is_product' => 0,
            'category_id' => $this->category->id,
            'product_name' => 'Material 1',
        ]);

        $material2 = Product::factory()->create([
            'is_product' => 0,
            'category_id' => $this->category->id,
            'product_name' => 'Material 2',
        ]);

        // Create product with multiple materials
        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
            'available_qty' => 100,
        ]);

        // Attach materials to product
        $product->materials()->attach($material1->id, [
            'quantity' => 1.5,
            'unit_type' => 'kg',
        ]);

        $product->materials()->attach($material2->id, [
            'quantity' => 2.0,
            'unit_type' => 'pcs',
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 10]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'products.materials.category', 'products.materials.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertCount(1, $response['products']);
        $this->assertArrayHasKey('materials', $response['products'][0]);
        $this->assertCount(2, $response['products'][0]['materials']);
        $this->assertEquals(15.0, $response['products'][0]['materials'][0]['quantity']); // 1.5 * 10
        $this->assertEquals(20.0, $response['products'][0]['materials'][1]['quantity']); // 2.0 * 10
    }

    /**
     * Test OrderResource with Products Having HTTP Image URLs
     */
    public function test_order_resource_products_http_image_urls(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['hardware' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
            'available_qty' => 50,
        ]);

        // Create product images with HTTP URLs
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_path' => 'http://example.com/image1.jpg',
            'order' => 1,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'image_path' => 'https://example.com/image2.jpg',
            'order' => 2,
        ]);

        // Attach product to order
        $order->products()->attach($product->id, ['quantity' => 5]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertArrayHasKey('images', $response['products'][0]);
        $this->assertCount(2, $response['products'][0]['images']);
        $this->assertStringContainsString('http://example.com/image1.jpg', $response['products'][0]['images'][0]);
        $this->assertStringContainsString('https://example.com/image2.jpg', $response['products'][0]['images'][1]);
    }

    /**
     * Test OrderResource with Custom Products Having HTTP Image URLs
     */
    public function test_order_resource_custom_products_http_image_urls(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending', 'custom' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        // Create custom product
        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'product_details' => [
                'product_id' => $product->id,
                'quantity' => 5,
                'materials' => [],
            ],
        ]);

        // Create custom product images with HTTP URLs
        OrderCustomProductImage::factory()->create([
            'order_custom_product_id' => $customProduct->id,
            'image_path' => 'https://example.com/custom1.jpg',
        ]);

        OrderCustomProductImage::factory()->create([
            'order_custom_product_id' => $customProduct->id,
            'image_path' => 'http://example.com/custom2.jpg',
        ]);

        // Load relationships
        $order->load('products.category', 'products.productImages', 'customProducts.images', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $customProducts = collect($response['products'])->where('is_custom', 1);
        $this->assertGreaterThan(0, $customProducts->count());
        $customProductData = $customProducts->first();
        $this->assertArrayHasKey('custom_images', $customProductData);
        $this->assertGreaterThan(0, count($customProductData['custom_images']));
    }

    /**
     * Test OrderResource Product Status Retrieval
     */
    public function test_order_resource_product_status_retrieval(): void
    {
        // Test with hardware status
        $order1 = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Approved,
            'product_status' => ['hardware' => 'approved'],
        ]);

        $product1 = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
        ]);

        $order1->products()->attach($product1->id, ['quantity' => 5]);
        $order1->load('products.category', 'products.productImages', 'site', 'customProducts');

        $resource1 = new OrderResource($order1);
        $response1 = $resource1->toArray(Request::create('/test'));

        $this->assertIsArray($response1);
        if (count($response1['products']) > 0) {
            $this->assertArrayHasKey('product_status', $response1['products'][0]);
        }

        // Test with workshop status
        $order2 = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
        ]);

        $product2 = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        $order2->products()->attach($product2->id, ['quantity' => 5]);
        $order2->load('products.category', 'products.productImages', 'site', 'customProducts');

        $resource2 = new OrderResource($order2);
        $response2 = $resource2->toArray(Request::create('/test'));

        $this->assertIsArray($response2);
        if (count($response2['products']) > 0) {
            $this->assertArrayHasKey('product_status', $response2['products'][0]);
        }
    }

    /**
     * Test OrderResource with Null Store Manager
     */
    public function test_order_resource_null_store_manager(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => null, // No product status
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        $order->products()->attach($product->id, ['quantity' => 5]);
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Should not throw errors
        $this->assertIsArray($response);
        $this->assertArrayHasKey('store_manager_role', $response);
    }

    /**
     * Test OrderResource Response Structure
     */
    public function test_order_resource_response_structure(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['workshop' => 'pending'],
            'priority' => PriorityEnum::High,
            'note' => 'Test note',
            'rejected_note' => null,
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'category_id' => $this->category->id,
        ]);

        $order->products()->attach($product->id, ['quantity' => 5]);
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assert all required fields exist
        $requiredFields = [
            'id',
            'site_id',
            'site_name',
            'site_manager_id',
            'store_manager_role',
            'store_manager_id',
            'site_location',
            'status',
            'delivery_status',
            'priority',
            'note',
            'rejected_note',
            'expected_delivery_date',
            'requested_date',
            'created_at',
            'updated_at',
            'products',
            'driver_name',
            'vehicle_number',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $response, "Missing field: {$field}");
        }

        // Assert products structure
        $products = $response['products'];
        $this->assertIsIterable($products);
        if (is_array($products) ? count($products) > 0 : $products->count() > 0) {
            $productFields = [
                'product_id',
                'product_name',
                'quantity',
                'unit_type',
                'category',
                'type_name',
                'product_status',
                'is_custom',
                'custom_note',
                'custom_image',
                'custom_images',
                'images',
                'materials',
                'available_qty',
                'out_of_stock',
            ];

            foreach ($productFields as $field) {
                $this->assertArrayHasKey($field, $response['products'][0], "Missing product field: {$field}");
            }
        }
    }

    /**
     * Test OrderResource with Products Having Fallback Image
     */
    public function test_order_resource_products_fallback_image(): void
    {
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
            'status' => OrderStatusEnum::Pending,
            'product_status' => ['hardware' => 'pending'],
        ]);

        $product = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'category_id' => $this->category->id,
            'image' => 'products/test-image.jpg',
            'available_qty' => 50,
        ]);

        // No product images, should use fallback
        $order->products()->attach($product->id, ['quantity' => 5]);
        $order->load('products.category', 'products.productImages', 'site', 'customProducts');

        // Create resource
        $resource = new OrderResource($order);
        $request = Request::create('/test');
        $response = $resource->toArray($request);

        // Assertions
        $this->assertIsArray($response);
        $this->assertArrayHasKey('products', $response);
        $this->assertCount(1, $response['products']);
        $this->assertArrayHasKey('images', $response['products'][0]);
        $this->assertGreaterThan(0, count($response['products'][0]['images']));
    }
}
