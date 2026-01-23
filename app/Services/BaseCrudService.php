<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class BaseCrudService
{
    /**
     * Get the model class name
     */
    abstract protected function getModelClass(): string;

    /**
     * Get validation rules for create
     */
    abstract protected function getCreateRules(): array;

    /**
     * Get validation rules for update
     */
    protected function getUpdateRules(): array
    {
        return $this->getCreateRules();
    }

    /**
     * Prepare data before create
     */
    protected function prepareCreateData(array $data): array
    {
        return $data;
    }

    /**
     * Prepare data before update
     */
    protected function prepareUpdateData(array $data): array
    {
        return $data;
    }

    /**
     * Create a new record
     */
    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $preparedData = $this->prepareCreateData($data);
            $model = $this->getModelClass()::create($preparedData);
            $this->afterCreate($model, $data);
            return $model;
        });
    }

    /**
     * Update a record
     */
    public function update(int|string $id, array $data): Model
    {
        return DB::transaction(function () use ($id, $data) {
            $model = $this->getModelClass()::findOrFail($id);
            $preparedData = $this->prepareUpdateData($data);
            $model->update($preparedData);
            $this->afterUpdate($model, $data);
            return $model->fresh();
        });
    }

    /**
     * Delete a record
     */
    public function delete(int|string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $model = $this->getModelClass()::findOrFail($id);
            $this->beforeDelete($model);
            $deleted = $model->delete();
            $this->afterDelete($model);
            return $deleted;
        });
    }

    /**
     * Get record by ID
     */
    public function find(int|string $id): ?Model
    {
        return $this->getModelClass()::find($id);
    }

    /**
     * Get record or fail
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->getModelClass()::findOrFail($id);
    }

    /**
     * Get all records
     */
    public function all(array $columns = ['*'])
    {
        return $this->getModelClass()::all($columns);
    }

    /**
     * Get paginated records
     */
    public function paginate(int $perPage = 10, array $filters = [])
    {
        $query = $this->getModelClass()::query();

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
    public function search(string $search, array $searchableFields = [], int $perPage = 10)
    {
        $query = $this->getModelClass()::query();

        if ($search && !empty($searchableFields)) {
            $query->where(function ($q) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $models = $this->getModelClass()::whereIn('id', $ids)->get();
            
            foreach ($models as $model) {
                $this->beforeDelete($model);
            }

            $deleted = $this->getModelClass()::whereIn('id', $ids)->delete();

            foreach ($models as $model) {
                $this->afterDelete($model);
            }

            return $deleted;
        });
    }

    /**
     * Hooks - Override in child classes
     */
    protected function afterCreate(Model $model, array $data): void
    {
        // Override if needed
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        // Override if needed
    }

    protected function beforeDelete(Model $model): void
    {
        // Override if needed
    }

    protected function afterDelete(Model $model): void
    {
        // Override if needed
    }
}

