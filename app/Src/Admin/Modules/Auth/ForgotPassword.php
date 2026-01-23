<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Auth;

use App\Services\AuthService;
use App\Utility\Enums\UserTypeEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ForgotPassword extends Component
{
    #[Validate]
    public string $email;

    protected AuthService $authService;

    public function boot(): void
    {
        $this->authService = app(AuthService::class);
    }

    public function save(): void
    {
        $this->validate();

        try {
            $this->authService->sendPasswordResetLink(
                $this->email,
                UserTypeEnum::Moderator,
                route('admin.auth.reset-password')
            );

            $this->reset();
            flashAlert(__('admin.reset-password.mail'), 'success');
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }

    public function render(): View
    {
        return view('admin::Auth.views.forgot-password')
            ->layout('panel::layout.auth', [
                'title' => __('admin.forgot-password.title'),
            ]);
    }
}
