<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ModuleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Clear modules cache before seeding (version bump)
        Module::clearModulesCache();

        Schema::disableForeignKeyConstraints();
        DB::table('modules')->truncate();
        Schema::enableForeignKeyConstraints();

        $this->createModule($this->adminModules());

        // Clear modules cache after seeding to ensure fresh data everywhere
        Module::clearModulesCache();
    }

    private function adminModules(): array
    {
        return [
            [
                'unique_name' => 'admin.dashboard',
                'name' => 'Dashboard',
                'icon' => '<i class="fa-solid fa-gauge-high"></i>',
                'index_route' => 'admin.dashboard',
                'sub_routes' => ['admin.index'],
                'order' => 1,
                'is_active' => true,
                'children' => [],
            ],
            [
                'unique_name' => 'admin.sites',
                'name' => 'Sites',
                'icon' => '<i class="fa-solid fa-building"></i>',
                'index_route' => 'admin.sites.index',
                'sub_routes' => [
                    'admin.sites.create',
                    'admin.sites.edit',
                    'admin.sites.view',
                ],
                'order' => 2,
                'is_active' => true,
                'children' => [],
            ],
            [
                // Suppliers (parent accordion). Important: use unique_name 'admin.suppliers' so permissions map works.
                'unique_name' => 'admin.suppliers',
                'name' => 'Suppliers',
                'icon' => '<i class="fa-solid fa-truck"></i>',
                'index_route' => null,
                'sub_routes' => [],
                'order' => 3,
                'is_active' => true,
                'children' => [
                    [
                        'unique_name' => 'admin.suppliers.items',
                        'name' => 'Suppliers',
                        'icon' => '<i class="fa-solid fa-truck"></i>',
                        'index_route' => 'admin.suppliers.index',
                        'sub_routes' => [
                            'admin.suppliers.index',
                            'admin.suppliers.create',
                            'admin.suppliers.edit',
                            'admin.suppliers.view',
                        ],
                        'order' => 1,
                        'is_active' => true,
                        'children' => [],
                    ],
                    // [
                    //     // Purchases module can be added later; keep route empty to avoid missing-route badge.
                    //     'unique_name' => 'admin.suppliers.purchases',
                    //     'name' => 'Purchases',
                    //     'icon' => '<i class="fa-solid fa-receipt"></i>',
                    //     'index_route' => null,
                    //     'sub_routes' => [],
                    //     'order' => 2,
                    //     'is_active' => true,
                    //     'children' => [],
                    // ],
                ],
            ],
            [
                'unique_name' => 'admin.user-management',
                'name' => 'Users',
                'icon' => '<i class="fa-solid fa-users"></i>',
                'index_route' => null,
                'sub_routes' => [],
                'order' => 8,
                'is_active' => true,
                'required_role' => 'super_admin',
                'children' => [
                    [
                        'unique_name' => 'admin.users',
                        'name' => 'Users',
                        'icon' => '<i class="fa-solid fa-users"></i>',
                        'index_route' => 'admin.users.index',
                        'sub_routes' => ['admin.users.view'],
                        'order' => 1,
                        'is_active' => true,
                        'required_role' => 'super_admin',
                        'children' => [],
                    ],
                    [
                        'unique_name' => 'admin.role-permissions',
                        'name' => 'Roles',
                        'icon' => '<i class="fa-solid fa-user-shield"></i>',
                        'index_route' => 'admin.role-permissions.index',
                        'sub_routes' => [],
                        'order' => 2,
                        'is_active' => true,
                        'required_role' => 'super_admin',
                        'children' => [],
                    ],
                ],
            ],
        ];
    }

    private function createModule(array $modules, ?int $parentId = null): void
    {
        foreach ($modules as $module) {
            $indexRoute = $module['index_route'] ?? null;
            $subRoutes = $module['sub_routes'] ?? [];

            if (!is_array($subRoutes)) {
                $subRoutes = [];
            }

            if ($indexRoute) {
                $subRoutes[] = $indexRoute;
                $subRoutes = array_values(array_unique($subRoutes));
            }

            $created = new Module([
                'unique_name' => $module['unique_name'],
                'name' => $module['name'],
                'icon' => $module['icon'] ?? null,
                'index_route' => $indexRoute,
                'sub_routes' => $subRoutes,
                'parent_id' => $parentId,
                'order' => (int) ($module['order'] ?? 0),
                'is_active' => (bool) ($module['is_active'] ?? true),
                'required_role' => $module['required_role'] ?? null,
                'theme_color' => $module['theme_color'] ?? null,
            ]);
            $created->save();

            $children = $module['children'] ?? [];
            if (is_array($children) && !empty($children)) {
                $this->createModule($children, $created->id);
            }
        }
    }
}
