<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Utility\Enums\RoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        $userRole = $user->getRole();

        if (!$userRole) {
            abort(403, 'User does not have a role assigned');
        }

        $allowedRolesList = [];
        foreach ($roles as $role) {
            $roleParts = array_map('trim', explode(',', $role));
            foreach ($roleParts as $rolePart) {
                $roleEnum = RoleEnum::tryFrom($rolePart);
                if ($roleEnum) {
                    $allowedRolesList[] = $roleEnum;
                }
            }
        }

        if (empty($allowedRolesList)) {
            abort(403, 'No valid roles specified');
        }

        if (!in_array($userRole, $allowedRolesList, true)) {
            abort(403, 'You do not have the required role to access this resource');
        }

        return $next($request);
    }
}

