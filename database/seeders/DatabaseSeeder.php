<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ModuleSeeder::class);
        $this->call(RolePermissionSeeder::class);
        // Seed test orders covering different combinations for QA / manual testing
        if ($this->command && $this->command->option('class') !== self::class) {
            // Only run the heavy order test seeder when explicitly invoked via artisan db:seed --class=OrderTestSeeder
            $this->command->warn('OrderTestSeeder is available. Run: php artisan db:seed --class=Database\\Seeders\\OrderTestSeeder');
        }

        $moderator = Moderator::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Moderator',
                'password' => 'password',
                'status' => StatusEnum::Active->value,
                'type' => 'moderator',
                'role' => RoleEnum::SuperAdmin->value,
            ]
        );

        if (!isset($moderator->role) || !$moderator->role) {
            $moderator->update(['role' => RoleEnum::SuperAdmin->value]);
        }

        $this->command->info('Default moderator created successfully!');
        $this->command->info('Email: admin@example.com');
        $this->command->info('Password: password');
    }
}
