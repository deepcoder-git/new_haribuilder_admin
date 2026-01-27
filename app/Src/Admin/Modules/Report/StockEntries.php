<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Report;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockEntries extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $adjustmentType = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    public ?Product $product = null;

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;
        if (!in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }

        $productId = request()->get('product_id');
        if ($productId && is_numeric($productId)) {
            $this->product = Product::query()->with('category')->find((int) $productId);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAdjustmentType(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        $perPageValue = is_numeric($value) ? (int) $value : 10;
        $this->perPage = in_array($perPageValue, [10, 25, 50, 100], true) ? $perPageValue : 10;
        $this->resetPage();
    }

    public function getStocksProperty()
    {
        $query = Stock::query()
            // Eager-load related models to avoid lazy loading (reference is morphTo)
            ->with(['product.category', 'site', 'reference'])
            ->where('status', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($this->product) {
            $query->where('product_id', $this->product->id);
        }

        if ($this->adjustmentType !== '') {
            $query->where('adjustment_type', $this->adjustmentType);
        }

        if ($this->search !== '') {
            $s = $this->search;
            $query->whereHas('product', function (Builder $q) use ($s) {
                $q->where('product_name', 'like', "%{$s}%");
            });
        }

        return $query->paginate($this->perPage);
    }

    public function getAdjustmentIcon(string $type): string
    {
        return match ($type) {
            'in' => 'fa-arrow-up',
            'out' => 'fa-arrow-down',
            default => 'fa-equals',
        };
    }

    public function getAdjustmentLabel(string $type): string
    {
        return match ($type) {
            'in' => 'Stock In',
            'out' => 'Stock Out',
            default => 'Adjustment',
        };
    }

    public function getAdjustmentBadgeClass(string $type): string
    {
        return match ($type) {
            'in' => 'badge-light-success',
            'out' => 'badge-light-danger',
            default => 'badge-light-primary',
        };
    }

    public function getReferenceInfo(Stock $stock): ?string
    {
        if (!$stock->reference_type || !$stock->reference_id) {
            return null;
        }

        $base = class_basename((string) $stock->reference_type);
        $ref = $stock->reference;

        // Try to show a helpful label for known references
        if ($ref && !empty($ref->purchase_number ?? null)) {
            return $base . ' #' . $ref->purchase_number;
        }

        return $base . ' #' . $stock->reference_id;
    }

    public function getReferenceUrl(Stock $stock): ?string
    {
        if (!$stock->reference_type || !$stock->reference_id) {
            return null;
        }

        $type = ltrim((string) $stock->reference_type, '\\');

        // Map known references to admin "view" routes (only if route exists)
        $routeName = match ($type) {
            \App\Models\ProductPurchase::class => 'admin.product-purchases.view',
            default => null,
        };

        if (!$routeName || !Route::has($routeName)) {
            return null;
        }

        return route($routeName, $stock->reference_id);
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Stock.views.entries', [
            'stocks' => $this->stocks,
            'product' => $this->product,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Stock Entries',
            'breadcrumb' => [
                ['Reports', '#'],
                ['Stock Report', route('admin.reports.stock-report')],
                ['Entries', '#'],
            ],
        ]);
    }
}


