<div>
    <!-- Delete Confirmation Modal -->
    <x-delete-confirm-modal 
        :itemNameProperty="'materialNameToDelete'"
        :showModalProperty="'showDeleteModal'"
        deleteMethod="delete"
        closeMethod="closeDeleteModal"
        title="Delete Material"
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
                               placeholder="Search materials by name..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <x-filter-dropdown
                            :filters="[
                                [
                                    'label' => 'Category',
                                    'wireModel' => 'tempCategoryFilter',
                                    'options' => $categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->prepend(['id' => 'all', 'name' => 'All Categories'])->values()->all(),
                                    'placeholder' => 'All Categories'
                                ],
                                [
                                    'label' => 'Unit',
                                    'wireModel' => 'tempUnitFilter',
                                    'options' => $unitTypes->map(fn($u) => ['id' => $u, 'name' => $u])->prepend(['id' => 'all', 'name' => 'All Units'])->values()->all(),
                                    'placeholder' => 'All Units'
                                ],
                                [
                                    'label' => 'Status',
                                    'wireModel' => 'tempStatusFilter',
                                    'options' => [
                                        ['id' => 'all', 'name' => 'All Status'],
                                        ['id' => 'active', 'name' => 'Active'],
                                        ['id' => 'inactive', 'name' => 'Inactive']
                                    ],
                                    'placeholder' => 'All Status'
                                ],
                                [
                                    'label' => 'Material Type',
                                    'wireModel' => 'tempMaterialTypeFilter',
                                    'options' => [
                                        ['id' => 'all', 'name' => 'All Material Types'],
                                        ['id' => '0', 'name' => 'Material Only'],
                                        ['id' => '1', 'name' => 'Material As Product'],
                                        ['id' => '2', 'name' => 'Material + Product'],
                                    ],
                                    'placeholder' => 'All Material Types'
                                ]
                            ]"
                            :hasActiveFilters="$this->hasActiveFilters()"
                            applyMethod="applyFilters"
                            resetMethod="resetFilters"
                        />
                        <button type="button" 
                                wire:click="openImportModal"
                                class="btn btn-success d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #16a34a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-file-import me-2"></i>
                            Import Materials
                        </button>
                        <button type="button" 
                                wire:click="openCreateForm"
                                class="btn btn-primary d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #1e3a8a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-plus me-2"></i>
                            Add Material
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive position-relative">
                {{-- Inline loading indicator --}}
                <div class="table-loading-overlay position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" 
                     wire:loading.delay.shortest
                     style="background: rgba(255, 255, 255, 0.9); z-index: 10;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                        <span class="text-muted">Loading...</span>
                    </div>
                </div>
                <table class="table align-middle table-row-dashed mb-0">
                    <thead>
                        <tr class="fw-bold text-uppercase">
                            <th>DATE</th>
                            <th class="text-center">IMAGE</th>
                            <th class="cursor-pointer" wire:click="sortBy('material_name')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>NAME</span>
                                    @if($sortField === 'material_name')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>CATEGORY</th>
                            <th>UNIT</th>
                            <th>MATERIAL TYPE</th>
                            <th>QUANTITY</th>
                            <th>LOW STOCK</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody wire:loading.class="opacity-50">
                        @forelse($materials as $material)
                        <tr>
                            <td>
                                <span class="text-gray-700">
                                    {{ $material->created_at ? $material->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td class="text-center" style="vertical-align: middle;">
                                {!! $this->renderImage($material) !!}
                            </td>
                            <td>
                                <span class="text-gray-800 fw-semibold">{{ $material->material_name }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $material->category->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $material->unit_type ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $this->getMaterialTypeLabel($material) }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {!! $this->renderAvailableQty($material) !!}
                                </span>
                            </td>
                            <td>
                                {!! $this->renderLowStock($material) !!}
                            </td>
                            <td>
                                <div class="form-check form-switch form-check-custom form-check-solid d-inline-flex">
                                    @php
                                        // Check if material is connected to orders (only check if active to avoid unnecessary queries)
                                        $isConnected = false;
                                        $shortMessage = '';
                                        if ($material->status) {
                                            $isConnected = $this->isMaterialConnectedToOrders($material->id);
                                            if ($isConnected) {
                                                $shortMessage = $this->getMaterialConnectionShortMessage($material->id);
                                            }
                                        }
                                        $shouldDisable = $isConnected && $material->status;
                                    @endphp
                                    <div class="position-relative material-tooltip-wrapper" 
                                         @if($shouldDisable) 
                                             data-bs-toggle="tooltip" 
                                             data-bs-placement="top"
                                             title="Do not change status: {{ $shortMessage }}"
                                         @endif>
                                        <input class="form-check-input material-status-toggle" 
                                               type="checkbox" 
                                               wire:click="toggleStatus({{ $material->id }})"
                                               @if($material->status) checked @endif
                                               @if($shouldDisable) disabled @endif
                                               style="cursor: {{ $shouldDisable ? 'not-allowed' : 'pointer' }}; width: 40px; height: 20px; opacity: {{ $shouldDisable ? '0.6' : '1' }};"
                                               wire:loading.attr="disabled"
                                               data-material-id="{{ $material->id }}"
                                               data-original-status="{{ $material->status ? '1' : '0' }}"
                                               id="material-status-{{ $material->id }}">
                                        <span wire:loading wire:target="toggleStatus({{ $material->id }})" class="spinner-border spinner-border-sm ms-2"></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="#" 
                                       wire:click.prevent="openViewModal({{ $material->id }})"
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Details"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="openEditForm({{ $material->id }})"
                                       class="btn btn-sm btn-icon btn-light-info"
                                       title="Edit Material"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="confirmDelete({{ $material->id }})"
                                       class="btn btn-sm btn-icon btn-light-danger"
                                       title="Delete Material"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No materials found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-datatable-pagination :items="$materials" />
        </div>
    </div>

    <!-- Import Modal -->
    <div x-data="{ 
        showModal: @entangle('showImportModal'),
        importing: @entangle('importing')
    }" 
         x-show="showModal"
         x-cloak
         style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
        <div class="modal fade" 
             :class="{ 'show d-block': showModal }"
             tabindex="-1" 
             role="dialog"
             aria-labelledby="importModalLabel"
             aria-modal="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0" style="box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
                    <div class="modal-header border-0 pb-0" style="background: #16a34a;">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <h5 class="modal-title text-white fw-bold fs-4 mb-0" id="importModalLabel">
                                <i class="fa-solid fa-file-import me-2"></i>
                                Import Materials
                            </h5>
                            <button type="button" 
                                    class="btn btn-icon btn-sm btn-active-color-white" 
                                    @click="$wire.closeImportModal()"
                                    :disabled="importing"
                                    aria-label="Close">
                                <i class="fa-solid fa-xmark fs-2 text-white"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-body pt-6 pb-4">
                        <div class="mb-4">
                            <label for="importFile" class="form-label fw-semibold mb-3">
                                Select File (Excel/CSV)
                                <span class="text-danger">*</span>
                            </label>
                            <input type="file" 
                                   id="importFile"
                                   wire:model="importFile"
                                   accept=".xlsx,.xls,.csv"
                                   class="form-control form-control-solid @error('importFile') is-invalid @enderror"
                                   :disabled="importing">
                            @error('importFile')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fa-solid fa-info-circle me-1"></i>
                                    Supported formats: .xlsx, .xls, .csv (Max: 10MB)
                                </small>
                            </div>
                            @if($importFile)
                                <div class="mt-2">
                                    <small class="text-success">
                                        <i class="fa-solid fa-check-circle me-1"></i>
                                        File selected: {{ $importFile->getClientOriginalName() }}
                                    </small>
                                </div>
                            @endif
                        </div>

                        <div class="alert alert-info d-flex align-items-start mb-4">
                            <i class="fa-solid fa-circle-info me-2 mt-1"></i>
                            <div>
                                <strong>File Format Requirements:</strong>
                                <ul class="mb-0 mt-2" style="padding-left: 1.5rem;">
                                    <li>First row must contain headers: <code>Material Name</code>, <code>Category</code>, <code>Unit Type</code>, <code>Available Quantity</code>, <code>Product (0 = No, 1 = Yes)</code></li>
                                    <li><strong>Material Name:</strong> Required</li>
                                    <li><strong>Category:</strong> Required (will be created if doesn't exist)</li>
                                    <li><strong>Unit Type:</strong> Required (must exist in Units management, e.g., Pieces, KG, Liters, Bag)</li>
                                    <li><strong>Available Quantity:</strong> Optional (numeric)</li>
                                    <li><strong>Product (0 = No, 1 = Yes):</strong> Optional (default: 0 for materials)</li>
                                </ul>
                            </div>
                        </div>

                        @if(count($importErrors) > 0)
                            <div class="alert alert-danger mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                    <strong>Import Errors ({{ $importErrorCount }}):</strong>
                                </div>
                                <div style="max-height: 200px; overflow-y: auto;">
                                    <ul class="mb-0" style="padding-left: 1.5rem;">
                                        @foreach($importErrors as $error)
                                            <li class="mb-1">{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if($importSuccessCount > 0)
                            <div class="alert alert-success mb-4">
                                <i class="fa-solid fa-check-circle me-2"></i>
                                <strong>Successfully imported {{ $importSuccessCount }} material(s)!</strong>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" 
                                class="btn btn-light-secondary"
                                @click="$wire.closeImportModal()"
                                :disabled="importing">
                            Cancel
                        </button>
                        <button type="button" 
                                class="btn btn-success"
                                wire:click="importMaterials"
                                :disabled="!$wire.importFile || importing"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="importMaterials">
                                <i class="fa-solid fa-upload me-2"></i>
                                Import Materials
                            </span>
                            <span wire:loading wire:target="importMaterials">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Importing...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-datatable-styles />
    <x-custom-select-styles />
    
    <!-- Image Zoom Modal -->
    <div id="imageZoomModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0" style="box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-3" style="background: #1e3a8a;">
                    <div class="d-flex align-items-center justify-content-between w-100">
                        <h5 class="modal-title fw-bold text-center text-white" id="imageZoomModalTitle" style="font-size: 1.125rem; margin: 0; flex: 1;">Material Image</h5>
                        <button type="button" class="btn btn-icon btn-sm btn-active-color-white" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fa-solid fa-xmark fs-2 text-white"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-body text-center py-4">
                    <img id="zoomedImage" src="" alt="" style="max-width: 100%; max-height: 70vh; width: auto; height: auto; object-fit: contain; border-radius: 0.5rem; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                </div>
            </div>
        </div>
    </div>
    
    <style>
        [x-cloak] {
            display: none !important;
        }
        
        .material-table-image {
            transition: all 0.2s ease;
        }
        
        .material-table-image:hover {
            transform: scale(1.1);
            border-color: #1e3a8a !important;
            box-shadow: 0 4px 8px rgba(30, 58, 138, 0.2);
        }
        
        .material-image-wrapper {
            position: relative;
        }
        
        .material-image-placeholder {
            transition: all 0.2s ease;
        }
        
        .material-image-placeholder:hover {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%) !important;
            border-color: #9ca3af !important;
        }
    </style>

    <script>
        function initMaterialTooltips() {
            const tooltipTriggerList = document.querySelectorAll('.material-tooltip-wrapper[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                const existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: 'top',
                    trigger: 'hover'
                });
            });
        }

        document.addEventListener('livewire:initialized', () => {
            let lastToggledCheckbox = null;
            let originalStatusBeforeToggle = null;
            
            // Initialize tooltips on page load
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                initMaterialTooltips();
            }
            
            // Store original checkbox state BEFORE it toggles
            document.querySelectorAll('.material-status-toggle').forEach(checkbox => {
                checkbox.addEventListener('mousedown', function(e) {
                    // Store state before the click toggles it
                    lastToggledCheckbox = this;
                    originalStatusBeforeToggle = this.checked;
                });
            });

            // Listen for error toasts and revert the last toggled checkbox
            Livewire.on('show-toast', (data) => {
                if (data.type === 'error' && data.message && data.message.includes('Cannot inactive material')) {
                    if (lastToggledCheckbox && originalStatusBeforeToggle !== null) {
                        // Revert to original state
                        lastToggledCheckbox.checked = originalStatusBeforeToggle;
                        lastToggledCheckbox = null;
                        originalStatusBeforeToggle = null;
                    }
                }
            });
        });

        // Initialize image zoom functionality using event delegation (only once)
        let imageZoomInitialized = false;
        function initImageZoom() {
            if (imageZoomInitialized) return;
            imageZoomInitialized = true;
            
            // Use event delegation for better performance and reliability with dynamic content
            document.addEventListener('click', function(e) {
                const img = e.target.closest('.material-image-zoom');
                if (img) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const imageUrl = img.getAttribute('data-image-url');
                    const materialName = img.getAttribute('data-material-name') || 'Material Image';
                    
                    if (imageUrl && typeof bootstrap !== 'undefined') {
                        document.getElementById('zoomedImage').src = imageUrl;
                        document.getElementById('zoomedImage').alt = materialName;
                        document.getElementById('imageZoomModalTitle').textContent = materialName;
                        
                        const modalElement = document.getElementById('imageZoomModal');
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            modal.show();
                        }
                    }
                }
            });
        }
        
        // Reinitialize tooltips after Livewire updates
        document.addEventListener('livewire:update', () => {
            setTimeout(() => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    initMaterialTooltips();
                }
            }, 100);
        });
        
        // Initialize image zoom on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initImageZoom);
        } else {
            initImageZoom();
        }
        
        // Also initialize after Livewire is ready
        document.addEventListener('livewire:initialized', () => {
            initImageZoom();
        });
    </script>
</div>

