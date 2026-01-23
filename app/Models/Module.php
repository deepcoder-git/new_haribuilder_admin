<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_name',
        'name',
        'icon',
        'index_route',
        'sub_routes',
        'parent_id',
        'order',
        'is_active',
        'required_role',
        'theme_color',
    ];

    protected $casts = [
        'sub_routes' => 'array',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get child modules
     */
    public function children(): HasMany
    {
        return $this->hasMany(Module::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get parent module
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }

    /**
     * Get modules by user based on role and permissions
     */
    public static function getModulesByUser($user = null)
    {
        if (!$user) {
            $user = auth('moderator')->user();
        }

        if (!$user) {
            return collect([]);
        }

        $userRole = $user->getRole();
        $userPermissions = array_map(fn($p) => $p->value, $user->getPermissions());
        $cacheVersion = Cache::get('modules_cache_version', 1);
        // Bump cache key schema so new modules like admin.reports show immediately without manual cache clear
        $cacheKey = 'modules_user_v2_' . $user->id . '_' . md5(serialize([
            $userRole?->value,
            $userPermissions
        ])) . '_v' . $cacheVersion;

        return Cache::remember($cacheKey, 3600, function () use ($user, $userRole, $userPermissions) {
            $query = static::where('is_active', true)
                ->whereNull('parent_id')
                ->with(['children' => function ($query) use ($userRole) {
                    $query->where('is_active', true);
                    // Hide LPO children for non-SuperAdmin users
                    if ($userRole !== \App\Utility\Enums\RoleEnum::SuperAdmin) {
                        $query->where('unique_name', '!=', 'admin.lpo');
                    }
                    $query->orderBy('order');
                }])
                ->orderBy('order');

            if ($user->hasRole(\App\Utility\Enums\RoleEnum::SuperAdmin)) {
                return $query->get();
            }

            $modulePermissionMap = self::getModulePermissionMap();

            if ($userRole && $userRole === \App\Utility\Enums\RoleEnum::TransportManager) {
                return $query->whereIn('unique_name', ['admin.deliveries'])->get();
            }

            $allowedUniqueNames = [];
            $allModules = $query->get();

            foreach ($allModules as $module) {
                // Hide LPO module for Store Managers and Site Supervisors - only Super Admin can see it
                if ($module->unique_name === 'admin.lpo') {
                    if ($userRole !== \App\Utility\Enums\RoleEnum::SuperAdmin) {
                        continue;
                    }
                }
                
                if ($module->required_role) {
                    $requiredRoles = array_map('trim', explode(',', $module->required_role));
                    $userRoleValue = $userRole ? $userRole->value : null;
                    if (!in_array($userRoleValue, $requiredRoles)) {
                        continue;
                    }
                }

                $requiredPermissions = $modulePermissionMap[$module->unique_name] ?? [];
                
                if (empty($requiredPermissions)) {
                    continue;
                }

                foreach ($requiredPermissions as $permission) {
                    if (in_array($permission, $userPermissions)) {
                        $allowedUniqueNames[] = $module->unique_name;
                        break;
                    }
                }
            }

            return $allModules->whereIn('unique_name', $allowedUniqueNames)->values();
        });
    }

    public static function clearModulesCache()
    {
        $cacheVersion = Cache::get('modules_cache_version', 1);
        Cache::forever('modules_cache_version', $cacheVersion + 1);
    }

    protected static function getModulePermissionMap(): array
    {
        return [
            'admin.dashboard' => ['dashboard'],
            'admin.sites' => ['settings'],
            'admin.modules' => ['settings'],
            'admin.reports' => ['inventory'],
            'admin.user-management' => ['users', 'roles'],
            'admin.users' => ['users'],
            'admin.role-permissions' => ['roles'],
            'admin.products' => ['inventory'],
            'admin.categories' => ['inventory'],
            'admin.units' => ['inventory'],
            'admin.suppliers' => ['inventory'],
            'admin.product-purchases' => ['inventory'],
            'admin.stocks' => ['inventory'],
            'admin.stock-transfers' => ['inventory'],
            'admin.orders' => ['orders'],
            'admin.deliveries' => ['orders'],
        ];
    }
}

