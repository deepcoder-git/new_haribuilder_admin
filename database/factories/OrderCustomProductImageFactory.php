<?php

namespace Database\Factories;

use App\Models\OrderCustomProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderCustomProductImage>
 */
class OrderCustomProductImageFactory extends Factory
{
    protected $model = OrderCustomProductImage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_custom_product_id' => null,
            'image_path' => fake()->imageUrl(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
