<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Profile;

use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ChangePassword extends Component
{
    #[Validate]
    public string $currentPassword;

    #[Validate]
    public string $password;

    #[Validate]
    public string $confirmPassword;

    protected AuthService $authService;

    public function boot(): void
    {
        $this->authService = app(AuthService::class);
    }

    public function resetPassword(): void
    {
        $this->validate();

        try {
            $user = request()->user('moderator');
            $this->authService->changePassword(
                $user,
                $this->currentPassword,
                $this->password
            );

            $this->reset();
            flashAlert(__('admin.change-password.updated'), 'success');
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required'],
            'password' => ['required', 'max:100', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'confirmPassword' => ['required', 'same:password'],
        ];
    }

    public function render(): View
    {
        return view('admin::Profile.views.change-password')
            ->layout('panel::layout.app', [
                'title' => __('admin.change-password.title'),
                'breadcrumb' => [[__('admin.change-password.title'), route('admin.profile.change-password')]],
            ]);
    }
}
