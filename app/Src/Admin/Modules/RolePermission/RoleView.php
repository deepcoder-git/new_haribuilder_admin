<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\RolePermission;

use App\Models\Moderator;
use App\Utility\Enums\PermissionEnum;
use App\Utility\Enums\RoleEnum;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RoleView extends Component
{
    public string $roleValue;

    public function mount(string $role): void
    {
        $this->roleValue = $role;
    }

    protected function getPermissionGroup(PermissionEnum $permission): string
    {
        return 'Modules';
    }

    protected function formatPermissionLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    public function getRoleProperty(): ?RoleEnum
    {
        return RoleEnum::tryFrom($this->roleValue);
    }

    public function getRolePermissionsProperty(): array
    {
        $role = $this->role;
        if (!$role) {
            return [];
        }

        $permissions = [];
        $rolePerms = $role->getPermissions();
        
        if ($role === RoleEnum::SuperAdmin) {
            $allPermissions = PermissionEnum::cases();
            foreach ($allPermissions as $permission) {
                $group = $this->getPermissionGroup($permission);
                if (!isset($permissions[$group])) {
                    $permissions[$group] = [];
                }
                $permissions[$group][] = [
                    'value' => $permission->value,
                    'label' => $this->formatPermissionLabel($permission->value),
                ];
            }
        } else {
            foreach ($rolePerms as $perm) {
                if ($perm instanceof PermissionEnum) {
                    $group = $this->getPermissionGroup($perm);
                    if (!isset($permissions[$group])) {
                        $permissions[$group] = [];
                    }
                    $permissions[$group][] = [
                        'value' => $perm->value,
                        'label' => $this->formatPermissionLabel($perm->value),
                    ];
                }
            }
        }

        return $permissions;
    }

    public function getUserCountProperty(): int
    {
        return Moderator::where('role', $this->roleValue)->count();
    }

    public function getModulesWithActionsProperty(): array
    {
        $role = $this->role;
        if (!$role) {
            return [];
        }

        return $this->getModulesWithActions($role);
    }

    protected function getModulesWithActions(RoleEnum $role): array
    {
        $modules = [];
        $rolePerms = array_map(fn($p) => $p->value, $role->getPermissions());
        $isSuperAdmin = $role === RoleEnum::SuperAdmin;

        // Complete module list matching the UI image
        // Note: Some modules may share permissions (e.g., Materials/Products under Inventory)
        $moduleMap = [
            'Dashboard' => ['permission' => 'dashboard', 'route' => 'admin.dashboard'],
            'Site Management' => ['permission' => 'settings', 'route' => 'admin.sites.index'],
            'Material Management' => ['permission' => 'inventory', 'route' => 'admin.materials.index'], // Materials grouped under inventory
            'Product Management' => ['permission' => 'inventory', 'route' => 'admin.products.index'],
            'Supplier Management' => ['permission' => 'inventory', 'route' => 'admin.suppliers.index'], // Suppliers grouped under inventory
            'Role Management' => ['permission' => 'roles', 'route' => 'admin.role-permissions.index'],
            'Order Management' => ['permission' => 'orders', 'route' => 'admin.orders.index'],
            'LPO Management' => ['permission' => 'orders', 'route' => 'admin.lpo.index'], // LPO grouped under orders or super admin only
            'User Management' => ['permission' => 'users', 'route' => 'admin.users.index'],
            'Wastage Management' => ['permission' => 'wastage', 'route' => 'admin.wastages.index'],
            'Reports' => ['permission' => 'reports', 'route' => 'admin.reports'],
        ];

        // Always show all modules, but mark permissions based on role
        foreach ($moduleMap as $moduleName => $config) {
            // Check if role has the permission (or is super admin)
            // For LPO, only super admin has access
            if ($moduleName === 'LPO Management') {
                $hasPermission = $isSuperAdmin;
            } else {
                $hasPermission = $isSuperAdmin || in_array($config['permission'], $rolePerms);
            }
            
            $modules[] = [
                'name' => $moduleName,
                'permission' => $config['permission'],
                'route' => $config['route'],
                'actions' => $this->getModuleActions($moduleName, $isSuperAdmin, $hasPermission, $rolePerms),
            ];
        }

        return $modules;
    }

    protected function getModuleActions(string $moduleName, bool $isSuperAdmin, bool $hasPermission, array $rolePerms): array
    {
        // Super Admin has all permissions
        if ($isSuperAdmin) {
            return ['view' => true, 'add' => true, 'edit' => true, 'delete' => true];
        }

        // For other roles, check if they have the permission
        $actions = [
            'view' => false,
            'add' => false,
            'edit' => false,
            'delete' => false,
        ];

        if ($hasPermission) {
            // If role has permission, grant all actions
            $actions['view'] = true;
            $actions['add'] = true;
            $actions['edit'] = true;
            $actions['delete'] = true;
        }

        return $actions;
    }

    public function render(): View
    {
        $role = $this->role;
        
        if (!$role) {
            abort(404, 'Role not found');
        }

        try {
            $breadcrumbUrl = route('admin.role-permissions.index');
        } catch (\Exception $e) {
            $breadcrumbUrl = '#';
        }

        return view('admin::RolePermission.views.view', [
            'role' => $role,
            'rolePermissions' => $this->rolePermissions,
            'userCount' => $this->userCount,
            'modulesWithActions' => $this->modulesWithActions,
        ])->layout('panel::layout.app', [
            'title' => 'View Role',
            'breadcrumb' => [
                ['Role Management', $breadcrumbUrl],
                ['View Role', '#'],
            ],
        ]);
    }
}

