<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\User;

use App\Models\Moderator;
use App\Models\Site;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Validation\Rule;

class UserForm extends Component
{
    use WithFileUploads;

    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $mobile_number = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = '';
    public string $store = '';
    public string|bool|null $status = '1';
    public mixed $image = null;
    public array $selectedSites = [];

    public function mount(?int $id = null): void
    {
        if ($id) {
            $user = Moderator::findOrFail($id);
            $this->isEditMode = true;
            $this->editingId = $id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->mobile_number = $user->mobile_number ?? '';
            $roleValue = $user->role?->value ?? '';
            
            // Convert WorkshopSiteManager to SiteManager for existing users
            if ($roleValue === RoleEnum::WorkshopSiteManager->value) {
                $this->role = RoleEnum::SiteSupervisor->value;
            } elseif ($roleValue === RoleEnum::WorkshopStoreManager->value) {
                $this->role = RoleEnum::StoreManager->value;
                $this->store = StoreEnum::WarehouseStore->value;
            } elseif ($roleValue === RoleEnum::StoreManager->value) {
                $this->role = RoleEnum::StoreManager->value;
                $this->store = StoreEnum::HardwareStore->value;
            } else {
                $this->role = $roleValue;
            }
            
            $this->status = $user->status === StatusEnum::Active->value ? '1' : '0';
            $this->password = '';
            $this->password_confirmation = '';
            $this->image = null;
            
            // Load assigned sites if user is a site manager
            if ($roleValue === RoleEnum::SiteSupervisor->value) {
                $this->selectedSites = Site::where('site_manager_id', $id)->pluck('id')->toArray();
            }
        }
    }

    public function updatedRole(): void
    {
        if ($this->role !== RoleEnum::StoreManager->value) {
            $this->store = '';
        }

        // Clear selected sites if role is not site_manager
        if ($this->role !== RoleEnum::SiteSupervisor->value) {
            $this->selectedSites = [];
        }
        
        // Dispatch event to reinitialize Select2
        $this->dispatch('role-updated');
    }

    public function save(): void
    {
        $this->sanitizeInputs();
        $this->mobile_number = $this->normalizeMobile($this->mobile_number);
        $rules = $this->getValidationRules();
        $messages = $this->getValidationMessages();
        
        $this->validate($rules, $messages);

        try {
            $role = $this->getFinalRole();
            $data = $this->prepareUserData($role);
            
            if ($this->isEditMode && $this->editingId) {
                $user = Moderator::findOrFail($this->editingId);
                $user->update($data);
                $this->handleSiteAssignment($role, $user->id);
                $message = 'User updated successfully!';
            } else {
                $user = Moderator::create($data);
                $this->handleSiteAssignment($role, $user->id);
                $message = 'User created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.users.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.users.index'));
    }

    private function getValidationRules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'role' => ['required', 'string', function ($attribute, $value, $fail) {
                if ($value === RoleEnum::SuperAdmin->value) {
                    $fail('Super Admin role cannot be selected.');
                }
            }],
            'status' => 'required|in:0,1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // Email is optional. Mobile number is required (local 7–digit number, Seychelles).
        $emailRules = ['nullable', 'email', 'max:255'];
        $mobileRules = ['required', 'digits:7'];

        if ($this->isEditMode) {
            $emailRules[] = 'unique:moderators,email,' . $this->editingId;
            $mobileRules[] = 'unique:moderators,mobile_number,' . $this->editingId;
        } else {
            $emailRules[] = 'unique:moderators,email';
            $mobileRules[] = 'unique:moderators,mobile_number';
        }

        $rules['email'] = $emailRules;
        $rules['mobile_number'] = $mobileRules;

        // Store selection for store manager
        // if ($this->role === RoleEnum::StoreManager->value) {
        //     $rules['store'] = [
        //         'required',
        //         Rule::in([
        //             StoreEnum::HardwareStore->value,
        //             StoreEnum::WarehouseStore->value,
        //         ]),
        //     ];
        // }

        // Password validation
        if ($this->isEditMode) {
            if ($this->password) {
                $rules['password'] = ['required', 'max:100', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()];
                $rules['password_confirmation'] = ['required', 'same:password'];
            }
        } else {
            $rules['password'] = ['required', 'max:100', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()];
            $rules['password_confirmation'] = ['required', 'same:password'];
        }

