<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use Illuminate\Database\Eloquent\Model;

class ModuleService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Module::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'unique_name' => 'required|string|max:255|unique:modules,unique_name',
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'index_route' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($value && !\Illuminate\Support\Facades\Route::has($value)) {
                    $fail("The route '{$value}' does not exist. Please create the route first or leave it empty.");
                }
            }],
            'sub_routes' => 'nullable|array',
            'parent_id' => 'nullable|exists:modules,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = $this->getCreateRules();
        $rules['unique_name'] = 'required|string|max:255|unique:modules,unique_name,' . request()->route('id');
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['order'] = $data['order'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sub_routes'] = $data['sub_routes'] ?? [];
        
        // Validate sub_routes exist
        if (!empty($data['sub_routes']) && is_array($data['sub_routes'])) {
            $invalidRoutes = [];
            foreach ($data['sub_routes'] as $route) {
                if ($route && !\Illuminate\Support\Facades\Route::has($route)) {
                    $invalidRoutes[] = $route;
                }
            }
            if (!empty($invalidRoutes)) {
                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add('sub_routes', 'The following routes do not exist: ' . implode(', ', $invalidRoutes));
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }
        
        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        // Validate sub_routes exist
        if (isset($data['sub_routes']) && is_array($data['sub_routes']) && !empty($data['sub_routes'])) {
            $invalidRoutes = [];
            foreach ($data['sub_routes'] as $route) {
                if ($route && !\Illuminate\Support\Facades\Route::has($route)) {
                    $invalidRoutes[] = $route;
                }
            }
            if (!empty($invalidRoutes)) {
                $validator = \Illuminate\Support\Facades\Validator::make([], []);
                $validator->errors()->add('sub_routes', 'The following routes do not exist: ' . implode(', ', $invalidRoutes));
                throw new \Illuminate\Validation\ValidationException($validator);
            }
        }
        
        return $data;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        Module::clearModulesCache();
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        Module::clearModulesCache();
    }

    protected function afterDelete(Model $model): void
    {
        Module::clearModulesCache();
    }
}

