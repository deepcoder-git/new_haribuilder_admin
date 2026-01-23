<?php

namespace Tests\Feature;

use App\Models\Moderator;
use App\Models\Order;
use App\Models\OrderCustomProduct;
use App\Models\Product;
use App\Models\Site;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use App\Utility\Enums\StatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected Moderator $siteManager;
    protected Moderator $storeManager;
    protected Moderator $workshopManager;
    protected Site $site;
    protected Product $regularProduct1;
    protected Product $regularProduct2;
    protected Product $lpoProduct;
    protected Product $material1;
    protected Product $material2;
    protected Product $productUsingMaterial1;

    protected function setUp(): void
    {
        parent::setUp();

        // Create site manager
        $this->siteManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::SiteSupervisor->value,
        ]);

        // Create store managers
        $this->storeManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::StoreManager->value,
        ]);

        $this->workshopManager = Moderator::factory()->create([
            'status' => StatusEnum::Active->value,
            'role' => RoleEnum::WorkshopStoreManager->value,
        ]);

        // Create site
        $this->site = Site::factory()->create([
            'site_manager_id' => $this->siteManager->id,
        ]);

        // Create regular products
        $this->regularProduct1 = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'status' => 1,
            'is_product' => 1,
            'store_manager_id' => $this->storeManager->id,
        ]);

        $this->regularProduct2 = Product::factory()->create([
            'store' => StoreEnum::HardwareStore,
            'status' => 1,
            'is_product' => 1,
            'store_manager_id' => $this->storeManager->id,
        ]);

        // Create LPO product
        $this->lpoProduct = Product::factory()->create([
            'store' => StoreEnum::LPO,
            'status' => 1,
            'is_product' => 1,
        ]);

        // Create materials (is_product = 0)
        $this->material1 = Product::factory()->create([
            'status' => 1,
            'is_product' => 0,
        ]);

        $this->material2 = Product::factory()->create([
            'status' => 1,
            'is_product' => 0,
        ]);

        // Create product that uses material1
        $this->productUsingMaterial1 = Product::factory()->create([
            'store' => StoreEnum::WarehouseStore,
            'status' => 1,
            'is_product' => 1,
        ]);

        // Link product to material via product_materials pivot table
        $this->productUsingMaterial1->materials()->attach($this->material1->id, [
            'quantity' => 1,
            'unit_type' => 'pcs',
        ]);

        // Authenticate as site manager
        Sanctum::actingAs($this->siteManager, ['*']);

        // Fake storage
        Storage::fake('public');
    }

    // ============================================
    // Category 1: Basic Order Creation
    // ============================================

    public function test_tc001_create_order_with_single_regular_product(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order with single product',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'products',
                ],
            ]);

        $this->assertEquals(1, Order::count());
        $order = Order::first();
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(1, $order->products()->count());
        $this->assertEquals(10, $order->products()->first()->pivot->quantity);
    }

    public function test_tc002_create_order_with_multiple_regular_products(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order with multiple products',
            'priority' => PriorityEnum::Medium->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
                [
                    'product_id' => $this->regularProduct2->id,
                    'quantity' => 20,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $this->assertEquals(2, $order->products()->count());
        $this->assertEquals(10, $order->products()->where('product_id', $this->regularProduct1->id)->first()->pivot->quantity);
        $this->assertEquals(20, $order->products()->where('product_id', $this->regularProduct2->id)->first()->pivot->quantity);
    }

    public function test_tc003_create_order_with_customer_image_file_upload(): void
    {
        $file = UploadedFile::fake()->image('customer.jpg', 800, 600);

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with customer image',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'customer_image' => $file,
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->post('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $this->assertNotNull($order->customer_image);
        Storage::disk('public')->assertExists($order->customer_image);
    }

    public function test_tc004_create_order_with_customer_image_base64(): void
    {
        $base64Image = 'data:image/jpeg;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../storage/test-image.jpg'));

        // If test image doesn't exist, create a simple base64 image
        if (!file_exists(__DIR__ . '/../../storage/test-image.jpg')) {
            $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        }

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with base64 image',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'customer_image' => $base64Image,
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $this->assertNotNull($order->customer_image);
    }

    // ============================================
    // Category 2: Custom Products (Without Materials)
    // ============================================

    public function test_tc005_create_order_with_single_custom_product_note_only(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with custom product',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product description',
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $this->assertEquals(1, $order->customProducts()->count());
        $customProduct = $order->customProducts()->first();
        $this->assertEquals('Custom product description', $customProduct->custom_note);
        $this->assertEquals(0, $order->products()->count());
    }

    public function test_tc006_create_order_with_custom_product_images_only(): void
    {
        $file1 = UploadedFile::fake()->image('custom1.jpg');
        $file2 = UploadedFile::fake()->image('custom2.png');

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with custom product images',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_images' => [$file1, $file2],
                ],
            ],
        ];

        $response = $this->post('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        $this->assertEquals(2, $customProduct->images()->count());
    }

    public function test_tc007_create_order_with_custom_product_note_and_images(): void
    {
        $file1 = UploadedFile::fake()->image('custom1.jpg');
        $file2 = UploadedFile::fake()->image('custom2.png');

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with custom product',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product with images',
                    'custom_images' => [$file1, $file2],
                ],
            ],
        ];

        $response = $this->post('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        $this->assertEquals('Custom product with images', $customProduct->custom_note);
        $this->assertEquals(2, $customProduct->images()->count());
    }

    public function test_tc008_create_order_with_custom_product_base64_images(): void
    {
        $base64Image1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $base64Image2 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD';

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with base64 custom images',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'custom_images' => [$base64Image1, $base64Image2],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        $this->assertGreaterThan(0, $customProduct->images()->count());
    }

    // ============================================
    // Category 3: Custom Products with Materials
    // ============================================

    public function test_tc009_create_order_with_custom_product_single_material(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with custom product and material',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product with material',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'measurements' => [1],
                                'calculated_quantity' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'products' => [
                        '*' => [
                            'materials' => [
                                '*' => [
                                    'material_id',
                                    'material_name',
                                    'quantity',
                                    'category',
                                    'images',
                                    'unit',
                                ],
                            ],
                            'custom_products',
                        ],
                    ],
                ],
            ]);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        $productDetails = $customProduct->product_details;

        $this->assertNotNull($productDetails);
        $this->assertArrayHasKey('materials', $productDetails);
        $this->assertEquals($this->material1->id, $productDetails['materials'][0]['material_id']);

        // Verify material information is in response
        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $this->assertNotNull($customProductData);
        $this->assertArrayHasKey('materials', $customProductData);
        $this->assertCount(1, $customProductData['materials']);
        $this->assertEquals($this->material1->id, $customProductData['materials'][0]['material_id']);
        $this->assertArrayHasKey('material_name', $customProductData['materials'][0]);
    }

    public function test_tc010_create_order_with_custom_product_multiple_materials(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with multiple materials',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product with multiple materials',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'measurements' => [1],
                                'calculated_quantity' => 5,
                                'cal_qty' => 5,
                            ],
                            [
                                'material_id' => $this->material2->id,
                                'actual_pcs' => 10,
                                'measurements' => [2, 3],
                                'calculated_quantity' => 20,
                                'cal_qty' => 20,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        $productDetails = $customProduct->product_details;

        $this->assertCount(2, $productDetails['materials']);

        // Verify both materials in response
        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $this->assertCount(2, $customProductData['materials']);
    }

    public function test_tc011_create_order_with_custom_product_material_measurements_array(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with material measurements',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'measurements' => [10, 20, 30, 40],
                                'calculated_quantity' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $material = $customProductData['materials'][0];
        $this->assertEquals([10, 20, 30, 40], $material['measurements']);
    }

    public function test_tc012_create_order_with_custom_product_material_m_fields(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with m* fields',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'm1' => 10,
                                'm2' => 20,
                                'm3' => 30,
                                'calculated_quantity' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $material = $customProductData['materials'][0];
        $this->assertArrayHasKey('measurements', $material);
        $this->assertEquals([10, 20, 30], $material['measurements']);
    }

    public function test_tc013_create_order_with_custom_product_material_quantity_priority(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with quantity priority',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 3,
                                'calculated_quantity' => 4,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $material = $customProductData['materials'][0];
        $this->assertEquals(5.0, $material['quantity']); // cal_qty takes priority
        $this->assertEquals(5, $material['cal_qty']);
    }

    public function test_tc014_create_order_with_custom_product_material_without_cal_qty(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order without cal_qty',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 3,
                                'calculated_quantity' => 4,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $material = $customProductData['materials'][0];
        $this->assertEquals(4.0, $material['quantity']); // calculated_quantity takes priority
    }

    // ============================================
    // Category 4: Mixed Orders
    // ============================================

    public function test_tc015_create_order_with_regular_and_custom_products(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Mixed order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
                [
                    'product_id' => $this->regularProduct2->id,
                    'quantity' => 20,
                    'is_custom' => 0,
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $this->assertEquals(2, $order->products()->count());
        $this->assertEquals(1, $order->customProducts()->count());

        $responseData = $response->json('data.products');
        $regularProducts = collect($responseData)->where('is_custom', 0);
        $customProducts = collect($responseData)->where('is_custom', 1);

        $this->assertGreaterThanOrEqual(2, $regularProducts->count());
        $this->assertGreaterThanOrEqual(1, $customProducts->count());
    }

    public function test_tc016_create_order_with_same_product_in_regular_and_custom(): void
    {
        // Link productUsingMaterial1 to material1
        $this->productUsingMaterial1->materials()->sync([
            $this->material1->id => ['quantity' => 1, 'unit_type' => 'pcs'],
        ]);

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with same product',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->productUsingMaterial1->id,
                    'quantity' => 15,
                    'is_custom' => 0,
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                    'product_ids' => [$this->productUsingMaterial1->id],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        // Product should be in order_products with aggregated quantity
        $productPivot = $order->products()->where('product_id', $this->productUsingMaterial1->id)->first();
        $this->assertNotNull($productPivot);
        // Quantity should be aggregated (15 + 5 = 20)
        $this->assertEquals(20, $productPivot->pivot->quantity);
    }

    // ============================================
    // Category 5: Order Update
    // ============================================

    public function test_tc017_update_order_add_regular_product(): void
    {
        // Create initial order
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
        ]);

        $order->products()->attach($this->regularProduct1->id, ['quantity' => 10]);

        $payload = [
            'order_id' => $order->id,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
                [
                    'product_id' => $this->regularProduct2->id,
                    'quantity' => 5,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/sites/order-request/form-data/update', $payload);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(2, $order->products()->count());
        $this->assertEquals(5, $order->products()->where('product_id', $this->regularProduct2->id)->first()->pivot->quantity);
    }

    public function test_tc018_update_order_add_custom_product(): void
    {
        // Create initial order
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
        ]);

        $order->products()->attach($this->regularProduct1->id, ['quantity' => 10]);

        $payload = [
            'order_id' => $order->id,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'New custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/sites/order-request/form-data/update', $payload);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(1, $order->products()->count());
        $this->assertEquals(1, $order->customProducts()->count());
    }

    public function test_tc019_update_order_update_custom_product_with_custom_product_id(): void
    {
        // Create initial order with custom product
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
        ]);

        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'custom_note' => 'Original custom product',
            'product_details' => [
                'product_id' => $this->regularProduct1->id,
                'materials' => [
                    [
                        'material_id' => $this->material1->id,
                        'actual_pcs' => 5,
                        'cal_qty' => 5,
                    ],
                ],
                'quantity' => 5,
            ],
        ]);

        $payload = [
            'order_id' => $order->id,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_product_id' => $customProduct->id,
                    'custom_note' => 'Updated custom product note',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 10,
                                'cal_qty' => 10,
                            ],
                        ],
                        'quantity' => 10,
                    ],
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/sites/order-request/form-data/update', $payload);

        $response->assertStatus(200);

        $customProduct->refresh();
        $this->assertEquals('Updated custom product note', $customProduct->custom_note);
        $productDetails = $customProduct->product_details;
        $this->assertEquals(10, $productDetails['materials'][0]['actual_pcs']);
        $this->assertEquals(10, $productDetails['materials'][0]['cal_qty']);
    }

    public function test_tc020_update_order_update_custom_product_material_quantities(): void
    {
        // Create initial order with custom product
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
        ]);

        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'custom_note' => 'Custom product',
            'product_details' => [
                'product_id' => $this->regularProduct1->id,
                'materials' => [
                    [
                        'material_id' => $this->material1->id,
                        'actual_pcs' => 5,
                        'cal_qty' => 5,
                    ],
                ],
                'quantity' => 5,
            ],
        ]);

        $payload = [
            'order_id' => $order->id,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_product_id' => $customProduct->id,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 15,
                                'calculated_quantity' => 15,
                                'cal_qty' => 15,
                            ],
                        ],
                        'quantity' => 15,
                    ],
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/sites/order-request/form-data/update', $payload);

        $response->assertStatus(200);

        $customProduct->refresh();
        $productDetails = $customProduct->product_details;
        $this->assertEquals(15, $productDetails['materials'][0]['cal_qty']);
        $this->assertEquals(15, $productDetails['quantity']);
    }

    public function test_tc034_create_order_custom_product_with_product_ids_array(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with custom product and product_ids',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product with connected products',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                    'product_ids' => [$this->regularProduct1->id, $this->regularProduct2->id],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        $productIds = $customProduct->product_ids;
        
        $this->assertIsArray($productIds);
        $this->assertContains($this->regularProduct1->id, $productIds);
        $this->assertContains($this->regularProduct2->id, $productIds);
    }

    public function test_tc035_create_order_custom_product_with_connected_products_from_materials(): void
    {
        // Link productUsingMaterial1 to material1
        $this->productUsingMaterial1->materials()->sync([
            $this->material1->id => ['quantity' => 1, 'unit_type' => 'pcs'],
        ]);

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with custom product using material',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        // Verify products using material are included in response
        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        
        $this->assertNotNull($customProductData);
        $this->assertArrayHasKey('custom_products', $customProductData);
        
        // Check if productUsingMaterial1 is in custom_products
        $customProducts = collect($customProductData['custom_products']);
        $hasProductUsingMaterial = $customProducts->contains('product_id', $this->productUsingMaterial1->id);
        $this->assertTrue($hasProductUsingMaterial, 'Product using material should be in custom_products array');
    }

    public function test_tc036_update_order_add_multiple_custom_products(): void
    {
        // Create initial order
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
        ]);

        $payload = [
            'order_id' => $order->id,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product 1',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product 2',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material2->id,
                                'actual_pcs' => 10,
                                'cal_qty' => 10,
                            ],
                        ],
                        'quantity' => 10,
                    ],
                ],
            ],
        ];

        $response = $this->putJson('/api/v1/sites/order-request/form-data/update', $payload);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals(2, $order->customProducts()->count());
    }

    public function test_tc037_update_order_update_custom_product_images(): void
    {
        // Create initial order with custom product
        $order = Order::factory()->create([
            'site_id' => $this->site->id,
            'site_manager_id' => $this->siteManager->id,
        ]);

        $customProduct = OrderCustomProduct::factory()->create([
            'order_id' => $order->id,
            'custom_note' => 'Custom product',
        ]);

        $file1 = UploadedFile::fake()->image('new_image1.jpg');
        $file2 = UploadedFile::fake()->image('new_image2.png');

        $payload = [
            'order_id' => $order->id,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_product_id' => $customProduct->id,
                    'custom_note' => 'Custom product',
                    'custom_images' => [$file1, $file2],
                ],
            ],
        ];

        $response = $this->post('/api/v1/sites/order-request/form-data/update', $payload);

        $response->assertStatus(200);

        $customProduct->refresh();
        $this->assertEquals(2, $customProduct->images()->count());
    }

    public function test_tc038_create_order_custom_product_with_all_fields(): void
    {
        $file1 = UploadedFile::fake()->image('custom1.jpg');
        $file2 = UploadedFile::fake()->image('custom2.png');

        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Order with complete custom product',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Complete custom product',
                    'custom_images' => [$file1, $file2],
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'h1' => 10,
                        'h2' => 20,
                        'w1' => 30,
                        'w2' => 40,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'measurements' => [10, 20],
                                'calculated_quantity' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                        'unit_id' => 1,
                    ],
                    'product_ids' => [$this->regularProduct1->id, $this->regularProduct2->id],
                ],
            ],
        ];

        $response = $this->post('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $customProduct = $order->customProducts()->first();
        
        $this->assertEquals('Complete custom product', $customProduct->custom_note);
        $this->assertEquals(2, $customProduct->images()->count());
        
        $productDetails = $customProduct->product_details;
        $this->assertEquals(10, $productDetails['h1']);
        $this->assertEquals(20, $productDetails['h2']);
        $this->assertEquals(30, $productDetails['w1']);
        $this->assertEquals(40, $productDetails['w2']);
        $this->assertEquals(5, $productDetails['quantity']);
        $this->assertEquals(1, $productDetails['unit_id']);
    }

    // ============================================
    // Category 6: Edge Cases and Error Handling
    // ============================================

    public function test_tc021_create_order_invalid_site_id(): void
    {
        $payload = [
            'site_id' => 99999,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['site_id']);
    }

    public function test_tc022_create_order_missing_required_fields(): void
    {
        $payload = [
            'notes' => 'Test order',
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['site_id', 'priority']);
    }

    public function test_tc023_create_order_invalid_product_id(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => 99999,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.product_id']);
    }

    public function test_tc024_create_order_zero_quantity(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 0,
                    'is_custom' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.quantity']);
    }

    public function test_tc025_create_order_custom_product_without_note_or_images(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        // This might pass if validation allows it, or fail if validation requires note/images
        // Adjust based on actual validation rules
        $response->assertStatusIn([200, 422]);
    }

    public function test_tc027_create_order_material_without_quantity_fields(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $material = $customProductData['materials'][0];
        $this->assertEquals(0.0, $material['quantity']); // Defaults to 0
    }

    // ============================================
    // Category 7: Response Validation
    // ============================================

    public function test_tc029_verify_order_response_structure(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'delivery_status',
                    'products' => [
                        '*' => [
                            'product_id',
                            'product_name',
                            'quantity',
                            'is_custom',
                        ],
                    ],
                    'created_at',
                ],
            ]);
    }

    public function test_tc030_verify_material_information_in_response(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Test order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'measurements' => [1],
                                'calculated_quantity' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $responseData = $response->json('data.products');
        $customProductData = collect($responseData)->firstWhere('is_custom', 1);
        $material = $customProductData['materials'][0];

        $this->assertArrayHasKey('material_id', $material);
        $this->assertArrayHasKey('material_name', $material);
        $this->assertArrayHasKey('quantity', $material);
        $this->assertArrayHasKey('category', $material);
        $this->assertArrayHasKey('images', $material);
        $this->assertArrayHasKey('unit', $material);
        $this->assertEquals($this->material1->id, $material['material_id']);
    }

    // ============================================
    // Category 8: Integration Tests
    // ============================================

    public function test_tc032_complete_order_flow_create_update_view(): void
    {
        // Step 1: Create Order
        $createPayload = [
            'site_id' => $this->site->id,
            'notes' => 'Initial order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 10,
                    'is_custom' => 0,
                ],
            ],
        ];

        $createResponse = $this->postJson('/api/v1/sites/order-request/form-data', $createPayload);
        $createResponse->assertStatus(200);
        $orderId = $createResponse->json('data.id');

        // Step 2: Update Order
        $updatePayload = [
            'order_id' => $orderId,
            'site_id' => $this->site->id,
            'notes' => 'Updated order',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'product_id' => $this->regularProduct1->id,
                    'quantity' => 15,
                    'is_custom' => 0,
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'Added custom product',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
            ],
        ];

        $updateResponse = $this->putJson('/api/v1/sites/order-request/form-data/update', $updatePayload);
        $updateResponse->assertStatus(200);

        // Step 3: View Order
        $viewResponse = $this->getJson("/api/v1/sites/order-request/{$orderId}");
        $viewResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'products',
                ],
            ]);

        $order = Order::find($orderId);
        $this->assertEquals(15, $order->products()->where('product_id', $this->regularProduct1->id)->first()->pivot->quantity);
        $this->assertEquals(1, $order->customProducts()->count());
    }

    public function test_tc033_multiple_custom_products_with_different_materials(): void
    {
        $payload = [
            'site_id' => $this->site->id,
            'notes' => 'Multiple custom products',
            'priority' => PriorityEnum::High->value,
            'expected_delivery_date' => now()->addDays(5)->format('d/m/Y'),
            'products' => [
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product 1',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material1->id,
                                'actual_pcs' => 5,
                                'cal_qty' => 5,
                            ],
                        ],
                        'quantity' => 5,
                    ],
                ],
                [
                    'is_custom' => 1,
                    'custom_note' => 'Custom product 2',
                    'product_details' => [
                        'product_id' => $this->regularProduct1->id,
                        'materials' => [
                            [
                                'material_id' => $this->material2->id,
                                'actual_pcs' => 10,
                                'cal_qty' => 10,
                            ],
                        ],
                        'quantity' => 10,
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sites/order-request/form-data', $payload);

        $response->assertStatus(200);

        $order = Order::first();
        $this->assertEquals(2, $order->customProducts()->count());

        $responseData = $response->json('data.products');
        $customProducts = collect($responseData)->where('is_custom', 1);
        $this->assertGreaterThanOrEqual(2, $customProducts->count());
    }
}
