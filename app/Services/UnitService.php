<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;

class UnitService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Unit::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'status' => 'boolean',
            'name' => 'required|string|max:255',
            'status' => 'boolean',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        // Add unique rules here if needed
        // $rules['name'] = 'required|string|max:255|unique:units,name,' . request()->route('id');
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        return $data;
    }
}

