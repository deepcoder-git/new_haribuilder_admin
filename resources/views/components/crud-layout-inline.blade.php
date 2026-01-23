@props(['title', 'createButton' => true, 'bulkDelete' => true, 'showModal' => false, 'showDateFilter' => false])

<div style="margin: 0; padding: 0;">
    <!-- Bulk Delete Confirmation Modal -->
    <x-confirm-modal 
        id="bulkDeleteConfirmModal"
        title="Delete Selected Items"
        message="Are you sure you want to delete the selected items?"
        confirmText="Delete All"
        cancelText="Cancel"
        type="danger"
    />
    
    <!-- Single Card Container -->
    <div class="card" style="margin: 0; padding: 0; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
        <!-- Header Section - Show only on listing page -->
        <div x-data="{ show: @entangle('showModal') }" 
             x-show="!show"
             x-cloak>
            <div class="card-header border-0 px-3" style="padding: 1rem !important; background: #f8f9fa; margin: 0; border-bottom: 1px solid #e5e7eb; border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem;">
                <div class="card-title w-100" style="margin: 0;">
                    <div class="d-flex align-items-center gap-3 flex-wrap w-100 justify-content-between">
                        <!-- Left Side: Search Bar -->
                        <div class="d-flex align-items-center position-relative flex-grow-1" style="min-width: 300px; max-width: 500px;">
                            <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 1rem; z-index: 10; pointer-events: none;"></i>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search"
                                   class="form-control form-control-solid" 
                                   placeholder="Search....."
                                   style="border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem 1rem 0.75rem 3rem; font-size: 1rem; height: 42px; width: 100%; transition: border-color 0.2s ease;"
                                   onfocus="this.style.borderColor='#1e3a8a';"
                                   onblur="this.style.borderColor='#e5e7eb';"/>
                        </div>
                        <!-- Right Side: Buttons -->
                        <div class="d-flex align-items-center gap-2 flex-wrap" style="flex-shrink: 0;">
                            {{ $headerActions ?? '' }}
                            <!-- Date Filter Button -->
                            @if($showDateFilter)
                            <div class="position-relative" x-data="{ show: @entangle('showDateFilterDropdown') }">
                                <button type="button" 
                                        wire:click="toggleDateFilter"
                                        class="btn d-flex align-items-center"
                                        style="background: #1e3a8a; border: none; box-shadow: none !important; color: white; padding: 0.625rem 1rem; font-size: 0.9375rem; border-radius: 0.5rem; height: 42px; font-weight: 500;">
                                    <i class="fa-solid fa-calendar-days me-2" style="font-size: 1rem;"></i>
                                    Date Filter
                                    @if(isset($dateFilterFrom) && $dateFilterFrom || isset($dateFilterTo) && $dateFilterTo)
                                        <span class="badge badge-light ms-2" style="background: rgba(255,255,255,0.2); color: white;">{{ ($dateFilterFrom ?? null ? 1 : 0) + ($dateFilterTo ?? null ? 1 : 0) }}</span>
                                    @endif
                                </button>
                                <div x-show="show"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 transform scale-100"
                                     x-transition:leave-end="opacity-0 transform scale-95"
                                     @click.away="show = false"
                                     class="position-absolute end-0 mt-2"
                                     style="z-index: 1050; min-width: 320px; display: none;">
                                    <div class="card shadow-lg border-0" style="border-radius: 0.625rem; background: #ffffff;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0 fw-semibold" style="color: #1e3a8a;">Date Filter</h6>
                                                <button type="button" 
                                                        wire:click="toggleDateFilter"
                                                        class="btn btn-sm btn-link p-0 text-muted"
                                                        style="text-decoration: none; border: none; background: transparent;">
                                                    <i class="fa-solid fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold mb-2" style="color: #374151;">Date Range</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="position-relative flex-fill">
                                                        <input type="date" 
                                                               id="dateFilterFrom"
                                                               wire:model.live="dateFilterFrom"
                                                               class="form-control form-control-solid"
                                                               style="font-size: 0.875rem; padding: 0.5rem 0.75rem; cursor: pointer;"/>
                                                    </div>
                                                    <i class="fa-solid fa-arrow-right" style="color: #6b7280; font-size: 0.75rem;"></i>
                                                    <div class="position-relative flex-fill">
                                                        <input type="date" 
                                                               id="dateFilterTo"
                                                               wire:model.live="dateFilterTo"
                                                               class="form-control form-control-solid"
                                                               style="font-size: 0.875rem; padding: 0.5rem 0.75rem; cursor: pointer;"/>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" 
                                                        wire:click="resetDateFilter"
                                                        class="btn btn-sm btn-light flex-fill"
                                                        style="font-size: 0.875rem; padding: 0.5rem; border: 1px solid #e5e7eb;">
                                                    <i class="fa-solid fa-rotate me-1"></i>Reset
                                                </button>
                                                <button type="button" 
                                                        wire:click="toggleDateFilter"
                                                        class="btn btn-sm flex-fill"
                                                        style="background: #1e3a8a; color: white; border: none; font-size: 0.875rem; padding: 0.5rem;">
                                                    Apply
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!-- Add New Button -->
                            @if($createButton)
                            <button type="button" 
                                    wire:click="openCreateModal"
                                    class="btn d-flex align-items-center text-white fw-semibold"
                                    style="background: #1e3a8a; border: none; box-shadow: none !important; padding: 0.625rem 1rem; font-size: 0.9375rem; border-radius: 0.5rem; height: 42px; font-weight: 500;">
                                <i class="fa-solid fa-plus me-2" style="font-size: 1rem;"></i>
                                @if(str_contains(strtolower($title), 'site'))
                                    Add New Site
                                @elseif(str_contains(strtolower($title), 'order'))
                                    Add New Order
                                @elseif(str_contains(strtolower($title), 'stock'))
                                    Add New Stock
                                @elseif(str_contains(strtolower($title), 'supplier'))
                                    Add New Supplier
                                @elseif(str_contains(strtolower($title), 'customer'))
                                    Add New Customer
                                @elseif(str_contains(strtolower($title), 'transport'))
                                    Add New Transport Manager
                                @else
                                    Add New {{ str_replace(' Management', '', $title) }}
                                @endif
                            </button>
                            @endif
                            @if($bulkDelete && isset($selectedItems) && !empty($selectedItems))
                            <button type="button" 
                                    onclick="event.preventDefault(); showBulkDeleteConfirm({{ count($selectedItems) }}, this);"
                                    class="btn btn-danger d-flex align-items-center"
                                    style="background: #ef4444; border: none; box-shadow: none !important; color: white; padding: 0.625rem 1rem; font-size: 0.9375rem; border-radius: 0.5rem; height: 42px; font-weight: 500;">
                                <i class="fa-solid fa-trash-can me-1" style="font-size: 1rem;"></i>
                                Delete Selected ({{ count($selectedItems) }})
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages - Show only on listing page -->
        <div x-data="{ show: @entangle('showModal') }" 
             x-show="!show"
             x-cloak
             style="padding: 0.5rem 0.75rem 0 0.75rem; margin: 0;">
            @if(session()->has('success') || (isset($successMessage) && $successMessage))
            <div x-data="{ show: true }" 
                 x-show="show"
                 x-cloak
                 x-init="setTimeout(() => show = false, 3000)"
                 class="alert alert-success alert-dismissible fade show" 
                 role="alert"
                 style="padding: 0.375rem 0.625rem; font-size: 0.875rem; margin: 0 0 0.5rem 0;">
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
                 role="alert"
                 style="padding: 0.375rem 0.625rem; font-size: 0.875rem; margin: 0 0 0.5rem 0;">
                <span>{{ $errorMessage ?? session('error') }}</span>
                <button type="button" class="btn-close" @click="show = false" aria-label="Close"></button>
            </div>
            @endif
        </div>

        <!-- Inline Form Section - Show when modal is open -->
        <div x-data="{ show: @entangle('showModal') }" 
             x-show="show"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-4"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-4"
             style="display: none;">
            <div class="card-header border-0 px-3 d-flex justify-content-between align-items-center" style="background: #ffffff; border-bottom: 1px solid #e5e7eb; padding: 0.75rem; margin: 0;">
                <div class="card-title" style="margin: 0;">
                    <h2 class="fw-bold m-0 d-flex align-items-center" style="color: #1e3a8a; font-size: 1.25rem; line-height: 1.5;">
                        <i class="fa-solid fa-building me-2" style="font-size: 1.25rem;" x-show="!$wire.isViewMode && !$wire.isEditMode"></i>
                        <i class="fa-solid fa-edit me-2" style="font-size: 1.25rem;" x-show="$wire.isEditMode"></i>
                        <i class="fa-solid fa-eye me-2" style="font-size: 1.25rem;" x-show="$wire.isViewMode"></i>
                        <span x-text="$wire.isViewMode ? 'View {{ $title }}' : ($wire.isEditMode ? 'Edit {{ $title }}' : 'Add {{ $title }}')">Add {{ $title }}</span>
                    </h2>
                </div>
                @if(isset($formHeaderActions))
                    <div class="d-flex align-items-center gap-3">
                        {{ $formHeaderActions }}
                    </div>
                @endif
                @if(isset($formHeaderSwitches))
                    <div class="d-flex align-items-center">
                        {{ $formHeaderSwitches }}
                    </div>
                @endif
            </div>
            <div class="card-body px-3" style="padding: 1rem !important; margin: 0;">
                @if(isset($formContent))
                    {{ $formContent }}
                @endif
                
                @if(isset($formContent))
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3">
                        <button type="button" 
                                wire:click="closeModal"
                                class="btn btn-light d-flex align-items-center"
                                style="padding: 0.75rem 2rem; font-size: 1rem; font-weight: 500; height: 48px;">
                            <i class="fa-solid fa-times me-2" style="font-size: 1rem;"></i>
                            <span x-text="$wire.isViewMode ? 'Close' : 'Cancel'">Cancel</span>
                        </button>
                        <div x-show="!$wire.isViewMode" x-cloak>
                            <button type="button"
                                    wire:click="save"
                                    class="btn text-white fw-semibold d-flex align-items-center"
                                    style="background: #1e3a8a; border: none; box-shadow: none !important; padding: 0.75rem 2rem; font-size: 1rem; font-weight: 600; height: 48px;">
                                <i class="fa-solid fa-floppy-disk me-2" style="font-size: 1rem;" x-show="!$wire.isEditMode"></i>
                                <i class="fa-solid fa-check-double me-2" style="font-size: 1rem;" x-show="$wire.isEditMode"></i>
                                <span x-text="$wire.isEditMode ? 'Update' : 'Save'">Save</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Content (Table) Section - Show only on listing page -->
        <div x-data="{ show: @entangle('showModal') }" 
             x-show="!show"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="card-body" style="padding: 0 !important; margin: 0; background: transparent;">
                {{ $slot }}
            </div>
        </div>
    </div>

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