<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Supplier;

class SupplierService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Supplier::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'supplier_type' => 'required|string|in:General Supplier,LPO Supplier,Overseas Supplier',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'gst_no' => 'nullable|string|max:255',
            'status' => 'boolean',
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

