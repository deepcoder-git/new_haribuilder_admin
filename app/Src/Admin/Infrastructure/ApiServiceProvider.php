<?php

declare(strict_types=1);

namespace App\Src\Admin\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        // Load views from both locations for compatibility
        $this->loadViewsFrom(__DIR__.'/../Modules', 'admin');
        $this->loadViewsFrom(resource_path('views/admin'), 'admin');
    }

    public function boot(): void
    {
    }
}
