<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Delivery;

class DeliveryService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Delivery::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'site_id' => 'nullable|exists:sites,id',
            'transport_manager_id' => 'nullable|exists:moderators,id',
            'delivery_date' => 'required|date',
            'status' => 'required|in:pending,in_transit,delivered,cancelled',
        ];
    }

    protected function getUpdateRules(): array
    {
        return $this->getCreateRules();
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? 'pending';
        return $data;
    }
}

