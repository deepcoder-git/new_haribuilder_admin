<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Auth;

use App\Services\AuthService;
use App\Utility\Enums\UserTypeEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Login extends Component
{
    #[Validate]
    public string $email;

    #[Validate]
    public string $password;

    public bool $remember = false;

    protected ?AuthService $authService = null;

    public function boot(): void
    {
        $this->authService = app(AuthService::class);
    }

    public function auth(): void
    {
        $this->validate();

        try {
            $result = $this->authService->login(
                $this->email,
                $this->password,
                UserTypeEnum::Moderator,
                $this->remember
            );

            flashAlert(__('admin.login.to_dashboard'), 'success');
            $this->redirect(route('admin.dashboard'));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                function ($attribute, $value, $fail) {
                    $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                    $isPhone = preg_match('/^[0-9+\-\s()]+$/', $value) && strlen(preg_replace('/[^0-9]/', '', $value)) >= 10;
                    
                    if (!$isEmail && !$isPhone) {
                        $fail('The ' . $attribute . ' must be a valid email address or phone number.');
                    }
                },
            ],
            'password' => ['required'],
        ];
    }

    public function render(): View
    {
        return view('admin::Auth.views.login')
            ->layout('panel::layout.auth', ['title' => __('admin.login.title')]);
    }
}
