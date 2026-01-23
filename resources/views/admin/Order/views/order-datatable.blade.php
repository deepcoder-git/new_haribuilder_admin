<div>
    <!-- Delete Confirmation Modal -->
    <x-delete-confirm-modal 
        :itemNameProperty="'orderNameToDelete'"
        :showModalProperty="'showDeleteModal'"
        deleteMethod="delete"
        closeMethod="closeDeleteModal"
        title="Delete Order"
    />

    <!-- Rejection Details Modal -->
    @if($showRejectionDetailsModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5);" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;">
                <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                    <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                        <i class="fa-solid fa-ban text-danger me-2"></i>
                        Rejection Details - {{ $rejectionDetailsOrderName }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeRejectionDetailsModal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    @if(!empty($rejectionDetailsProductStatuses))
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                            Rejected Product Types
                        </label>
                        <div class="border rounded p-3" style="background-color: #fef2f2; border-color: #fecaca;">
                            @foreach($rejectionDetailsProductStatuses as $type => $label)
                            <div class="d-flex align-items-center mb-2">
                                <i class="fa-solid fa-times-circle text-danger me-2" style="font-size: 0.875rem;"></i>
                                <span style="font-size: 0.9375rem; color: #991b1b; font-weight: 500;">{{ $label }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                            Rejection Reason
                        </label>
                        <div class="border rounded p-3" style="background-color: #f9fafb; border-color: #e5e7eb; min-height: 100px;">
                            @if($rejectionDetailsNote)
                                <p class="mb-0" style="font-size: 0.9375rem; color: #374151; white-space: pre-wrap; word-wrap: break-word;">{{ $rejectionDetailsNote }}</p>
                            @else
                                <p class="mb-0 text-muted" style="font-size: 0.9375rem; font-style: italic;">No rejection reason provided.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff; border-radius: 0 0 0.75rem 0.75rem;">
                    <button type="button" class="btn btn-secondary" wire:click="closeRejectionDetailsModal" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Reject Order Modal -->
    @if($showRejectModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5);" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;">
                <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                    <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                        <i class="fa-solid fa-ban text-danger me-2"></i>
                        Reject Order
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeRejectModal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-3">
                        <p class="text-gray-700 mb-3" style="font-size: 0.9375rem;">
                            Are you sure you want to reject <strong>{{ $orderNameToReject }}</strong>?
                        </p>
                        <label for="rejectionNote" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                            Rejection Note <span class="text-danger">*</span>
                        </label>
                        <textarea 
                            id="rejectionNote"
                            wire:model="rejectionNote"
                            class="form-control form-control-solid" 
                            rows="4" 
                            placeholder="Please provide a reason for rejecting this order..."
                            style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem; resize: vertical; min-height: 100px;"
                            required></textarea>
                        @error('rejectionNote')
                            <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff; border-radius: 0 0 0.75rem 0.75rem;">
                    <button type="button" class="btn btn-light-secondary" wire:click="closeRejectModal" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" wire:click="rejectOrder" wire:loading.attr="disabled" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                        <span wire:loading.remove wire:target="rejectOrder">
                            <i class="fa-solid fa-ban me-2"></i>Reject Order
                        </span>
                        <span wire:loading wire:target="rejectOrder">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Rejecting...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card shadow-sm border-0" style="overflow: visible;">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex flex-column gap-2 w-100">
                    <div class="d-flex align-items-center justify-content-between gap-2 flex-nowrap w-100">
                        <div class="d-flex align-items-center position-relative" style="min-width: 280px; max-width: 400px; flex: 0 0 auto;">
                            <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 10; pointer-events: none;"></i>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search"
                                   class="form-control form-control-solid" 
                                   placeholder="Search orders by ID, product, site..."
                                   style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                        </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <x-filter-dropdown
                            :filters="[
                                [
                                    'label' => 'Status',
                                    'wireModel' => 'tempStatusFilter',
                                    'options' => collect(\App\Utility\Enums\OrderStatusEnum::cases())
                                        ->map(fn($s) => ['id' => $s->value, 'name' => $s->getName()])
                                        ->prepend(['id' => 'all', 'name' => 'All Status'])
                                        ->values()
                                        ->all(),
                                    'placeholder' => 'All Status'
                                ],
                                [
                                    'label' => 'Site',
                                    'wireModel' => 'tempSiteFilter',
                                    'options' => $sites->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->prepend(['id' => 'all', 'name' => 'All Sites'])->values()->all(),
                                    'placeholder' => 'All Sites'
                                ],
                                [
                                    'label' => 'Priority',
                                    'wireModel' => 'tempPriorityFilter',
                                    'options' => collect(\App\Utility\Enums\PriorityEnum::cases())->map(fn($p) => ['id' => $p->value, 'name' => $p->getName()])->prepend(['id' => 'all', 'name' => 'All Priorities'])->values()->all(),
                                    'placeholder' => 'All Priorities'
                                ]
                            ]"
                            :hasActiveFilters="$this->hasActiveFilters()"
                            applyMethod="applyFilters"
                            resetMethod="resetFilters"
                        />
                        @if($this->isSuperAdmin())
                        <button type="button" 
                                wire:click="openCreateForm"
                                class="btn btn-primary d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #1e3a8a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-plus me-2"></i>
                            Add New Order
                        </button>
                        @endif
                    </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0" style="overflow: visible;">
            <div class="table-responsive order-table-responsive" style="width: 100%; overflow-x: auto; overflow-y: visible; border-right: none !important;">
                <table class="table align-middle table-row-dashed mb-0" style="font-size: 0.9375rem; width: 100%; table-layout: auto; border-right: none !important;">
                    <thead>
                        <tr class="fw-bold text-uppercase" style="border-bottom: 2px solid #1e3a8a; background: #ffffff;">
                            <th class="cursor-pointer" wire:click="sortBy('created_at')" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 180px;">
                                <div class="d-flex align-items-center gap-1">
                                    <span>DATE</span>
                                    @if($sortField === 'created_at')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="cursor-pointer" wire:click="sortBy('id')" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 180px;">
                                <div class="d-flex align-items-center gap-1">
                                    <span>ORDER ID</span>
                                    @if($sortField === 'id')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="text-center" style="padding: 1rem 2rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 180px;">SITE</th>
                            <th class="text-center" style="padding: 1rem 2rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 150px;">PRIORITY</th>
                            <th class="text-center" style="padding: 1rem 2rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 200px;">EXP. DATE OF DELIVERY</th>
                            <th class="text-center" style="padding: 1rem 2rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 200px;">STATUS</th>
                            <th class="text-center" style="padding: 1rem 2rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 260px;">LPO SUPPLIER STATUS</th>
                            <th class="text-center" style="padding: 1rem 1.5rem 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; width: auto; min-width: 250px; max-width: 300px; border-right: none !important; font-weight: 700;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr class="border-bottom" style="transition: background-color 0.2s; border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                <span class="text-gray-700" style="font-size: 0.9375rem; line-height: 1.5;">
                                    {{ $order->created_at ? $order->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td style="padding: 1rem 1.25rem; vertical-align: middle;">
                                <div style="line-height: 1.5;">
                                    {!! $this->renderOrderId($order) !!}
                                </div>
                            </td>
                            <td style="padding: 1rem 2rem; vertical-align: middle;" class="text-center">
                                <span class="text-gray-800 fw-semibold" style="font-size: 0.9375rem; line-height: 1.5;">
                                    {{ $order->site->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td style="padding: 1rem 2rem; vertical-align: middle;" class="text-center">
                                <div style="line-height: 1.5;">
                                    {!! $this->renderPriority($order) !!}
                                </div>
                            </td>
                            <td style="padding: 1rem 2rem; vertical-align: middle;" class="text-center">
                                <div style="line-height: 1.5;">
                                    {!! $this->renderExpectedDeliveryDate($order) !!}
                                </div>
                            </td>


                            <td style="padding: 1rem 2rem; vertical-align: middle;" class="text-center">
                                <div style="line-height: 1.5;">
                                    {!! $this->renderOrderStatus($order) !!}
                                </div>
                            </td>
                            <td style="padding: 1rem 2rem; vertical-align: middle;" class="text-center">
                                <div style="line-height: 1.5; max-width: 260px; margin: 0 auto;">
                                    {!! $this->renderLpoSupplierStatuses($order) !!}
                                </div>
                            </td>
                            <td class="text-center" style="padding: 1rem 1.5rem 1rem 1.25rem; width: auto; min-width: 250px; max-width: 300px; border-right: none !important; vertical-align: middle;">
                                {!! $this->renderActionDropdown($order) !!}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No orders found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-datatable-pagination :items="$orders" />
        </div>
    </div>
    <x-datatable-styles />
    <x-custom-select-styles />
    <style>
        /* Table Container - Allow vertical overflow for dropdowns */
        .order-table-responsive {
            overflow-x: auto;
            overflow-y: visible;
            width: 100%;
            padding: 2rem 1rem 1rem 0;
        }
        
        /* Parent containers - Allow overflow for dropdowns */
        .card,
        .card-body {
            overflow: visible;
        }
        
        /* Table Base Styles */
        .table {
            table-layout: auto;
            width: 100%;
            border-right: none;
            border-collapse: separate;
            border-spacing: 0;
            overflow: visible;
        }
        
        .table thead,
        .table tbody,
        .table tr {
            border-right: none;
        }
        
        /* Table Rows */
        .table tbody tr {
            width: 100%;
            min-height: 60px;
            overflow: visible;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table tbody tr:hover {
            background-color: #f9fafb;
            transition: background-color 0.2s ease;
        }
        
        /* Table Cells */
        .table tbody td {
            white-space: nowrap;
            vertical-align: middle;
            overflow: visible;
            position: relative;
            padding-top: 1.25rem;
            padding-bottom: 1.25rem;
        }
        
        .table tbody td > div,
        .table tbody td > span {
            display: inline-block;
        }
        
        /* Table Headers */
        .table thead th {
            vertical-align: middle;
            background-color: #ffffff;
            min-width: 180px;
        }
        
        /* Column Spacing */
        .table thead th:nth-child(3),
        .table tbody td:nth-child(3),
        .table thead th:nth-child(4),
        .table tbody td:nth-child(4),
        .table thead th:nth-child(5),
        .table tbody td:nth-child(5),
        .table thead th:nth-child(6),
        .table tbody td:nth-child(6) {
            padding-left: 2rem;
            padding-right: 2rem;
        }
        
        /* Last Column (Action) */
        .table thead th:last-child,
        .table tbody td:last-child {
            border-right: none;
            padding-right: 1.5rem;
            max-width: 300px;
        }
        
        .table tbody tr td:last-child {
            white-space: nowrap;
        }
        
        /* Transport Details Dropdown */
        .table tbody td .dropup {
            position: relative;
        }
        
        /* Dropup - opens above */
        .dropup .dropdown-menu {
            margin-bottom: 0.5rem;
        }
        
        /* Dropdown Menu Content */
        .dropdown-menu .table {
            margin-bottom: 0;
        }
        
        .dropdown-menu .table td {
            white-space: normal;
            word-wrap: break-word;
        }
        
        /* Responsive */
        @media (max-width: 767px) {
            .dropup .dropdown-menu {
                min-width: 280px;
                max-width: 100%;
            }
        }
    </style>
    <script>
        (function() {
            'use strict';
            
            // Initialize Bootstrap dropdowns
            function initDropdowns() {
                if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) {
                    return;
                }
                
                const dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                dropdowns.forEach(function(dropdown) {
                    try {
                        if (!bootstrap.Dropdown.getInstance(dropdown)) {
                            new bootstrap.Dropdown(dropdown);
                        }
                    } catch(e) {
                        // Dropdown already initialized or error occurred
                    }
                });
            }
            
            // Handle dropdown positioning for dropups
            function handleDropdownPositioning(e) {
                const button = e.target.closest('[data-bs-toggle="dropdown"]');
                if (!button || !button.closest('.dropup')) {
                    return;
                }
                
                const dropdownMenu = button.nextElementSibling;
                if (!dropdownMenu || !dropdownMenu.classList.contains('dropdown-menu')) {
                    return;
                }
                
                setTimeout(function() {
                    const rect = dropdownMenu.getBoundingClientRect();
                    const viewportWidth = window.innerWidth;
                    
                    // Adjust if clipped on right
                    if (rect.right > viewportWidth) {
                        dropdownMenu.style.left = 'auto';
                        dropdownMenu.style.right = '0';
                    }
                    
                    // Ensure z-index
                    dropdownMenu.style.zIndex = '9999';
                }, 10);
            }
            
            // Initialize on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDropdowns);
            } else {
                initDropdowns();
            }
            
            // Reinitialize after Livewire updates
            if (typeof Livewire !== 'undefined') {
                document.addEventListener('livewire:init', function() {
                    Livewire.hook('morph.updated', function() {
                        setTimeout(initDropdowns, 100);
                    });
                });
            }
            
            // Handle dropdown show event for positioning
            document.addEventListener('show.bs.dropdown', handleDropdownPositioning);
        })();
    </script>
</div>
