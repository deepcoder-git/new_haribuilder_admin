<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StockTransfer;

class StockTransferService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return StockTransfer::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'from_site_id' => 'required|exists:sites,id',
            'to_site_id' => 'required|exists:sites,id|different:from_site_id',
            'transfer_date' => 'required|date',
            'transfer_status' => 'required|in:pending,in_transit,completed,cancelled',
            'notes' => 'nullable|string',
            'status' => 'boolean',
        ];
    }

    protected function getUpdateRules(): array
    {
        return $this->getCreateRules();
    }

    protected function prepareCreateData(array $data): array
    {
        $data['status'] = $data['status'] ?? true;
        return $data;
    }
}

