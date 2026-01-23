<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Src\Web\Infrastructure\ApiServiceProvider::class,
    App\Src\Admin\Infrastructure\ApiServiceProvider::class,
    App\Src\Admin\Infrastructure\EventServiceProvider::class,
    App\Src\Api\Infrastructure\ApiServiceProvider::class,
    CloudinaryLabs\CloudinaryLaravel\CloudinaryServiceProvider::class,
];
