<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\ProductPurchase;

use App\Models\ProductPurchase;
use App\Models\Supplier;
use App\Services\ProductPurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductPurchaseDatatable extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    #[Url(as: 'sort')]
    public string $sortField = 'created_at';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    #[Url(as: 'supplier')]
    public ?int $filter_supplier_id = null;

    public string $tempSupplierFilter = 'all';

    public ?int $purchaseToDelete = null;
    public ?string $purchaseNumberToDelete = null;
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

    public function updatedSearch(): void
    {
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

    public function syncTempFilters(): void
    {
        $this->tempSupplierFilter = $this->filter_supplier_id ? (string) $this->filter_supplier_id : 'all';
    }

    public function applyFilters(): void
    {
        $this->filter_supplier_id = ($this->tempSupplierFilter === 'all' || $this->tempSupplierFilter === '')
            ? null
            : (int) $this->tempSupplierFilter;

        $this->resetPage();
        $this->dispatch('close-filter-dropdown');
    }

    public function resetFilters(): void
    {
        $this->filter_supplier_id = null;
        $this->tempSupplierFilter = 'all';
        $this->search = '';

        $this->resetPage();
        $this->dispatch('reset-filter-selects');
    }

    public function hasActiveFilters(): bool
    {
        return (bool) $this->filter_supplier_id;
    }

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.product-purchases.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.product-purchases.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.product-purchases.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        /** @var ProductPurchase $purchase */
        $purchase = ProductPurchase::query()->findOrFail($id);
        $this->purchaseToDelete = (int) $id;
        $this->purchaseNumberToDelete = (string) ($purchase->purchase_number ?? 'Purchase');
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->purchaseToDelete = null;
        $this->purchaseNumberToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->purchaseToDelete) {
            return;
        }

        try {
            app(ProductPurchaseService::class)->delete($this->purchaseToDelete);
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Purchase deleted successfully!']);
            $this->closeDeleteModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function renderTotalAmount(ProductPurchase $purchase): string
    {
        $amount = (int) ($purchase->total_amount ?? 0);
        return number_format($amount, 0);
    }

    public function getPurchasesProperty()
    {
        $query = ProductPurchase::query()->with('supplier');

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function (Builder $q) use ($s) {
                $q->where('purchase_number', 'like', "%{$s}%")
                    ->orWhereHas('supplier', function (Builder $sq) use ($s) {
                        $sq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        if ($this->filter_supplier_id) {
            $query->where('supplier_id', $this->filter_supplier_id);
        }

        $sortField = match ($this->sortField) {
            'created_at' => 'created_at',
            'purchase_date' => 'purchase_date',
            'total_amount' => 'total_amount',
            default => 'id',
        };

        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function render(): View
    {
        $suppliers = Supplier::query()->select(['id', 'name'])->orderBy('name')->get();

        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::ProductPurchase.views.product-purchase-datatable', [
            'purchases' => $this->purchases,
            'suppliers' => $suppliers,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Product Purchases',
            'breadcrumb' => [['Product Purchases', route('admin.product-purchases.index')]],
        ]);
    }
}


