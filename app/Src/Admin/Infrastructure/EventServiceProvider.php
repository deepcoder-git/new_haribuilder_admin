<?php

declare(strict_types=1);

namespace App\Src\Admin\Infrastructure;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as MainEventServiceProvider;

class EventServiceProvider extends MainEventServiceProvider
{
    protected $listen = [
        // Add your event listeners here
    ];
}
