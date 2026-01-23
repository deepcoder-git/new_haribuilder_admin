<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Utility\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockAdminFromApi
{
    /**
     * Handle an incoming request.
     * Block Super Admin and Admin from accessing mobile API
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $userRole = $user->getRole();

        if ($userRole === RoleEnum::SuperAdmin || $userRole === RoleEnum::Admin) {
            abort(403, 'Super Admin and Admin are not allowed to access the mobile API');
        }

        return $next($request);
    }
}
