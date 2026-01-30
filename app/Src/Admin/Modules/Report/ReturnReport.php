<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Report;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReturnReport extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;
        if (!in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage($value): void
    {
        $value = is_numeric($value) ? (int) $value : 10;
        $this->perPage = in_array($value, [10, 25, 50, 100], true) ? $value : 10;
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.returns.create'));
    }

    public function getRowsProperty()
    {
        $query = DB::table('order_return_items')
            ->join('products', 'order_return_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id as product_id',
                'products.product_name',
                'categories.name as category_name',
                DB::raw('SUM(order_return_items.return_quantity) as total_return_qty')
            )
            // only rows that actually affect stock
            ->where('order_return_items.adjust_stock', true)
            ->groupBy('products.id', 'products.product_name', 'categories.name')
            ->orderBy('products.product_name');

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('products.product_name', 'like', "%{$s}%")
                    ->orWhere('categories.name', 'like', "%{$s}%");
            });
        }

        return $query->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('admin.Report.views.return-report', [
            'rows' => $this->rows,
        ])->layout('panel::layout.app', [
            'title' => 'Return Report',
            'breadcrumb' => [
                ['Reports', '#'],
                ['Return Report', '#'],
            ],
        ]);
    }
}

