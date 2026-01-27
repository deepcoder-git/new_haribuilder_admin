<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Order\Requests;

use App\Services\StockService;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use Illuminate\Validation\Rule;

/**
 * Lightweight rules/messages provider used by the Livewire `OrderForm`.
 *
 * NOTE:
 * This is NOT an HTTP FormRequest. It exists because the admin order form builds
 * validation rules dynamically (needs current component state + StockService).
 */
class StoreOrderRequest
{
    public function __construct(
        public bool $isEditMode,
        public int|string|null $editingId,
        public ?string $site_id,
        public array $orderProducts,
        public ?StockService $stockService = null,
    ) {
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_map(fn ($c) => $c->value, OrderStatusEnum::cases()))],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'site_manager_id' => ['nullable', 'integer', 'exists:moderators,id'],
            // transport_manager_id is intentionally not validated here because assignment flow differs per role
            'priority' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, PriorityEnum::cases()))],
            'expected_delivery_date' => ['required', 'date'],
            'drop_location' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],

            'orderProducts' => ['required', 'array', 'min:1'],
            'orderProducts.*.is_custom' => ['nullable'],

            // For non-custom rows, quantity is required and must be >= 1.
            // Custom rows do not have quantity at order-level.
            'orderProducts.*.quantity' => [
                'nullable',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) {
                    // Extract row index from "orderProducts.N.quantity"
                    if (!preg_match('/^orderProducts\.(\d+)\.quantity$/', $attribute, $matches)) {
                        return;
                    }
                    $index = (int) $matches[1];
                    $row = $this->orderProducts[$index] ?? [];
                    $isCustom = (bool) ($row['is_custom'] ?? false);

                    if (!$isCustom && ($value === null || $value === '' || (float) $value < 1)) {
                        $fail('Quantity is required.');
                    }
                },
            ],

            // For non-custom rows, product_id is required (validated separately in component).
            'orderProducts.*.product_id' => ['nullable', 'integer', 'exists:products,id'],

            'orderProducts.*.custom_note' => ['nullable', 'string', 'max:255'],
            'orderProducts.*.custom_images' => ['nullable'],
            'orderProducts.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'site_id.required' => 'Please select a site.',
            'site_id.exists' => 'Selected site is invalid.',
            'priority.required' => 'Please select priority.',
            'priority.in' => 'Selected priority is invalid.',
            'expected_delivery_date.required' => 'Expected delivery date is required.',
            'expected_delivery_date.date' => 'Expected delivery date must be a valid date.',
            'orderProducts.required' => 'Please add at least one product.',
            'orderProducts.array' => 'Invalid products payload.',
            'orderProducts.min' => 'Please add at least one product.',
            'orderProducts.*.quantity.required' => 'Quantity is required.',
            'orderProducts.*.quantity.min' => 'Quantity must be at least 1.',
            'orderProducts.*.product_id.exists' => 'Selected product is invalid.',
            'orderProducts.*.supplier_id.exists' => 'Selected supplier is invalid.',
        ];
    }
}


