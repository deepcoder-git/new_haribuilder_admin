<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Auth;

use App\Models\Moderator;
use App\Utility\Enums\StatusEnum;
use App\Utility\Enums\SchoolBoardEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class Register extends Component
{
    use WithFileUploads;

    #[Validate]
    public $profileImage;

    #[Validate]
    public string $name;

    #[Validate]
    public string $email;

    #[Validate]
    public string $password;

    #[Validate]
    public string $password_confirmation;

    #[Validate]
    public string $board;

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function register(): void
    {
        $this->validate();
        
        $moderator = Moderator::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'board' => $this->board,
            'status' => StatusEnum::Active->value,
            'type' => 'moderator',
            'role' => RoleEnum::Moderator->value,
        ]);

        if ($this->profileImage) {
            $moderator->addMedia($this->profileImage)->toMediaCollection('profile_image');
        }

        // Auto login after registration
        Auth::guard('moderator')->login($moderator);
        
        flashAlert(__('admin.register.success'), 'success');
        $this->redirect(route('admin.dashboard'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'max:100', 'string'],
            'email' => ['required', 'email:filter', 'max:100', Rule::unique(Moderator::class)],
            'password' => ['required', 'max:100', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required'],
            'profileImage' => ['nullable', 'file', 'image', 'max:'.config('media-library.max_file_size')],
            'board' => ['required', 'in:'.SchoolBoardEnum::inRule()],
        ];
    }

    public function getBoards(): array
    {
        return SchoolBoardEnum::cases();
    }

    public function render(): View
    {
        return view('admin::Auth.views.register')
            ->layout('panel::layout.auth', ['title' => __('admin.register.title')]);
    }
}

