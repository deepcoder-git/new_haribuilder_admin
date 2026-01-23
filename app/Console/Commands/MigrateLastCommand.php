<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateLastCommand extends Command
{
    protected $signature = 'migrate:last {count=1}';
    protected $description = 'Run the last N migration files in database/migrations';

    public function handle()
    {
        $count = (int) $this->argument('count');

        // Get migration files sorted by latest timestamp
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->sort()
            ->take(-$count)
            ->values();

        if ($migrations->isEmpty()) {
            $this->warn('No migration files found.');
            return;
        }

        $this->info("Running the last {$count} migration(s):");
        foreach ($migrations as $file) {
            $this->line(basename($file));
            Artisan::call('migrate', ['--path' => str_replace(base_path() . '/', '', $file)]);
            $this->line(Artisan::output());
        }

        $this->info('Done.');
    }
}
