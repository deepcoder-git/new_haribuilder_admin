<div>
    <!-- Delete Confirmation Modal -->
    <x-delete-confirm-modal 
        :itemNameProperty="'userNameToDelete'"
        :showModalProperty="'showDeleteModal'"
        deleteMethod="delete"
        closeMethod="closeDeleteModal"
        title="Delete User"
    />

    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-nowrap w-100">
                    <div class="d-flex align-items-center position-relative" style="min-width: 280px; max-width: 400px; flex: 0 0 auto;">
                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 10; pointer-events: none;"></i>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               class="form-control form-control-solid" 
                               placeholder="Search users by name, email, phone..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <x-filter-dropdown
                            :filters="[
                                [
                                    'label' => 'Role',
                                    'wireModel' => 'tempRoleFilter',
                                    'options' => collect($roles)->map(fn($label, $value) => ['id' => $value, 'name' => $label])->prepend(['id' => 'all', 'name' => 'All Roles'])->values()->all(),
                                    'placeholder' => 'All Roles'
                                ]
                            ]"
                            :hasActiveFilters="$this->hasActiveFilters()"
                            applyMethod="applyFilters"
                            resetMethod="resetFilters"
                        />
                        <button type="button" 
                                wire:click="openCreateForm"
                                class="btn btn-primary d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #1e3a8a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-plus me-2"></i>
                            Add New User
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed mb-0">
                    <thead>
                        <tr class="fw-bold text-uppercase">
                            <th>DATE</th>
                            <th style="text-align: center;">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>IMAGE</span>
                                </div>
                            </th>
                            <th class="cursor-pointer" wire:click="sortBy('name')">
                                <div class="d-flex align-items-center justify-content-start gap-1">
                                    <span>NAME</span>
                                    @if($sortField === 'name')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="cursor-pointer" wire:click="sortBy('email')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>EMAIL</span>
                                    @if($sortField === 'email')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="cursor-pointer" wire:click="sortBy('mobile_number')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>MOBILE</span>
                                    @if($sortField === 'mobile_number')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="cursor-pointer" wire:click="sortBy('role')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>ROLE</span>
                                    @if($sortField === 'role')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>
                                <span class="text-gray-700">
                                    {{ $user->created_at ? $user->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <div class="d-flex justify-content-center align-items-center">
                                    {!! $this->renderImage($user) !!}
                                </div>
                            </td>
                            <td>
                                <span class="text-gray-800 fw-semibold">{{ $user->name }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $user->email }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $user->mobile_number ?? '-' }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $role = $user->role?->value ?? '';
                                    $badgeClass = match($role) {
                                        'super_admin' => 'badge-light-danger',
                                        'admin' => 'badge-light-primary',
                                        'moderator' => 'badge-light-info',
                                        'site_supervisor' => 'badge-light-warning',
                                        'workshop_site_manager' => 'badge-light-info',
                                        'store_manager' => 'badge-light-success',
                                        'workshop_store_manager' => 'badge-light-info',
                                        'transport_manager' => 'badge-light-dark',
                                        default => 'badge-light-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}" style="font-size: 0.8125rem; padding: 0.25rem 0.5rem;">
                                    {{ ucfirst(str_replace('_', ' ', $role)) }}
                                </span>
                            </td>
                            <td>
                                <div class="form-check form-switch form-check-custom form-check-solid d-inline-flex">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           wire:change="toggleStatus({{ $user->id }})"
                                           @if($user->status === \App\Utility\Enums\StatusEnum::Active->value) checked @endif
                                           style="cursor: pointer; width: 40px; height: 20px;"
                                           wire:loading.attr="disabled">
                                    <span wire:loading wire:target="toggleStatus({{ $user->id }})" class="spinner-border spinner-border-sm ms-2"></span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="#" 
                                       wire:click.prevent="openViewModal({{ $user->id }})"
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Details"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="openEditForm({{ $user->id }})"
                                       class="btn btn-sm btn-icon btn-light-info"
                                       title="Edit User"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="confirmDelete({{ $user->id }})"
                                       class="btn btn-sm btn-icon btn-light-danger"
                                       title="Delete User"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No users found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-datatable-pagination :items="$users" />
        </div>
    </div>

    <x-datatable-styles />
    <x-custom-select-styles />

    <!-- Image Zoom Modal -->
    <div id="imageZoomModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
            <div class="modal-content">
                <div class="modal-header border-0 pb-2" style="padding: 1rem 1.25rem; background: #f8f9fa; border-bottom: 2px solid #1e3a8a; display: flex; align-items: center; justify-content: center; position: relative;">
                    <h5 class="modal-title fw-bold text-center" id="imageZoomModalTitle" style="color: #1e3a8a; font-size: 1.125rem; margin: 0; flex: 1;">User Image</h5>
                    <button type="button" class="btn position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; color: #6b7280; transition: all 0.2s; cursor: pointer; flex-shrink: 0;" onmouseover="this.style.color='#ffffff'; this.style.background='#ef4444'; this.style.borderColor='#ef4444';" onmouseout="this.style.color='#6b7280'; this.style.background='#ffffff'; this.style.borderColor='#e5e7eb';">
                        <i class="fa-solid fa-xmark" style="font-size: 1rem; font-weight: bold;"></i>
                    </button>
                </div>
                <div class="modal-body text-center p-3" style="max-height: 400px; overflow: auto; background: #ffffff;">
                    <img id="zoomedImage" src="" alt="" style="max-width: 100%; max-height: 350px; width: auto; height: auto; object-fit: contain; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                </div>
            </div>
        </div>
    </div>
</div>

@push('footer')
<style>
.user-image-zoom {
    transition: all 0.2s ease;
    display: inline-block;
}

.user-image-zoom:hover {
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.15);
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const img = e.target.closest('.user-image-zoom');
        if (img) {
            e.preventDefault();
            const imageUrl = img.getAttribute('data-image-url') || img.getAttribute('src');
            const userName = img.getAttribute('data-user-name') || 'User Image';
            
            if (imageUrl && typeof bootstrap !== 'undefined') {
                document.getElementById('zoomedImage').src = imageUrl;
                document.getElementById('zoomedImage').alt = userName || 'User Image';
                document.getElementById('imageZoomModalTitle').textContent = userName || 'User Image';
                
                const modal = new bootstrap.Modal(document.getElementById('imageZoomModal'));
                modal.show();
            }
        }
    });
});
</script>
@endpush

