<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Product;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Unit;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductDatatable extends Component
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

    // Filters (persist in URL)
    #[Url(as: 'store')]
    public ?string $filter_store = null;

    #[Url(as: 'category')]
    public ?int $filter_category_id = null;

    #[Url(as: 'unit')]
    public ?string $filter_unit_type = null;

    #[Url(as: 'status')]
    public ?string $filter_status = null; // active|inactive|null

    // Temp filters (dropdown)
    public string $tempStoreFilter = 'all';
    public string $tempCategoryFilter = 'all';
    public string $tempUnitFilter = 'all';
    public string $tempStatusFilter = 'all';

    // Delete modal state
    public ?int $productToDelete = null;
    public ?string $productNameToDelete = null;
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
        $this->tempStoreFilter = $this->filter_store ?: 'all';
        $this->tempCategoryFilter = $this->filter_category_id ? (string) $this->filter_category_id : 'all';
        $this->tempUnitFilter = $this->filter_unit_type ?: 'all';
        $this->tempStatusFilter = $this->filter_status ?: 'all';
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

    public function applyFilters(): void
    {
        $this->filter_store = ($this->tempStoreFilter === 'all') ? null : $this->tempStoreFilter;
        $this->filter_category_id = ($this->tempCategoryFilter === 'all') ? null : (int) $this->tempCategoryFilter;
        $this->filter_unit_type = ($this->tempUnitFilter === 'all') ? null : $this->tempUnitFilter;
        $this->filter_status = ($this->tempStatusFilter === 'all') ? null : $this->tempStatusFilter;

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filter_store = null;
        $this->filter_category_id = null;
        $this->filter_unit_type = null;
        $this->filter_status = null;
        $this->search = '';

        $this->syncTempFilters();
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) ($this->filter_store || $this->filter_category_id || $this->filter_unit_type || $this->filter_status);
    }

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.products.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.products.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.products.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        /** @var Product $product */
        $product = Product::findOrFail($id);
        $this->productToDelete = (int) $id;
        $this->productNameToDelete = $product->product_name ?? 'Product';
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->productToDelete = null;
        $this->productNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->productToDelete) {
            return;
        }

        try {
            $product = Product::findOrFail($this->productToDelete);

            if ($this->isProductConnectedToOrders($product->id)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'This product cannot be deleted because it is connected to orders.',
                ]);
                $this->closeDeleteModal();
                return;
            }

            $product->delete();
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Product deleted successfully!']);
            $this->closeDeleteModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function toggleStatus(int|string $id): void
    {
        try {
            /** @var Product $product */
            $product = Product::findOrFail($id);

            if ($product->status && $this->isProductConnectedToOrders($product->id)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Cannot inactive product: product is connected to orders.',
                ]);
                return;
            }

            $product->update(['status' => !$product->status]);
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Product status updated successfully!']);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function isProductConnectedToOrders(int $productId): bool
    {
        return Order::query()
            ->whereHas('products', function (Builder $q) use ($productId) {
                $q->where('products.id', $productId);
            })
            ->exists();
    }

    public function getProductConnectionShortMessage(int $productId): string
    {
        $count = Order::query()
            ->whereHas('products', function (Builder $q) use ($productId) {
                $q->where('products.id', $productId);
            })
            ->count();

        return $count > 0 ? "Used in {$count} order(s)" : 'Used in orders';
    }

    public function renderImage($product): string
    {
        $url = $product->primary_image_url ?? $product->first_image_url ?? null;
        if (!$url) {
            return '<div class="product-image-placeholder d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb; background: #f9fafb;"><i class="fa-solid fa-image text-muted"></i></div>';
        }

        $name = htmlspecialchars((string) ($product->product_name ?? 'Product'), ENT_QUOTES, 'UTF-8');
        $urlEsc = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');

        return '<div class="product-image-wrapper d-inline-block">'
            . '<img src="' . $urlEsc . '" class="product-table-image product-image-zoom" data-image-url="' . $urlEsc . '" data-product-name="' . $name . '"'
            . ' style="width: 44px; height: 44px; object-fit: cover; border-radius: 0.5rem; border: 1px solid #e5e7eb; cursor: pointer;" />'
            . '</div>';
    }

    public function renderQuantity($product): string
    {
        $qty = (int) ($product->total_stock_quantity ?? $product->available_qty ?? 0);
        return '<span class="fw-semibold">' . $qty . '</span>';
    }

    public function renderLowStock($product): string
    {
        $qty = (int) ($product->total_stock_quantity ?? $product->available_qty ?? 0);
        $threshold = (int) ($product->low_stock_threshold ?? 0);

        if ($threshold > 0 && $qty <= $threshold) {
            return '<span class="badge badge-light-danger">Low</span>';
        }

        return '<span class="badge badge-light-success">OK</span>';
    }

    public function getProductsProperty()
    {
        $query = Product::query()
            ->with(['category', 'productImages'])
            ->whereIn('is_product', [1, 2]); // products

        if ($this->search) {
            $query->where('product_name', 'like', "%{$this->search}%");
        }

        if ($this->filter_store) {
            $query->where('store', $this->filter_store);
        }

        if ($this->filter_category_id) {
            $query->where('category_id', $this->filter_category_id);
        }

        if ($this->filter_unit_type) {
            $query->where('unit_type', $this->filter_unit_type);
        }

        if ($this->filter_status) {
            if ($this->filter_status === 'active') {
                $query->where('status', true);
            } elseif ($this->filter_status === 'inactive') {
                $query->where('status', false);
            }
        }

        $sortField = match ($this->sortField) {
            'product_name' => 'product_name',
            'created_at' => 'created_at',
            default => 'id',
        };

        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function render(): View
    {
        $categories = Category::query()->orderBy('name')->get();
        $unitTypes = Unit::query()->where('status', true)->orderBy('name')->pluck('name');
        $stores = collect(StoreEnum::cases())->map(fn (StoreEnum $s) => ['value' => $s->value, 'name' => $s->getName()]);

        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Product.views.product-datatable', [
            'products' => $this->products,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
            'stores' => $stores,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Product Management',
            'breadcrumb' => [['Products', route('admin.products.index')]],
        ]);
    }
}