<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Module\Requests;

class StoreModuleRequest
{
    protected bool $isEditMode;
    protected ?int $editingId;

    public function __construct(bool $isEditMode = false, ?int $editingId = null)
    {
        $this->isEditMode = $isEditMode;
        $this->editingId = $editingId;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string',
            'index_route' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if ($value && !\Illuminate\Support\Facades\Route::has($value)) {
                        $fail("The route '{$value}' does not exist. Please create the route first or leave it empty.");
                    }
                },
            ],
            'sub_routes' => 'nullable|array',
            'parent_id' => 'nullable|exists:modules,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'theme_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ];

        if ($this->isEditMode) {
            $rules['unique_name'] = 'required|string|max:255|unique:modules,unique_name,' . $this->editingId;
        } else {
            $rules['unique_name'] = 'required|string|max:255|unique:modules,unique_name';
        }

        return $rules;
    }
}

