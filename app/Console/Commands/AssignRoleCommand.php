<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use Illuminate\Console\Command;

class AssignRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-role 
                            {email : The email of the user}
                            {role : The role to assign (super_admin, admin, moderator, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a role to a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        // Find user
        $user = Moderator::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        // Validate role
        $role = RoleEnum::tryFrom($roleName);

        if (!$role) {
            $this->error("Invalid role '{$roleName}'.");
            $this->info('Available roles: ' . implode(', ', array_map(fn($r) => $r->value, RoleEnum::cases())));
            return Command::FAILURE;
        }

        // Assign role
        $user->assignRole($role);

        $this->info("Role '{$roleName}' assigned to user '{$email}' successfully!");
        $this->info("User: {$user->name}");
        $this->info("Role: {$role->getName()}");

        return Command::SUCCESS;
    }
}

