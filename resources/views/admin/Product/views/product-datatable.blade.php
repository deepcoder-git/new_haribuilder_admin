<div>
    <!-- Delete Confirmation Modal -->
    <x-delete-confirm-modal 
        :itemNameProperty="'productNameToDelete'"
        :showModalProperty="'showDeleteModal'"
        deleteMethod="delete"
        closeMethod="closeDeleteModal"
        title="Delete Product"
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
                               placeholder="Search products by name..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <x-filter-dropdown
                            :filters="[
                                [
                                    'label' => 'Store',
                                    'wireModel' => 'tempStoreFilter',
                                    'options' => $stores->map(fn($s) => ['id' => $s['value'], 'name' => $s['name']])->prepend(['id' => 'all', 'name' => 'All Stores'])->values()->all(),
                                    'placeholder' => 'All Stores'
                                ],
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
                            Add New Product
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
                            <th class="text-center">IMAGE</th>
                            <th class="cursor-pointer" wire:click="sortBy('product_name')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>PRODUCT NAME</span>
                                    @if($sortField === 'product_name')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>STORE</th>
                            <th>CATEGORY</th>
                            <th>UNIT</th>
                            <th>QUANTITY</th>
                            <th>LOW STOCK</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                        <tr>
                            <td>
                                <span class="text-gray-700">
                                    {{ $product->created_at ? $product->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td class="text-center" style="vertical-align: middle;">
                                {!! $this->renderImage($product) !!}
                            </td>
                            <td>
                                <span class="text-gray-800 fw-semibold">{{ $product->product_name }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $product->store?->getName() ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $product->category->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $product->unit_type ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-800 fw-semibold">
                                    {!! $this->renderQuantity($product) !!}
                                </span>
                            </td>
                            <td>
                                {!! $this->renderLowStock($product) !!}
                            </td>
                            <td>
                                <div class="form-check form-switch form-check-custom form-check-solid d-inline-flex">
                                    @php
                                        // Check if product is connected to orders (only check if active to avoid unnecessary queries)
                                        $isConnected = false;
                                        $shortMessage = '';
                                        if ($product->status) {
                                            $isConnected = $this->isProductConnectedToOrders($product->id);
                                            if ($isConnected) {
                                                $shortMessage = $this->getProductConnectionShortMessage($product->id);
                                            }
                                        }
                                        $shouldDisable = $isConnected && $product->status;
                                    @endphp
                                    <div class="position-relative product-tooltip-wrapper" 
                                         @if($shouldDisable) 
                                             data-bs-toggle="tooltip" 
                                             data-bs-placement="top"
                                             title="Do not change status: {{ $shortMessage }}"
                                         @endif>
                                        <input class="form-check-input product-status-toggle" 
                                               type="checkbox" 
                                               wire:click="toggleStatus({{ $product->id }})"
                                               @if($product->status) checked @endif
                                               @if($shouldDisable) disabled @endif
                                               style="cursor: {{ $shouldDisable ? 'not-allowed' : 'pointer' }}; width: 40px; height: 20px; opacity: {{ $shouldDisable ? '0.6' : '1' }};"
                                               wire:loading.attr="disabled"
                                               data-product-id="{{ $product->id }}"
                                               data-original-status="{{ $product->status ? '1' : '0' }}"
                                               id="product-status-{{ $product->id }}">
                                        <span wire:loading wire:target="toggleStatus({{ $product->id }})" class="spinner-border spinner-border-sm ms-2"></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="#" 
                                       wire:click.prevent="openViewModal({{ $product->id }})"
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Details"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="openEditForm({{ $product->id }})"
                                       class="btn btn-sm btn-icon btn-light-info"
                                       title="Edit Product"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="confirmDelete({{ $product->id }})"
                                       class="btn btn-sm btn-icon btn-light-danger"
                                       title="Delete Product"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>

                        
                            <td colspan="10" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No products found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-datatable-pagination :items="$products" />
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
                        <h5 class="modal-title fw-bold text-center text-white" id="imageZoomModalTitle" style="font-size: 1.125rem; margin: 0; flex: 1;">Product Image</h5>
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
        
        .product-table-image {
            transition: all 0.2s ease;
        }
        
        .product-table-image:hover {
            transform: scale(1.1);
            border-color: #1e3a8a !important;
            box-shadow: 0 4px 8px rgba(30, 58, 138, 0.2);
        }
        
        .product-image-wrapper {
            position: relative;
        }
        
        .product-image-placeholder {
            transition: all 0.2s ease;
        }
        
        .product-image-placeholder:hover {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%) !important;
            border-color: #9ca3af !important;
        }
    </style>

    <script>
        function initProductTooltips() {
            const tooltipTriggerList = document.querySelectorAll('.product-tooltip-wrapper[data-bs-toggle="tooltip"]');
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
            // Initialize tooltips on page load
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                initProductTooltips();
            }
        });

        // Initialize image zoom functionality using event delegation (only once)
        let imageZoomInitialized = false;
        function initImageZoom() {
            if (imageZoomInitialized) return;
            imageZoomInitialized = true;
            
            // Use event delegation for better performance and reliability with dynamic content
            document.addEventListener('click', function(e) {
                const img = e.target.closest('.product-image-zoom');
                if (img) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const imageUrl = img.getAttribute('data-image-url');
                    const productName = img.getAttribute('data-product-name') || 'Product Image';
                    
                    if (imageUrl && typeof bootstrap !== 'undefined') {
                        document.getElementById('zoomedImage').src = imageUrl;
                        document.getElementById('zoomedImage').alt = productName;
                        document.getElementById('imageZoomModalTitle').textContent = productName;
                        
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
                    initProductTooltips();
                }
            }, 100);
        });
        
        // Initialize image zoom on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initImageZoom);
        } else {
            initImageZoom();
        }
        
        // (No extra init needed; event delegation is registered once)
    </script>
</div>

