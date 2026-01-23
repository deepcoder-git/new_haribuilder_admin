<?php

namespace App\Src\Web\Infrastructure;

use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        
    }

    public function register(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
