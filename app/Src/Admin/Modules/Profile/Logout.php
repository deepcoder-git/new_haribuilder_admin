<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Profile;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Logout extends Controller
{
    public function __invoke(Request $request, AuthService $authService): RedirectResponse
    {
        $authService->logout('moderator');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        flashAlert(__('admin.logout.to_login'), 'success');

        return redirect()->route('admin.auth.login');
    }
}
