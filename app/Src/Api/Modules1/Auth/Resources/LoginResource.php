<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Utility\Enums\PermissionEnum;
use App\Utility\Enums\RoleEnum;

class LoginResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissions = array_map(fn($status) => $status->value, PermissionEnum::cases());
        $modulesWithPermission = array_fill_keys($permissions, 0);

        $permissions = $this->getPermissions() ? array_map(fn($p) => $p->value, $this->getPermissions()) : [];

        $modules = [];

        foreach ($permissions as $permission) {
            $modulesWithPermission[$permission] = 1;
        }

        $groupedPermissions = [];

        // Define the module groups with their respective permissions
        $permissionGroups = [
            "orders" => PermissionEnum::getPermissionGroup('orders'),
            "inventory" => PermissionEnum::getPermissionGroup('inventory'),
            "dashboard" => PermissionEnum::getPermissionGroup('dashboard'),
            "users" => PermissionEnum::getPermissionGroup('users'),
            "wastage" => PermissionEnum::getPermissionGroup('wastage'),
            "reports" => PermissionEnum::getPermissionGroup('reports'),
            "settings" => PermissionEnum::getPermissionGroup('settings'),
            "delivery" => PermissionEnum::getPermissionGroup('delivery')
        ];

        // Loop through each permission group and check if any permission is granted
        foreach ($permissionGroups as $group => $permissions) {
            $groupedPermissions[$group] = hasPermission($modulesWithPermission, $permissions) ? 1 : 0;
        }

        // Remove order permissions for transport managers
        if ($this->role === RoleEnum::TransportManager) {
            $groupedPermissions['orders'] = 0;
        }

        $groupedPermissions['profile'] = 1;

        return [
            'id' => $this->id,
            'type' => 'moderator',
            'name' => $this->name,
            'email' => $this->email,
            // Predefine Seychelles country code for mobile number
            'mobile' => "+248 ".$this->mobile_number,
            'profile_image' => Storage::disk('public')->url($this->image),
            'status' => $this->status,
            'role' => $this->role?->value ?? null,
            'role_name' => $this->role?->label() ?? null,
            'permissions' => $groupedPermissions,
            'token' => $this->token,
        ];
    }
}
