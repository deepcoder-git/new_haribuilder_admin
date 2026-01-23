<?php

declare(strict_types=1);

namespace App\Src\Api\Infrastructure;

use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/../Modules', 'api');
    }

    public function register(): void
    {
        //
    }
}
