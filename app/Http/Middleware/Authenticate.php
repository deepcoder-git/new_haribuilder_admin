<?php

namespace App\Http\Middleware;

use Error;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        $routeName = $request->route()?->getName();

        return match (true) {
            str_starts_with($routeName ?? '', 'admin.') => route('admin.auth.login'),
            default => throw new Error('Authenticate guard Redirect not found'),
        };
    }
}
