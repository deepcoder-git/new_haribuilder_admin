<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Site;

use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use App\Models\Site;
use App\Utility\Enums\SiteTypeEnum;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SiteForm extends Component
{
    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public string $name = '';
    public string $location = '';
    public ?int $site_manager_id = null;
    public ?string $type = null;
    public ?string $work_type = null;
    public string|bool|null $status = '1';
    public ?string $start_date = null; // dd/mm/yyyy
    public ?string $end_date = null;   // dd/mm/yyyy

    public function mount(?int $id = null): void
    {
        if ($id) {
            $site = Site::findOrFail($id);
            $this->isEditMode = true;
            $this->editingId = $id;

            $this->name = (string) ($site->name ?? '');
            $this->location = (string) ($site->location ?? '');
            $this->site_manager_id = $site->site_manager_id ? (int) $site->site_manager_id : null;
            $this->type = $site->type?->value ?? (is_string($site->type) ? $site->type : null);
            $this->work_type = $site->work_type ?? null;
            $this->status = $site->status ? '1' : '0';
            $this->start_date = $site->start_date ? $site->start_date->format('d/m/Y') : null;
            $this->end_date = $site->end_date ? $site->end_date->format('d/m/Y') : null;
        }
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $data = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                $site = Site::findOrFail($this->editingId);
                $site->update($data);
                $message = 'Site updated successfully!';
            } else {
                Site::create($data);
                $message = 'Site created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.sites.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.sites.index'));
    }

    public function getSiteManagersProperty()
    {
        return Moderator::where('role', RoleEnum::SiteSupervisor->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    protected function rules(): array
    {
        $typeValues = array_map(static fn (SiteTypeEnum $e) => $e->value, SiteTypeEnum::cases());

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'name')->ignore($this->editingId),
            ],
            'location' => 'required|string|max:255',
            'site_manager_id' => 'required|exists:moderators,id',
            'type' => ['nullable', 'string', Rule::in($typeValues)],
            'work_type' => 'nullable|string|max:255',
            'start_date' => ['required', function ($attribute, $value, $fail) {
                if (!$value) {
                    return;
                }
                try {
                    Carbon::createFromFormat('d/m/Y', (string) $value);
                } catch (\Exception $e) {
                    $fail('The Start Date must be in dd/mm/yyyy format.');
                }
            }],
            'end_date' => ['nullable', function ($attribute, $value, $fail) {
                if (!$value) {
                    return;
                }
                try {
                    Carbon::createFromFormat('d/m/Y', (string) $value);
                } catch (\Exception $e) {
                    $fail('The End Date must be in dd/mm/yyyy format.');
                }
            }],
            'status' => 'required|in:0,1',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'The site name is required.',
            'name.unique' => 'This site name is already taken.',
            'location.required' => 'The location is required.',
            'site_manager_id.required' => 'Please select a site supervisor.',
            'site_manager_id.exists' => 'Please select a valid site supervisor.',
            'status.required' => 'The status is required.',
            'status.in' => 'Please select a valid status.',
        ];
    }

    protected function payload(): array
    {
        return [
            'name' => $this->name,
            'location' => $this->location,
            'site_manager_id' => $this->site_manager_id,
            'type' => $this->type ?: null,
            'work_type' => $this->work_type ?: null,
            'start_date' => $this->start_date ? Carbon::createFromFormat('d/m/Y', $this->start_date)->format('Y-m-d') : null,
            'end_date' => $this->end_date ? Carbon::createFromFormat('d/m/Y', $this->end_date)->format('Y-m-d') : null,
            'status' => (bool) $this->status,
        ];
    }

    public function render(): View
    {
        return view('admin::Site.views.site-form', [
            'siteManagers' => $this->siteManagers,
            'isEditMode' => $this->isEditMode,
        ])->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Site' : 'Add Site',
            'breadcrumb' => [
                ['Site', route('admin.sites.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


