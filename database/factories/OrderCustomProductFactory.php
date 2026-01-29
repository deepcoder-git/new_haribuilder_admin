<?php

namespace Database\Factories;

use App\Models\OrderCustomProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderCustomProduct>
 */
class OrderCustomProductFactory extends Factory
{
    protected $model = OrderCustomProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => null,
            'product_details' => [
                'product_id' => null,
                'quantity' => fake()->numberBetween(1, 10),
                'unit_id' => null,
                'materials' => [],
            ],
            'custom_note' => fake()->optional()->sentence(),
            'product_ids' => null,
        ];
    }
}
