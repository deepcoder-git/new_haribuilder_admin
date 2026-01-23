<?php

declare(strict_types=1);

namespace App\Utility\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasCrudOperations
{
    /**
     * Create a new record
     */
    public function createRecord(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            return $this->getModel()::create($data);
        });
    }

    /**
     * Update a record
     */
    public function updateRecord(int|string $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            $model = $this->getModel()::findOrFail($id);
            $model->update($data);
            return $model->fresh();
        });
    }

    /**
     * Delete a record
     */
    public function deleteRecord(int|string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $model = $this->getModel()::findOrFail($id);
            return $model->delete();
        });
    }

    /**
     * Bulk delete records
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            return $this->getModel()::whereIn('id', $ids)->delete();
        });
    }

    /**
     * Get record by ID
     */
    public function getRecord(int|string $id): Model
    {
        return $this->getModel()::findOrFail($id);
    }

    /**
     * Get all records with pagination
     */
    public function getAllRecords(array $filters = [], int $perPage = 10)
    {
        $query = $this->getModel()::query();

        // Apply filters
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Search records
     */
    public function searchRecords(string $search, array $fields = [], int $perPage = 10)
    {
        $query = $this->getModel()::query();

        if (!empty($fields) && $search) {
            $query->where(function ($q) use ($search, $fields) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get model class name
     */
    abstract protected function getModel(): string;
}

