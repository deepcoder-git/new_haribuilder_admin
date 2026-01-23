<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;

class OrderService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Order::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'site_id' => 'required|exists:sites,id',
            'site_manager_id' => 'nullable|exists:moderators,id',
            'transport_manager_id' => 'nullable|exists:moderators,id',
            'sale_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'drop_location' => 'nullable|string|max:255',
            'document_details' => 'nullable|string',
            'priority' => 'nullable|string',
            'note' => 'nullable|string',
            'driver_name' => 'nullable|string|max:255',
            'vehicle_number' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'delivery_status' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required_without:items.*.is_custom|nullable|exists:products,id',
            'items.*.quantity' => 'required_without:items.*.is_custom|nullable|integer|min:1',
            'items.*.is_custom' => 'nullable|boolean',
            'items.*.custom_note' => 'required_if:items.*.is_custom,true|nullable|string',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        return $data;
    }
}

