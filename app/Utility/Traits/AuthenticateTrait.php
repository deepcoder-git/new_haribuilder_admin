<?php

declare(strict_types=1);

namespace App\Utility\Traits;

use App\Services\AuthService;
use App\Utility\Enums\UserTypeEnum;
use Illuminate\Support\Facades\Password;

trait AuthenticateTrait
{
    protected ?AuthService $authService = null;

    protected function getAuthService(): AuthService
    {
        if ($this->authService === null) {
            $this->authService = app(AuthService::class);
        }
        return $this->authService;
    }

    protected function resetPassword(array $credentials): string
    {
        $guard = $this->guard ?? 'moderator';
        $userType = match ($guard) {
            'staff' => UserTypeEnum::Staff,
            'moderator' => UserTypeEnum::Moderator,
            default => UserTypeEnum::Moderator,
        };

        return $this->getAuthService()->resetPassword(
            $credentials['email'],
            $credentials['password'],
            $credentials['token'],
            $userType
        );
    }
}

