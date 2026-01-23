<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Supplier;

use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SupplierForm extends Component
{
    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public string $name = '';
    public string $supplier_type = 'General Supplier';
    public string $email = '';
    public string $phone = '';
    public ?string $address = null;
    public ?string $description = null;
    public ?string $tin_number = null;
    // Form blade uses string values: active|inactive
    public string $status = 'active';

    /** @var array<string,bool> */
    public array $dropdownOpen = [];

    public function mount(?int $id = null): void
    {
        if ($id) {
            /** @phpstan-ignore-next-line */
            $supplier = Supplier::findOrFail($id);
            $this->isEditMode = true;
            $this->editingId = $id;

            $this->name = (string) ($supplier->name ?? '');
            $this->supplier_type = (string) ($supplier->supplier_type ?? 'General Supplier');
            $this->email = (string) ($supplier->email ?? '');
            $this->phone = (string) ($supplier->phone ?? '');
            $this->address = $supplier->address;
            $this->description = $supplier->description;
            $this->tin_number = $supplier->tin_number;
            $this->status = $supplier->status ? 'active' : 'inactive';
        }
    }

    public function toggleDropdown(string $field): void
    {
        $current = $this->dropdownOpen[$field] ?? false;

        // close all, then toggle requested
        $this->dropdownOpen = [];
        $this->dropdownOpen[$field] = !$current;
    }

    public function closeDropdown(string $field): void
    {
        $this->dropdownOpen[$field] = false;
    }

    public function selectOption(string $field, string $value): void
    {
        if (property_exists($this, $field)) {
            $this->{$field} = $value;
        }

        $this->closeDropdown($field);
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $data = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                /** @phpstan-ignore-next-line */
                $supplier = Supplier::findOrFail($this->editingId);
                $supplier->update($data);
                $message = 'Supplier updated successfully!';
            } else {
                /** @phpstan-ignore-next-line */
                Supplier::create($data);
                $message = 'Supplier created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.suppliers.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.suppliers.index'));
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'supplier_type' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'The supplier name is required.',
            'supplier_type.required' => 'The supplier type is required.',
            'email.required' => 'The email is required.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'The phone is required.',
            'status.required' => 'The status is required.',
            'status.in' => 'Please select a valid status.',
        ];
    }

    protected function payload(): array
    {
        return [
            'name' => $this->name,
            'supplier_type' => $this->supplier_type,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address ?: null,
            'description' => $this->description ?: null,
            'tin_number' => $this->tin_number ?: null,
            'status' => $this->status === 'active',
        ];
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Supplier.views.supplier-form', [
            'isEditMode' => $this->isEditMode,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Supplier' : 'Add Supplier',
            'breadcrumb' => [
                ['Suppliers', route('admin.suppliers.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


