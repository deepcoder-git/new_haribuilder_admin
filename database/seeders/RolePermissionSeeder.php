<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Clearing existing role and permission data...');
        $this->command->newLine();

        $this->clearOldData();

        $this->command->info('🚀 Seeding Roles and Permissions...');
        $this->command->newLine();

        $this->createSuperAdmin();
        $this->createAdmin();
        // $this->createModerator();
        $this->createSiteManager();
        $this->createStoreManager();
        $this->createTransportManager();

        $this->command->newLine();
        $this->command->info('✅ Roles and Permissions seeded successfully!');
        $this->command->newLine();
        $this->command->info('📋 Users Created:');
        $this->command->info('   SuperAdmin: superadmin@test.com / password');
        $this->command->info('   Admin: admin@test.com / password');
        $this->command->info('   Moderator: moderator@test.com / password');
        $this->command->info('   Site Supervisor: site@test.com / password');
        $this->command->info('   Store Manager: store@test.com / password');
        $this->command->info('   Transport Manager: transport@test.com / password');
    }

    protected function clearOldData(): void
    {
        $oldRoles = [
            'principal', 'teacher', 'librarian', 'accountant', 'mess_manager',
            'non_teaching_staff', 'inventory_manager', 'hostel_manager', 'visitor',
            'parent', 'student'
        ];

        $usersWithOldRoles = Moderator::whereIn('role', $oldRoles)->get();
        $count = $usersWithOldRoles->count();

        if ($count > 0) {
            Moderator::whereIn('role', $oldRoles)->delete();
            $this->command->info("   ✅ Removed {$count} user(s) with old roles");
        }

        Moderator::whereNotNull('role')
            ->whereNotIn('role', [
                RoleEnum::SuperAdmin->value,
                RoleEnum::Admin->value,
                // RoleEnum::Moderator->value,
                RoleEnum::SiteSupervisor->value,
                RoleEnum::StoreManager->value,
                RoleEnum::TransportManager->value,
            ])
            ->update(['role' => null, 'permissions' => null]);

        $this->command->info('   ✅ Cleared invalid role and permission data');
    }

    protected function createSuperAdmin(): void
    {
        $user = Moderator::firstOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'status' => StatusEnum::Active->value,
                'type' => 'moderator',
                'role' => RoleEnum::SuperAdmin->value,
                'permissions' => null,
            ]
        );

        if (!$user->wasRecentlyCreated) {
            $user->update([
                'role' => RoleEnum::SuperAdmin->value,
                'permissions' => null,
                'status' => StatusEnum::Active->value,
            ]);
        }

        $this->command->info('✅ SuperAdmin user created/updated');
    }

    protected function createAdmin(): void
    {
        $user = Moderator::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin User',
                'password' => 'password',
                'status' => StatusEnum::Active->value,
                'type' => 'moderator',
                'role' => RoleEnum::Admin->value,
                'permissions' => null,
            ]
        );

        if (!$user->wasRecentlyCreated) {
            $user->update([
                'role' => RoleEnum::Admin->value,
                'permissions' => null,
                'status' => StatusEnum::Active->value,
            ]);
        }

        $this->command->info('✅ Admin user created/updated');
    }

    protected function createSiteManager(): void
    {
        $user = Moderator::firstOrCreate(
            ['email' => 'siteadmin@gmail.com'],
            [
                'name' => 'Site Supervisor',
                'password' => 'password',
                'status' => StatusEnum::Active->value,
                'type' => 'moderator',
                'role' => RoleEnum::SiteSupervisor->value,
                'permissions' => null,
            ]
        );

        if (!$user->wasRecentlyCreated) {
            $user->update([
                'role' => RoleEnum::SiteSupervisor->value,
                'permissions' => null,
                'status' => StatusEnum::Active->value,
            ]);
        }

        $this->command->info('✅ Site Supervisor user created/updated');
    }

    protected function createStoreManager(): void
    {
        $user = Moderator::firstOrCreate(
            ['email' => 'storeadmin@gmail.com'],
            [
                'name' => 'Store Manager',
                'password' => 'password',
                'status' => StatusEnum::Active->value,
                'type' => 'moderator',
                'role' => RoleEnum::StoreManager->value,
                'permissions' => null,
            ]
        );

        if (!$user->wasRecentlyCreated) {
            $user->update([
                'role' => RoleEnum::StoreManager->value,
                'permissions' => null,
                'status' => StatusEnum::Active->value,
            ]);
        }

        $this->command->info('✅ Store Manager user created/updated');
    }

    protected function createTransportManager(): void
    {
        $user = Moderator::firstOrCreate(
            ['email' => 'transportadmin@gmail.com'],
            [
                'name' => 'Transport Manager',
                'password' => 'password',
                'status' => StatusEnum::Active->value,
                'type' => 'moderator',
                'role' => RoleEnum::TransportManager->value,
                'permissions' => null,
            ]
        );

        if (!$user->wasRecentlyCreated) {
            $user->update([
                'role' => RoleEnum::TransportManager->value,
                'permissions' => null,
                'status' => StatusEnum::Active->value,
            ]);
        }

        $this->command->info('✅ Transport Manager user created/updated');
    }
}
