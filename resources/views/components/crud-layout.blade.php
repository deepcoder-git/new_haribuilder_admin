@props(['title', 'createButton' => true, 'bulkDelete' => true])

<div>
    <!-- Bulk Delete Confirmation Modal -->
    <x-confirm-modal 
        id="bulkDeleteConfirmModal"
        title="Delete Selected Items"
        message="Are you sure you want to delete the selected items?"
        confirmText="Delete All"
        cancelText="Cancel"
        type="danger"
    />
    <!-- Header -->
    <div class="card mb-5 mb-xl-8">
        <div class="card-header border-0 pt-6">
            <div class="card-title" style="flex-direction: column; align-items: flex-start; gap: 1rem;">
                <h3 class="fw-bold m-0" style="color: #1e3a8a; font-size: 1.5rem; line-height: 1.5;">
                    {{ $title }}
                </h3>
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search"
                           class="form-control form-control-solid w-250px ps-13" 
                           placeholder="Search..."/>
                </div>
            </div>
            <div class="card-toolbar">
                <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap">
                    @if($bulkDelete && isset($selectedItems) && !empty($selectedItems))
                    <button type="button" 
                            onclick="event.preventDefault(); showBulkDeleteConfirm({{ count($selectedItems) }}, this);"
                            class="btn btn-sm btn-danger d-flex align-items-center"
                            style="background: #ef4444; border: none; box-shadow: none !important; color: white;">
                        <i class="fa-solid fa-trash-can me-2"></i>
                        Delete Selected ({{ count($selectedItems) }})
                    </button>
                    @endif
                    @if($createButton)
                    <button type="button" 
                            wire:click="openCreateModal"
                            class="btn d-flex align-items-center text-white fw-semibold"
                            style="background: #1e3a8a; border: none; box-shadow: none !important; padding: 0.625rem 1.25rem;">
                        <i class="fa-solid fa-plus me-2"></i>
                        Add
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session()->has('success') || (isset($successMessage) && $successMessage))
    <div x-data="{ show: true }" 
         x-show="show"
         x-cloak
         x-init="setTimeout(() => show = false, 3000)"
         class="alert alert-success alert-dismissible fade show" 
         role="alert">
        <span>{{ $successMessage ?? session('success') }}</span>
        <button type="button" class="btn-close" @click="show = false" aria-label="Close"></button>
    </div>
    @endif

    @if(session()->has('error') || (isset($errorMessage) && $errorMessage))
    <div x-data="{ show: true }" 
         x-show="show"
         x-cloak
         x-init="setTimeout(() => show = false, 5000)"
         class="alert alert-danger alert-dismissible fade show" 
         role="alert">
        <span>{{ $errorMessage ?? session('error') }}</span>
        <button type="button" class="btn-close" @click="show = false" aria-label="Close"></button>
    </div>
    @endif

    <!-- Content -->
    <div class="card">
        <div class="card-body pt-0">
            {{ $slot }}
        </div>
    </div>

    <!-- Modal -->
    <x-crud-modal :title="$title">
        {{ $modalContent ?? '' }}
    </x-crud-modal>

    <script>
    function showBulkDeleteConfirm(count, buttonElement) {
        const modal = document.getElementById('bulkDeleteConfirmModal');
        const messageEl = modal.querySelector('.modal-body p.text-gray-800');
        const confirmBtn = document.getElementById('bulkDeleteConfirmModalConfirmBtn');
        
        // Update message with count
        messageEl.textContent = `Are you sure you want to delete ${count} selected item(s)?`;
        
        // Remove previous event listeners
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        // Find the Livewire component from the button's closest wire:id
        const wireElement = buttonElement ? buttonElement.closest('[wire\\:id]') : document.querySelector('[wire\\:id]');
        const wireId = wireElement ? wireElement.getAttribute('wire:id') : null;
        
        // Add new event listener
        newConfirmBtn.addEventListener('click', function() {
            if (wireId && window.Livewire) {
                const component = window.Livewire.find(wireId);
                if (component) {
                    component.call('bulkDelete');
                }
            }
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
        
        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    </script>
</div>
