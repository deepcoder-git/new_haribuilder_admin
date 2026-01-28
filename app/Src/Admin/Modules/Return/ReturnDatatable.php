<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Return;

use App\Models\OrderReturn;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ReturnDatatable extends Component
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
        $this->redirect(route('admin.returns.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.returns.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.returns.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        $this->redirect(route('admin.returns.view', $id));
    }

    public function getReturnsProperty()
    {
        $query = OrderReturn::query()
            ->with(['manager', 'site', 'order', 'items.product']);

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('manager', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('site', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%');
                })->orWhereHas('order', function ($sub) use ($search) {
                    $sub->where('id', (int) $search);
                })->orWhere('id', (int) $search);
            });
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('admin.Return.views.return-datatable', [
            'returns' => $this->returns,
        ])->layout('panel::layout.app', [
            'title' => 'Returns',
            'breadcrumb' => [
                ['Dashboard', route('admin.dashboard')],
                ['Returns', '#'],
            ],
        ]);
    }
}

