<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Lpo;

use App\Models\Order;
use App\Models\Site;
use App\Models\Moderator;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LpoDatatable extends Component
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

    #[Url(as: 'status')]
    public ?string $filter_status = null;

    #[Url(as: 'site')]
    public ?int $filter_site_id = null;

    #[Url(as: 'priority')]
    public ?string $filter_priority = null;

    public string $tempStatusFilter = 'all';
    public string $tempSiteFilter = 'all';
    public string $tempPriorityFilter = 'all';

    public ?int $orderToDelete = null;
    public ?string $orderNameToDelete = null;
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

    public function syncTempFilters(): void
    {
        $this->tempStatusFilter = $this->filter_status ?: 'all';
        $this->tempSiteFilter = $this->filter_site_id ? (string) $this->filter_site_id : 'all';
        $this->tempPriorityFilter = $this->filter_priority ?: 'all';
    }

    public function applyFilters(): void
    {
        $this->filter_status = ($this->tempStatusFilter === 'all' || $this->tempStatusFilter === '') ? null : $this->tempStatusFilter;
        $this->filter_site_id = ($this->tempSiteFilter === 'all' || $this->tempSiteFilter === '') ? null : (int) $this->tempSiteFilter;
        $this->filter_priority = ($this->tempPriorityFilter === 'all' || $this->tempPriorityFilter === '') ? null : $this->tempPriorityFilter;

        $this->resetPage();
        $this->dispatch('close-filter-dropdown');
    }

    public function resetFilters(): void
    {
        $this->filter_status = null;
        $this->filter_site_id = null;
        $this->filter_priority = null;
        $this->search = '';

        $this->syncTempFilters();
        $this->resetPage();
        $this->dispatch('reset-filter-selects');
    }

    public function hasActiveFilters(): bool
    {
        return (bool) ($this->filter_status || $this->filter_site_id || $this->filter_priority);
    }

    public function isSuperAdmin(): bool
    {
        /** @var Moderator|null $u */
        $u = auth('moderator')->user();
        return (bool) ($u && $u->getRole() === RoleEnum::SuperAdmin);
    }

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.lpo.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.lpo.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.lpo.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($id);
        $this->orderToDelete = (int) $id;
        $this->orderNameToDelete = 'LPO' . $order->id;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->orderToDelete = null;
        $this->orderNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->orderToDelete) {
            return;
        }

        try {
            Order::query()->where('id', $this->orderToDelete)->delete();
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'LPO deleted successfully!']);
            $this->closeDeleteModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function renderOrderId(Order $order): string
    {
        $id = (int) $order->id;
        $url = route('admin.lpo.view', $id);
        return '<a href="' . e($url) . '" class="badge badge-light-dark" style="text-decoration:none;">LPO' . $id . '</a>';
    }

    public function renderParentOrderId(Order $order): string
    {
        // parent_order_id removed; keep compatibility by showing LPO id
        return $this->renderOrderId($order);
    }

    public function renderPriority(Order $order): string
    {
        $priority = (string) ($order->priority ?? '');
        $p = PriorityEnum::tryFrom($priority);
        $label = $p ? ($p->getName() ?? ucfirst($p->value)) : ($priority ? ucfirst($priority) : 'N/A');

        $badge = match ($priority) {
            PriorityEnum::High->value => 'badge-light-danger',
            PriorityEnum::Medium->value => 'badge-light-warning',
            PriorityEnum::Low->value => 'badge-light-success',
            default => 'badge-light-secondary',
        };

        return '<span class="badge ' . $badge . '">' . e($label) . '</span>';
    }

    public function renderOrderStatus(Order $order): string
    {
        $statusEnum = $order->status instanceof OrderStatusEnum
            ? $order->status
            : (OrderStatusEnum::tryFrom((string) ($order->status ?? '')) ?? OrderStatusEnum::Pending);

        $badge = match ($statusEnum) {
            OrderStatusEnum::Pending => 'badge-light-warning',
            OrderStatusEnum::Approved => 'badge-light-success',
            OrderStatusEnum::InTransit => 'badge-light-primary',
            OrderStatusEnum::Delivery => 'badge-light-info',
            OrderStatusEnum::OutOfDelivery => 'badge-light-primary',
            OrderStatusEnum::Rejected, OrderStatusEnum::Cancelled => 'badge-light-danger',
        };

        return '<span class="badge ' . $badge . '">' . e($statusEnum->getName()) . '</span>';
    }

    public function renderLpoSupplierStatuses(Order $order): string
    {
        $suppliers = $order->supplier();
        $count = is_iterable($suppliers) ? count($suppliers) : 0;
        return '<span class="badge badge-light-primary">' . e((string) $count) . ' supplier(s)</span>';
    }

    public function renderActionDropdown(Order $order): string
    {
        $id = (int) $order->id;
        $view = 'wire:click.prevent="openViewModal(' . $id . ')"';
        $edit = 'wire:click.prevent="openEditForm(' . $id . ')"';
        $del = 'wire:click.prevent="confirmDelete(' . $id . ')"';

        return '<div class="d-flex justify-content-center gap-1">'
            . '<a href="#" ' . $view . ' class="btn btn-sm btn-icon btn-light-primary" title="View"><i class="fa-solid fa-eye"></i></a>'
            . '<a href="#" ' . $edit . ' class="btn btn-sm btn-icon btn-light-info" title="Edit"><i class="fa-solid fa-pen"></i></a>'
            . '<a href="#" ' . $del . ' class="btn btn-sm btn-icon btn-light-danger" title="Delete"><i class="fa-solid fa-trash"></i></a>'
            . '</div>';
    }

    public function getOrdersProperty()
    {
        $query = Order::query()
            ->with(['site'])
            ->where('is_lpo', true);

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function (Builder $q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                    ->orWhereHas('site', fn (Builder $sq) => $sq->where('name', 'like', "%{$s}%"));
            });
        }

        if ($this->filter_status) {
            $query->where('status', $this->filter_status);
        }
        if ($this->filter_site_id) {
            $query->where('site_id', $this->filter_site_id);
        }
        if ($this->filter_priority) {
            $query->where('priority', $this->filter_priority);
        }

        $sortField = in_array($this->sortField, ['created_at', 'id', 'status'], true) ? $this->sortField : 'created_at';
        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function render(): View
    {
        $sites = Site::query()->select(['id', 'name'])->orderBy('name')->get();

        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::LPO.views.lpo-datatable', [
            'orders' => $this->orders,
            'sites' => $sites,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'LPO Management',
            'breadcrumb' => [['LPOs', route('admin.lpo.index')]],
        ]);
    }
}


