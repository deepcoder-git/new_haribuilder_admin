<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Unit;

use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UnitForm extends Component
{
    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public string $name = '';
    public string|bool|null $status = '1';

    public function mount(?int $id = null): void
    {
        if ($id) {
            /** @var Unit $unit */
            $unit = Unit::findOrFail($id);

            $this->isEditMode = true;
            $this->editingId = $id;

            $this->name = (string) ($unit->name ?? '');
            $this->status = $unit->status ? '1' : '0';
        }
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $data = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                /** @var Unit $unit */
                $unit = Unit::findOrFail($this->editingId);
                $unit->update($data);
                $message = 'Unit updated successfully!';
            } else {
                Unit::create($data);
                $message = 'Unit created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.units.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.units.index'));
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units', 'name')->ignore($this->editingId),
            ],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'The unit name is required.',
            'name.unique' => 'This unit name is already taken.',
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
        $view = view('admin::Unit.views.unit-form', [
            'isEditMode' => $this->isEditMode,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Unit' : 'Add Unit',
            'breadcrumb' => [
                ['Units', route('admin.units.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


