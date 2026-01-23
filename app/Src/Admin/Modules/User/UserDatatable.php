<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\User;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class UserDatatable extends Component
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

    #[Url(as: 'role')]
    public ?string $filter_role = null;

    public ?string $tempRoleFilter = null;

    public ?int $userToDelete = null;
    public ?string $userNameToDelete = null;
    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;
        
        if (!in_array($this->perPage, [10, 25, 50, 100])) {
            $this->perPage = 10;
        }

        // Initialize temp filters from actual filters (sync with URL parameters)
        $this->syncTempFilters();
    }

    public function syncTempFilters(): void
    {
        $this->tempRoleFilter = $this->filter_role ?: 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->filter_role = $this->tempRoleFilter === 'all' ? null : $this->tempRoleFilter;
        $this->resetPage();
        
        // Dispatch event to close dropdown
        $this->dispatch('close-filter-dropdown');
    }

    public function resetFilters(): void
    {
        $this->filter_role = null;
        $this->tempRoleFilter = 'all';
        $this->search = '';
        $this->resetPage();
        
        // Dispatch event to reset custom selects (but keep dropdown open)
        $this->dispatch('reset-filter-selects');
    }

    public function hasActiveFilters(): bool
    {
        return ($this->filter_role && $this->filter_role !== 'all');
    }

    public function updatedPerPage($value): void
    {
        $perPageValue = is_numeric($value) ? (int) $value : 10;
        
        if (in_array($perPageValue, [10, 25, 50, 100])) {
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
        $this->redirect(route('admin.users.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.users.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.users.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        $user = Moderator::findOrFail($id);
        $this->userToDelete = (int) $id;
        $this->userNameToDelete = $user->name;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (!$this->userToDelete) {
            return;
        }

        try {
            Moderator::findOrFail($this->userToDelete)->delete();
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'User deleted successfully!']);
            $this->resetPage();
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function closeDeleteModal(): void
    {
        $this->userToDelete = null;
        $this->userNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function toggleStatus(int|string $id): void
    {
        try {
            $user = Moderator::findOrFail($id);
            $newStatus = $user->status === StatusEnum::Active->value 
                ? StatusEnum::InActive->value 
                : StatusEnum::Active->value;
            
            $user->update(['status' => $newStatus]);
            
            $statusText = $newStatus === StatusEnum::Active->value ? 'activated' : 'deactivated';
            $this->dispatch('show-toast', [
                'type' => 'success', 
                'message' => "User {$statusText} successfully!"
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getUsersProperty()
    {
        $query = Moderator::query();

        // Exclude Super Admin users from listing
        $query->where('role', '!=', RoleEnum::SuperAdmin->value);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('mobile_number', 'like', "%{$this->search}%");
            });
        }

        if ($this->filter_role) {
            $query->where('role', $this->filter_role);
        }

        $sortField = in_array($this->sortField, ['name', 'email', 'mobile_number', 'role']) 
            ? $this->sortField 
            : 'id';

        return $query->orderBy($sortField, $this->sortDirection)
                     ->paginate($this->perPage);
    }

    public function getRolesProperty(): array
    {
        return [
            RoleEnum::Admin->value => 'Admin',
            RoleEnum::SiteSupervisor->value => 'Site Supervisor',
            RoleEnum::StoreManager->value => 'Store Manager',
            RoleEnum::TransportManager->value => 'Transport Manager',
        ];
    }

    public function renderImage($user): string
    {
        if ($user->image) {
            $imageUrl = Storage::url($user->image);
            return '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($user->name) . '" style="width: 48px; height: 48px; object-fit: cover; border-radius: 50%; cursor: pointer; border: 2px solid #e5e7eb; transition: all 0.2s ease;" class="user-image-zoom" data-image-url="' . htmlspecialchars($imageUrl) . '" data-user-name="' . htmlspecialchars($user->name) . '" onmouseover="this.style.borderColor=\'#1e3a8a\'; this.style.transform=\'scale(1.05)\';" onmouseout="this.style.borderColor=\'#e5e7eb\'; this.style.transform=\'scale(1)\';">';
        }
        
        return '<div style="width: 48px; height: 48px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #e5e7eb;"><i class="fa-solid fa-user text-gray-400" style="font-size: 1.25rem;"></i></div>';
    }

    public function render(): View
    {
        return view('admin::User.views.user-datatable', [
            'users' => $this->users,
            'roles' => $this->roles,
        ])->layout('panel::layout.app', [
            'title' => 'User Management',
            'breadcrumb' => [['User', route('admin.users.index')]],
        ]);
    }
}

