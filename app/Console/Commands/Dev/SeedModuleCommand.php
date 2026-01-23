<?php

declare(strict_types=1);

namespace App\Console\Commands\Dev;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedModuleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This Command Will Seed Module Again to Database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Artisan::call('db:seed --class=ModuleSeeder');
    }
}
