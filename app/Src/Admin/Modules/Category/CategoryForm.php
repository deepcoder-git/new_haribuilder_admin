<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Category;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CategoryForm extends Component
{
    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public string $name = '';
    public string|bool|null $status = '1';

    public function mount(?int $id = null): void
    {
        if ($id) {
            /** @var Category $category */
            $category = Category::findOrFail($id);

            $this->isEditMode = true;
            $this->editingId = $id;

            $this->name = (string) ($category->name ?? '');
            $this->status = $category->status ? '1' : '0';
        }
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $data = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                /** @var Category $category */
                $category = Category::findOrFail($this->editingId);
                $category->update($data);
                $message = 'Category updated successfully!';
            } else {
                Category::create($data);
                $message = 'Category created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.categories.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.categories.index'));
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->editingId),
            ],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'The category name is required.',
            'name.unique' => 'This category name is already taken.',
        ];
    }

    protected function payload(): array
    {
        return [
            'name' => $this->name,
            'status' => (bool) $this->status,
        ];
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Category.views.category-form', [
            'isEditMode' => $this->isEditMode,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Category' : 'Add Category',
            'breadcrumb' => [
                ['Categories', route('admin.categories.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


