<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Moderator;
use Illuminate\Console\Command;

class ListUserPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:permissions {email : The email of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all permissions for a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        // Find user
        $user = Moderator::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        $this->info("User Information:");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("Status: {$user->status}");

        // Get role
        $role = $user->getRole();
        if ($role) {
            $this->info("\nRole: {$role->getName()} ({$role->value})");
        } else {
            $this->warn("\nNo role assigned.");
        }

        // Get permissions
        $permissions = $user->getPermissions();

        if (empty($permissions)) {
            $this->warn("\nNo permissions found.");
            return Command::SUCCESS;
        }

        $this->info("\nPermissions (" . count($permissions) . "):");
        $this->newLine();

        $tableData = [];
        foreach ($permissions as $permission) {
            $tableData[] = [
                $permission->value,
                $permission->getName(),
            ];
        }

        $this->table(['Permission', 'Name'], $tableData);

        return Command::SUCCESS;
    }
}

