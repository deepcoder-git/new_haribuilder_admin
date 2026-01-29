<?php

namespace Database\Factories;

use App\Models\Order;
use App\Utility\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => null,
            'site_manager_id' => null,
            'transport_manager_id' => null,
            'expected_delivery_date' => fake()->date(),
            'status' => OrderStatusEnum::Pending,
            'priority' => null,
            'note' => fake()->optional()->sentence(),
            'rejected_note' => null,
            'product_status' => null,
            'product_rejection_notes' => null,
            'drop_location' => null,
            'is_lpo' => false,
            'is_custom_product' => false,
            'supplier_id' => null,
            'product_driver_details' => null,
        ];
    }
}