        return $rules;
    }


    private function getValidationMessages(): array
    {
        return [
            'name.required' => 'The full name is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'The email has already been taken.',
            'mobile_number.required' => 'The mobile number is required.',
            'mobile_number.digits' => 'The mobile number must be exactly 7 digits (local number).',
            'mobile_number.unique' => 'The mobile number has already been taken.',
            'role.required' => 'The role is required.',
            'store.required' => 'Please select a store for store managers.',
            'store.in' => 'Please select a valid store option.',
            'password.required' => 'The password is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password_confirmation.required' => 'The confirm password is required.',
            'password_confirmation.min' => 'The confirm password must be at least 8 characters.',
            'status.required' => 'The status is required.',
            'status.in' => 'Please select a valid status option.',
            'image.image' => 'The image must be an image file.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif.',
            'image.max' => 'The image must not be greater than 2MB.',
        ];
    }

    private function getFinalRole(): string
    {
        if ($this->role === RoleEnum::StoreManager->value) {
            if ($this->store === StoreEnum::WarehouseStore->value) {
                return RoleEnum::WorkshopStoreManager->value;
            }
            return RoleEnum::StoreManager->value;
        }

        return $this->role;
    }

    private function prepareUserData(string $role): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email !== '' ? $this->email : null,
            'mobile_number' => $this->mobile_number !== '' ? $this->mobile_number : null,
            'role' => $role,
            'status' => (bool) $this->status ? StatusEnum::Active->value : StatusEnum::InActive->value,
            'type' => 'moderator',
        ];

        if ($this->password) {
            $data['password'] = $this->password;
        }

        if ($this->image) {
            $data['image'] = $this->image->store('users', 'public');
        }

        return $data;
    }

    private function handleSiteAssignment(string $role, int $userId): void
    {
        if ($this->isEditMode) {
            // Unassign all sites currently assigned to this user
            Site::where('site_manager_id', $userId)->update(['site_manager_id' => null]);
        }

        // Assign selected sites if user is a site manager
        if ($role === RoleEnum::SiteSupervisor->value && !empty($this->selectedSites)) {
            Site::whereIn('id', $this->selectedSites)->update(['site_manager_id' => $userId]);
        }
    }

    public function updatedMobileNumber(): void
    {
        $this->mobile_number = $this->normalizeMobile($this->mobile_number);
    }


    private function sanitizeInputs(): void
    {
        $this->name = $this->trimValue($this->name);
        $this->email = $this->trimValue($this->email);
        $this->password = $this->trimValue($this->password);
        $this->password_confirmation = $this->trimValue($this->password_confirmation);
        $this->role = $this->trimValue($this->role);
        $this->store = $this->trimValue($this->store);
        $this->mobile_number = $this->trimValue($this->mobile_number);
    }

    private function trimValue(?string $value): string
    {
        return trim((string) $value);
    }

    private function normalizeMobile(?string $value): string
    {
        // Keep only digits
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        // If the number starts with the Seychelles country code (248),
        // normalise it to the local 7–digit part.
        if (strlen($digits) > 7) {
            $digits = substr($digits, -7);
        }

        return $digits;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            return (bool) $value;
        }
        return $normalized;
    }

    public function getRolesProperty(): array
    {
        return [
            RoleEnum::Admin->value => 'Admin',
            RoleEnum::SiteSupervisor->value => 'Site Supervisor',
            RoleEnum::StoreManager->value => 'Store Manager',
            RoleEnum::WorkshopStoreManager->value => 'Workshop store Manager',
            RoleEnum::TransportManager->value => 'Transport Manager',
        ];
    }

    public function getStoresProperty(): array
    {
        return [
            StoreEnum::HardwareStore->value => StoreEnum::HardwareStore->getName(),
            StoreEnum::WarehouseStore->value => StoreEnum::WarehouseStore->getName(),
        ];
    }

    public function getAvailableSitesProperty()
    {
        // Get unassigned sites (where site_manager_id is null)
        $query = Site::where('status', true);
        
        // If editing, also include currently assigned sites to this user
        if ($this->isEditMode && $this->editingId && $this->role === RoleEnum::SiteSupervisor->value) {
            $query->where(function($q) {
                $q->whereNull('site_manager_id')
                  ->orWhere('site_manager_id', $this->editingId);
            });
        } else {
            $query->whereNull('site_manager_id');
        }
        
        return $query->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('admin::User.views.user-form', [
            'roles' => $this->roles,
            'stores' => $this->stores,
            'availableSites' => $this->availableSites,
        ])->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit User' : 'Create User',
            'breadcrumb' => [
                ['User', route('admin.users.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}