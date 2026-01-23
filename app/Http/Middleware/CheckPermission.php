<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Utility\Enums\PermissionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->hasRole(\App\Utility\Enums\RoleEnum::SuperAdmin) || $user->hasRole(\App\Utility\Enums\RoleEnum::Admin)) {
            return $next($request);
        }

        $allowedPermissions = array_map(fn($perm) => PermissionEnum::tryFrom($perm), $permissions);
        $allowedPermissions = array_filter($allowedPermissions);

        if (empty($allowedPermissions)) {
            return $next($request);
        }

        $userPermissions = $user->getPermissions();
        $hasPermission = false;

        foreach ($allowedPermissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            abort(403, 'You do not have the required permission to access this resource');
        }

        return $next($request);
    }
}

