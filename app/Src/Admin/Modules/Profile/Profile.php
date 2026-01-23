<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Profile;

use App\Models\Moderator;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class Profile extends Component
{
    use WithFileUploads;

    #[Validate]
    public $thumbImage;

    #[Validate]
    public string $name;

    #[Validate]
    public string $email;

    public function mount(): void
    {
        $user = request()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function save(): void
    {
        $this->validate();
        $user = request()->user();
        if ($this->thumbImage) {
            $user->addMedia($this->thumbImage)->toMediaCollection('profile_image');
        }
        $user->update(['name' => $this->name, 'email' => $this->email]);
        flashAlert(__('admin.profile.details_updated'), 'success');
        $this->reset('thumbImage');
    }

    public function rules(): array
    {
        return [
            'thumbImage' => ['nullable', 'file', 'image', 'max:'.config('media-library.max_file_size')],
            'name' => ['required', 'max:100', 'string'],
            'email' => ['required', 'email:filter', 'max:100', Rule::unique(Moderator::class)->ignore(request()->user())],
        ];
    }

    public function render(): View
    {
        return view('admin::Profile.views.profile')
            ->layout('panel::layout.app', [
                'title' => __('admin.profile.title'),
                'breadcrumb' => [[__('admin.profile.title'), route('admin.profile.index')]],
            ]);
    }
}
