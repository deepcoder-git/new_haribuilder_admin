<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\RolePermission;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class RoleDatatable extends Component
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
        
        if (!in_array($this->perPage, [10, 25, 50, 100])) {
            $this->perPage = 10;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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

    public function openViewModal(string|int $role): void
    {
        $roleValue = (string) $role;
        
        if (empty($roleValue) || $roleValue === '0') {
            return;
        }
        
        // Prevent viewing Super Admin role
        if ($roleValue === RoleEnum::SuperAdmin->value) {
            return;
        }
        
        $roleEnum = RoleEnum::tryFrom($roleValue);
        
        if ($roleEnum) {
            $this->redirect(route('admin.role-permissions.view', ['role' => $roleValue]));
        }
    }

    public function getRolesListProperty(): array
    {
        $roles = [];
        foreach (RoleEnum::cases() as $role) {
            // Exclude SuperAdmin from display
            if ($role === RoleEnum::SuperAdmin) {
                continue;
            }
            
            if (in_array($role->value, array_keys($this->getRolesProperty()))) {
                $label = $role->label();
                
                if (!empty($this->search) && stripos($label, $this->search) === false) {
                    continue;
                }
                
                $userCount = Moderator::where('role', $role->value)->count();
                
                $roleObj = new \stdClass();
                $roleObj->id = $role->value;
                $roleObj->value = $role->value;
                $roleObj->label = $label;
                $roleObj->user_count = $userCount;
                
                $roles[] = $roleObj;
            }
        }
        return $roles;
    }

    public function getRolesProperty(): array
    {
        return [
            RoleEnum::Admin->value => 'Admin',
            RoleEnum::SiteSupervisor->value => 'Site Supervisor',
            RoleEnum::StoreManager->value => 'Store Manager',
            RoleEnum::WorkshopStoreManager->value => 'Workshop Store Manager',
            RoleEnum::TransportManager->value => 'Transport Manager',
        ];
    }

    public function getRolesPaginatedProperty()
    {
        $roles = $this->rolesList;
        $currentPage = $this->getPage();
        $perPage = $this->perPage;
        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($roles, $offset, $perPage);
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            count($roles),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => 'page',
            ]
        );
    }

    public function render(): View
    {
        return view('admin::RolePermission.views.role-datatable', [
            'roles' => $this->rolesPaginated,
        ])->layout('panel::layout.app', [
            'title' => 'Role & Permission Management',
            'breadcrumb' => [['Role & Permission', route('admin.role-permissions.index')]],
        ]);
    }
}

