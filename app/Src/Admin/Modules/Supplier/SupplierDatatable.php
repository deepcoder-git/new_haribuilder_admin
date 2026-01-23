<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Supplier;

use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierDatatable extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    #[Url(as: 'sort')]
    public string $sortField = 'id';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    #[Url(as: 'type')]
    public ?string $filter_type = null;

    #[Url(as: 'status')]
    public ?string $filter_status = null; // active|inactive|null

    public ?string $tempTypeFilter = null;
    public ?string $tempStatusFilter = null;

    public ?int $supplierToDelete = null;
    public ?string $supplierNameToDelete = null;
    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;

        if (!in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }

        $this->syncTempFilters();
    }

    public function syncTempFilters(): void
    {
        $this->tempTypeFilter = $this->filter_type ?: 'all';
        $this->tempStatusFilter = $this->filter_status ?: 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->filter_type = ($this->tempTypeFilter === 'all' || $this->tempTypeFilter === null)
            ? null
            : $this->tempTypeFilter;

        $this->filter_status = ($this->tempStatusFilter === 'all' || $this->tempStatusFilter === null)
            ? null
            : $this->tempStatusFilter;

        $this->resetPage();
        $this->dispatch('close-filter-dropdown');
    }

    public function resetFilters(): void
    {
        $this->filter_type = null;
        $this->filter_status = null;
        $this->tempTypeFilter = 'all';
        $this->tempStatusFilter = 'all';
        $this->search = '';

        $this->resetPage();
        $this->dispatch('reset-filter-selects');
    }

    public function hasActiveFilters(): bool
    {
        return ($this->filter_type && $this->filter_type !== 'all')
            || ($this->filter_status && $this->filter_status !== 'all');
    }

    public function updatedPerPage($value): void
    {
        $perPageValue = is_numeric($value) ? (int) $value : 10;
        $this->perPage = in_array($perPageValue, [10, 25, 50, 100], true) ? $perPageValue : 10;
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.suppliers.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.suppliers.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.suppliers.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        /** @phpstan-ignore-next-line */
        $supplier = Supplier::findOrFail($id);
        $this->supplierToDelete = (int) $id;
        $this->supplierNameToDelete = $supplier->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->supplierToDelete = null;
        $this->supplierNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->supplierToDelete) {
            return;
        }

        try {
            
            Supplier::findOrFail($this->supplierToDelete)->delete();

            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Supplier deleted successfully!']);
            $this->resetPage();
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function toggleStatus(int|string $id): void
    {
        try {
            /** @phpstan-ignore-next-line */
            $supplier = Supplier::findOrFail($id);
            $supplier->update(['status' => !$supplier->status]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Supplier status updated successfully!',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getSuppliersProperty()
    {
        /** @phpstan-ignore-next-line */
        $query = Supplier::query();

        if ($this->search) {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('tin_number', 'like', "%{$s}%");
            });
        }

        if ($this->filter_type) {
            $query->where('supplier_type', $this->filter_type);
        }

        if ($this->filter_status) {
            if ($this->filter_status === 'active') {
                $query->where('status', true);
            } elseif ($this->filter_status === 'inactive') {
                $query->where('status', false);
            }
        }

        $sortField = in_array($this->sortField, ['name', 'email', 'created_at'], true)
            ? $this->sortField
            : 'id';

        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function getSupplierTypesProperty()
    {
        return [
            'General Supplier',
            'LPO Supplier',
            'Overseas Supplier',
        ];
    }
    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Supplier.views.supplier-datatable', [
            'suppliers' => $this->suppliers,
            'supplierTypes' => $this->supplierTypes,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Supplier Management',
            'breadcrumb' => [['Suppliers', route('admin.suppliers.index')]],
        ]);
    }
}


