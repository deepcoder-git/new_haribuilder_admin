<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Auth;

use App\Services\AuthService;
use App\Utility\Enums\UserTypeEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ResetPassword extends Component
{
    #[Url('email')]
    #[Validate]
    public string $email;

    #[Url('token')]
    public string $token;

    #[Validate]
    public string $password;

    #[Validate]
    public string $password_confirmation;

    protected AuthService $authService;

    public function boot(): void
    {
        $this->authService = app(AuthService::class);
    }

    public function submit(): void
    {
        $this->validate();

        try {
            $this->authService->resetPassword(
                $this->email,
                $this->password,
                $this->token,
                UserTypeEnum::Moderator
            );

            flashAlert(__('admin.reset-password.success'), 'success');
            $this->redirectRoute('admin.auth.login');
        } catch (ValidationException $e) {
            flashAlert(__('admin.reset-password.error'), 'danger');
            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'max:100', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    public function render(): View
    {
        return view('admin::Auth.views.reset-password')
            ->layout('panel::layout.auth', [
                'title' => __('admin.reset-password.title'),
            ]);
    }
}
