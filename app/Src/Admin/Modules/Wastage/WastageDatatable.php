<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Wastage;

use App\Models\Wastage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class WastageDatatable extends Component
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
        $this->redirect(route('admin.wastages.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.wastages.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.wastages.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        // For now, redirect to view page where delete logic can be handled later if needed.
        $this->redirect(route('admin.wastages.view', $id));
    }

    public function getWastagesProperty()
    {
        $query = Wastage::query()
            ->with(['manager', 'site', 'order', 'products.category']);

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('manager', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('site', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('products', function ($sub) use ($search) {
                    $sub->where('product_name', 'like', '%' . $search . '%');
                })->orWhere('id', (int) $search);
            });
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function renderProducts($wastage): string
    {
        if (!$wastage->relationLoaded('products')) {
            $wastage->load('products.category');
        }

        if ($wastage->products->isEmpty()) {
            return '<span class="text-muted">No products</span>';
        }

        $names = $wastage->products->map(function ($product) {
            return e($product->product_name);
        })->take(3)->implode(', ');

        if ($wastage->products->count() > 3) {
            $names .= ' + ' . ($wastage->products->count() - 3) . ' more';
        }

        return '<span>' . $names . '</span>';
    }

    public function renderType($wastage): string
    {
        if (!$wastage->type) {
            return '<span class="badge badge-light-secondary">N/A</span>';
        }

        $label = is_object($wastage->type) && method_exists($wastage->type, 'getName')
            ? $wastage->type->getName()
            : ucfirst(str_replace('_', ' ', (string) $wastage->type));

        return '<span class="badge badge-light-primary">' . e($label) . '</span>';
    }

    public function render(): View
    {
        return view('admin.Wastage.views.wastage-datatable', [
            'wastages' => $this->wastages,
        ])->layout('panel::layout.app', [
            'title' => 'Wastages',
            'breadcrumb' => [
                ['Dashboard', route('admin.dashboard')],
                ['Wastages', '#'],
            ],
        ]);
    }
}

