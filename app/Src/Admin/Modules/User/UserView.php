<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\User;

use App\Models\Moderator;
use App\Utility\Enums\PermissionEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Livewire\BaseViewComponent;

class UserView extends BaseViewComponent
{
    protected function getModelClass(): string
    {
        return Moderator::class;
    }

    protected function getModelVariableName(): string
    {
        return 'user';
    }

    protected function getModuleName(): string
    {
        return 'User';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.users.index';
    }

    protected function getViewRouteName(): string
    {
        return 'admin.users.view';
    }

    protected function getRelations(): array
    {
        return [];
    }

    protected function getIcon(): string
    {
        return 'user';
    }

    protected function getAdditionalViewData(): array
    {
        $user = $this->model;
        $rolePermissions = [];

        if ($user && $user->role) {
            $role = $user->role instanceof RoleEnum ? $user->role : RoleEnum::tryFrom($user->role);
            
            if ($role) {
                $permissions = $role->getPermissions();
                
                foreach ($permissions as $permission) {
                    if ($permission instanceof PermissionEnum) {
                        $group = $this->getPermissionGroup($permission);
                        if (!isset($rolePermissions[$group])) {
                            $rolePermissions[$group] = [];
                        }
                        $rolePermissions[$group][] = $this->formatPermissionLabel($permission->value);
                    }
                }
            }
        }

        return [
            'rolePermissions' => $rolePermissions,
            'userRole' => $user && $user->role ? ($user->role instanceof RoleEnum ? $user->role->value : $user->role) : null,
        ];
    }


    protected function getPermissionGroup(PermissionEnum $permission): string
    {
        $value = $permission->value;
        
        if (str_starts_with($value, 'manage_')) {
            $parts = explode('_', $value);
            if (count($parts) > 1) {
                return ucfirst($parts[1]) . ' Management';
            }
        } elseif (str_starts_with($value, 'view_')) {
            $parts = explode('_', $value);
            if (count($parts) > 1) {
                return 'View ' . ucfirst($parts[1]);
            }
        } elseif (str_starts_with($value, 'create_')) {
            return 'Create Operations';
        } elseif (str_starts_with($value, 'edit_')) {
            return 'Edit Operations';
        } elseif (str_starts_with($value, 'delete_')) {
            return 'Delete Operations';
        } elseif (str_starts_with($value, 'approve_')) {
            return 'Approve Operations';
        }
        
        return 'General';
    }

    protected function formatPermissionLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}

