<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use App\Utility\Enums\UserTypeEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate user with email/phone and password
     */
    public function login(string $identifier, string $password, UserTypeEnum $userType, bool $remember = false): array
    {
        $guard = $userType->getGuard();

        // Rate limiting - Auto-clear if blocking (prevents rate limit lockout)
        $throttleKey = $this->getThrottleKey($guard, 'login');
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            // Auto-clear rate limiter to allow login (prevents permanent lockout)
            RateLimiter::clear($throttleKey);
        }

        // Determine if identifier is email or phone number
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        
        // Find user by email or mobile_number
        $user = null;
        if ($isEmail) {
            $user = Moderator::where('email', $identifier)->first();
        } else {
            // Remove any non-numeric characters for phone comparison
            $phoneNumber = preg_replace('/[^0-9]/', '', $identifier);
            $user = Moderator::where('mobile_number', $phoneNumber)
                ->orWhere('mobile_number', $identifier)
                ->first();
        }

        // Verify password and authenticate
        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 3600);
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Check if user is active
        if (!$this->isUserActive($user)) {
            RateLimiter::hit($throttleKey, 3600);
            throw ValidationException::withMessages([
                'email' => __('auth.account_disabled'),
            ]);
        }

        // Log the user in manually
        Auth::guard($guard)->login($user, $remember);

        // Clear rate limiter on success
        RateLimiter::clear($throttleKey);

        return [
            'user' => $user,
            'guard' => $guard,
        ];
    }

    /**
     * Login via API (returns token)
     */
    public function loginApi(string $identifier, string $password): array
    {
        // Determine if identifier is email or phone number
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        
        // Find user by email or mobile_number
        $user = null;
        if ($isEmail) {
            $user = Moderator::where('email', $identifier)->first();
        } else {
            // Remove any non-numeric characters for phone comparison
            $phoneNumber = preg_replace('/[^0-9]/', '', $identifier);
            $user = Moderator::where('mobile_number', $phoneNumber)
                ->orWhere('mobile_number', $identifier)
                ->first();
        }
        
        if (!$user || !Hash::check($password, $user->password)) {
            return [
                'hasError' => true,
                'error' => __('auth.failed'),
            ];
        }

        if (!$this->isUserActive($user)) {
            return [
                'hasError' => true,
                'error' => __('auth.account_disabled'),
            ];
        }

        // Prevent Super Admin and Admin from logging in via API
        $role = $user->getRole();
        if ($role === RoleEnum::SuperAdmin || $role === RoleEnum::Admin) {
            return [
                'hasError' => true,
                'error' => __('auth.unauthorized'),
            ];
        }

        // Create token
        $tokenName = $user->getRole()->value . '_token';
        $abilities = [$user->getRole()->value];

        if ($user instanceof Moderator && $user->getRole()) {
            $abilities[] = $user->getRole()->value;
        }

        $token = $user->createToken($tokenName, $abilities, now()->addMonth())->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Send password reset link
     */
    public function sendPasswordResetLink(string $email, UserTypeEnum $userType, ?string $resetUrl = null): string
    {
        $throttleKey = $this->getThrottleKey($userType->getGuard(), 'reset-password');
        
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        // Configure reset URL if provided (for web requests)
        if ($resetUrl) {
            \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) use ($resetUrl, $email) {
                return $resetUrl . '?token=' . $token . '&email=' . urlencode($email);
            });
        }

        $broker = $userType->getPasswordBroker();
        $status = Password::broker($broker)->sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            RateLimiter::hit($throttleKey, 3600);
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        RateLimiter::hit($throttleKey, 60);
        return $status;
    }

    /**
     * Send password reset link from APIs
     */
    public function sendPasswordResetLinkApi(string $email, RoleEnum $userType, ?string $resetUrl = null): array
    {
        
        $throttleKey = $this->getThrottleKey($userType->getGuard(), 'reset-password');

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return [
                'hasError' => true,
                'error' => __('auth.throttle', ['seconds' => $seconds]),
            ];
        }

        // Configure reset URL if provided (for web requests)
        if ($resetUrl) {            
            \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) use ($resetUrl, $email) {
                return $resetUrl . '?token=' . $token . '&email=' . urlencode($email);
            });
        }

        $broker = $userType->getPasswordBroker();
        $status = Password::broker($broker)->sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {   
            RateLimiter::hit($throttleKey, 3600);

            return [
                'hasError' => true,
                'error' => __($status),
            ];
        }

        RateLimiter::hit($throttleKey, 60);

        return [
            'status' => __($status),
        ];
    }

    /**
     * Reset password
     */
    public function resetPassword(string $email, string $password, string $token, UserTypeEnum $userType): string
    {
        $broker = $userType->getPasswordBroker();
        $status = Password::broker($broker)->reset(
            [
                'email' => $email,
                'password' => $password,
                'token' => $token,
            ],
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $status;
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword($user, string $currentPassword, string $newPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return [
                'hasError' => true,
                'error' => __('auth.password'),
            ];
        }

        $user->update(['password' => $newPassword]);
        return [
            'message' => __('auth.password_update'),
        ];
        
    }

    /**
     * Logout user
     */
    public function logout(string $guard): void
    {
        Auth::guard($guard)->logout();
    }

    /**
     * Logout API user (revoke token)
     */
    public function logoutApi($user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Check if user is active
     */
    protected function isUserActive($user): bool
    {
        if (!isset($user->status)) {
            return true;
        }

        $status = StatusEnum::tryFrom($user->status);
        return $status?->isActive() === 1;
    }

    /**
     * Get throttle key for rate limiting
     */
    protected function getThrottleKey(string $guard, string $key): string
    {
        return $guard . '-' . $key . '-' . request()->ip();
    }
}

