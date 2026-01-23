<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Site;

use App\Models\Moderator;
use App\Models\Site;
use App\Utility\Enums\OrderStatusEnum;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use App\Utility\Enums\RoleEnum;

class SiteDatatable extends Component
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

    #[Url(as: 'supervisor')]
    public ?string $filter_supervisor = null;

    #[Url(as: 'status')]
    public ?string $filter_status = null; // active|inactive|null

    public ?string $tempSupervisorFilter = null;
    public ?string $tempStatusFilter = null;

    public ?int $siteToDelete = null;
    public ?string $siteNameToDelete = null;
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
        $this->tempSupervisorFilter = $this->filter_supervisor ?: 'all';
        $this->tempStatusFilter = $this->filter_status ?: 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->filter_supervisor = ($this->tempSupervisorFilter === 'all' || $this->tempSupervisorFilter === null)
            ? null
            : $this->tempSupervisorFilter;

        $this->filter_status = ($this->tempStatusFilter === 'all' || $this->tempStatusFilter === null)
            ? null
            : $this->tempStatusFilter;

        $this->resetPage();
        $this->dispatch('close-filter-dropdown');
    }

    public function resetFilters(): void
    {
        $this->filter_supervisor = null;
        $this->filter_status = null;
        $this->tempSupervisorFilter = 'all';
        $this->tempStatusFilter = 'all';
        $this->search = '';
        $this->resetPage();

        $this->dispatch('reset-filter-selects');
    }

    public function hasActiveFilters(): bool
    {
        return ($this->filter_supervisor && $this->filter_supervisor !== 'all')
            || ($this->filter_status && $this->filter_status !== 'all');
    }

    public function updatedPerPage($value): void
    {
        $perPageValue = is_numeric($value) ? (int) $value : 10;

        if (in_array($perPageValue, [10, 25, 50, 100], true)) {
            $this->perPage = $perPageValue;
        } else {
            $this->perPage = 10;
        }

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
        $this->redirect(route('admin.sites.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.sites.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.sites.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        $site = Site::findOrFail($id);
        $this->siteToDelete = (int) $id;
        $this->siteNameToDelete = $site->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->siteToDelete = null;
        $this->siteNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->siteToDelete) {
            return;
        }

        try {
            $site = Site::with(['orders'])->findOrFail($this->siteToDelete);

            // Do not allow delete when the site has pending orders
            $hasPendingOrders = $site->orders()
                ->where('status', OrderStatusEnum::Pending->value)
                ->exists();

            if ($hasPendingOrders) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'This site cannot be deleted because it has pending orders.',
                ]);
                $this->closeDeleteModal();
                return;
            }

            // When no pending orders, "delete" should keep the site visible in the list.
            // So we simply mark the site as inactive instead of deleting it.
            $site->update(['status' => 0]);

            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Site deleted successfully!']);
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
            $site = Site::findOrFail($id);
            $site->update(['status' => !$site->status]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Site status updated successfully!',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getSiteManagersProperty()
    {
        // Note: used for filter dropdown; show all moderators (optionally scope to role later)
        return Moderator::where('role', RoleEnum::SiteSupervisor->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getSitesProperty()
    {
        $query = Site::query()->with('siteManager');

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        if ($this->filter_supervisor) {
            $query->where('site_manager_id', $this->filter_supervisor);
        }

        if ($this->filter_status) {
            if ($this->filter_status === 'active') {
                $query->where('status', true);
            } elseif ($this->filter_status === 'inactive') {
                $query->where('status', false);
            }
        }

        $sortField = in_array($this->sortField, ['name', 'start_date', 'created_at'], true)
            ? $this->sortField
            : 'id';

        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Site.views.site-datatable', [
            'sites' => $this->sites,
            'siteManagers' => $this->siteManagers,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Site Management',
            'breadcrumb' => [['Site', route('admin.sites.index')]],
        ]);
    }
}


