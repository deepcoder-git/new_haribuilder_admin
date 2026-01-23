<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Site;

class SiteService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Site::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:sites,code',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'status' => 'boolean',
            'description' => 'nullable|string',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        $rules['code'] = 'required|string|max:50|unique:sites,code,' . request()->route('id');
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        return $data;
    }
}

