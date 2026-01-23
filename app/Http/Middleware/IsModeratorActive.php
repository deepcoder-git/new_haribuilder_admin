<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Utility\Enums\StatusEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsModeratorActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('moderator');
        if ($user?->status && StatusEnum::tryFrom($user->status)?->isActive()) {
            return $next($request);
        }
        Auth::guard('moderator')->logout();
        flashAlert('Your Account is disabled by administrator.', 'danger');

        return redirect()->route('admin.auth.login');
    }
}
