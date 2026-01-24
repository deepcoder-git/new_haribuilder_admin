<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Report;

use App\Models\Product;
use App\Models\Stock;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class StockReport extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    /** When true, only show products whose current qty <= threshold */
    public bool $onlyLowStock = false;

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;
        if (!in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }
    }

    public function updatedPerPage($value): void
    {
        $perPageValue = is_numeric($value) ? (int) $value : 10;
        $this->perPage = in_array($perPageValue, [10, 25, 50, 100], true) ? $perPageValue : 10;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getProductsProperty()
    {
        // Latest general-stock quantity (site_id = null) subquery
        $latestStockQty = Stock::query()
            ->select('quantity')
            ->whereColumn('product_id', 'products.id')
            ->whereNull('site_id')
            ->where('status', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);

        $query = Product::query()
            ->with('category')
            // LPO products are not stock-managed
            ->where('store', '!=', StoreEnum::LPO)
            ->select('products.*')
            // Computed field: latest general stock quantity (nullable if no entries)
            ->selectSub($latestStockQty, 'current_qty')
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function (Builder $q) use ($s) {
                $q->where('product_name', 'like', "%{$s}%")
                    ->orWhereHas('category', function (Builder $cq) use ($s) {
                        $cq->where('name', 'like', "%{$s}%");
                    });
            });
        }

        if ($this->onlyLowStock) {
            // threshold must be present and current qty <= threshold
            $query->whereNotNull('low_stock_threshold')
                ->where('low_stock_threshold', '>', 0)
                ->havingRaw('COALESCE(current_qty, available_qty, 0) <= low_stock_threshold');
        }

        return $query->paginate($this->perPage);
    }

    public function getTotalQuantity(Product $product): int
    {
        // If query provided current_qty, use it (but if null, fall back).
        if (isset($product->current_qty) && $product->current_qty !== null) {
            return (int) $product->current_qty;
        }

        // Fall back to available_qty (opening balance), otherwise accessor.
        if ($product->available_qty !== null) {
            return (int) $product->available_qty;
        }

        return (int) ($product->total_stock_quantity ?? 0);
    }

    public function renderLowStock(Product $product): string
    {
        $qty = $this->getTotalQuantity($product);
        $threshold = (int) ($product->low_stock_threshold ?? 0);

        if ($threshold <= 0) {
            return '<span class="badge badge-light-secondary">N/A</span>';
        }

        if ($qty <= 0) {
            return '<span class="badge badge-light-danger">Out</span>';
        }

        if ($qty <= $threshold) {
            return '<span class="badge badge-light-warning">Low</span>';
        }

        return '<span class="badge badge-light-success">OK</span>';
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Stock.views.report', [
            'products' => $this->products,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->onlyLowStock ? 'Low Stock Report' : 'Stock Report',
            'breadcrumb' => [
                ['Reports', '#'],
                [$this->onlyLowStock ? 'Low Stock Report' : 'Stock Report', '#'],
            ],
        ]);
    }
}


