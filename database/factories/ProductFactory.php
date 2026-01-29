<?php

namespace Database\Factories;

use App\Models\Product;
use App\Utility\Enums\StoreEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_name' => fake()->words(3, true),
            'category_id' => null,
            'store' => StoreEnum::HardwareStore,
            'store_manager_id' => null,
            'unit_type' => fake()->randomElement(['pcs', 'kg', 'm', 'm²', 'm³']),
            'image' => null,
            'low_stock_threshold' => fake()->numberBetween(10, 50),
            'available_qty' => fake()->numberBetween(0, 100),
            'status' => true,
            'is_product' => 1,
        ];
    }
}
