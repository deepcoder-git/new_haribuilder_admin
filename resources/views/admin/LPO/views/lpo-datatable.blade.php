<div>
    <div x-data="{ 
        showModal: @entangle('showDeleteModal'),
        itemName: @entangle('orderNameToDelete')
    }" 
         x-show="showModal"
         x-cloak
         style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050;">
        <div class="modal fade" 
             :class="{ 'show d-block': showModal }"
             tabindex="-1" 
             role="dialog"
             aria-labelledby="deleteConfirmModalLabel"
             aria-modal="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0" style="box-shadow: none !important;">
                    <div class="modal-header border-0 pb-0" style="background: #ef4444;">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <h5 class="modal-title text-white fw-bold fs-4 mb-0" id="deleteConfirmModalLabel">Delete LPO</h5>
                            <button type="button" 
                                    class="btn btn-icon btn-sm btn-active-color-white" 
                                    @click="$wire.closeDeleteModal()"
                                    aria-label="Close">
                                <i class="fa-solid fa-xmark fs-2 text-white"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-body pt-6 pb-4">
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px me-4 flex-shrink-0">
                                <div class="symbol-label bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-triangle-exclamation fs-2x text-danger"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <p class="text-gray-800 fs-5 fw-semibold mb-0">
                                    Are you sure you want to delete "<span style="color: #ef4444; font-weight: 600;" x-text="itemName || 'this item'"></span>"?
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 d-flex justify-content-end gap-2">
                        <button type="button" 
                                class="btn btn-light btn-active-light-primary fw-semibold px-6" 
                                @click="$wire.closeDeleteModal()">
                            <i class="fa-solid fa-times me-2"></i>
                            Cancel
                        </button>
                        <button type="button" 
                                class="btn btn-danger fw-semibold px-6" 
                                wire:click="delete"
                                style="background: #ef4444; border: none; box-shadow: none !important;">
                            <i class="fa-solid fa-trash-can me-2"></i>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade" 
             :class="{ 'show': showModal }"
             @click="$wire.closeDeleteModal()"
             x-show="showModal"
             x-cloak
             style="display: none;"></div>
    </div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-nowrap w-100">
                    <div class="d-flex align-items-center position-relative" style="min-width: 280px; max-width: 400px; flex: 0 0 auto;">
                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 10; pointer-events: none;"></i>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               class="form-control form-control-solid" 
                               placeholder="Search LPOs by ID, product, site..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <x-filter-dropdown
                            :filters="[
                                [
                                    'label' => 'Status',
                                    'wireModel' => 'tempStatusFilter',
                                    'options' => collect(\App\Utility\Enums\OrderStatusEnum::cases())->map(fn($s) => ['id' => $s->value, 'name' => $s->getName()])->prepend(['id' => 'all', 'name' => 'All Status'])->values()->all(),
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
                            Add New LPO
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0" style="overflow: visible;">
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
                            <th class="cursor-pointer" wire:click="sortBy('created_at')" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 180px;">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>DATE</span>
                                    @if($sortField === 'created_at')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="cursor-pointer" wire:click="sortBy('id')" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 180px;">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>ORDER ID</span>
                                    @if($sortField === 'id')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            {{-- <th class="text-center" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 150px;">PARENT ORDER</th> --}}
                            <th class="text-center" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 200px;">SITE</th>
                            <th class="text-center" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 150px;">PRIORITY</th>
                            <th class="text-center" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 150px;">STATUS</th>
                            <th class="text-center" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 220px;">LPO SUPPLIERS</th>
                            <th class="text-center" style="padding: 1rem 1.25rem; color: #1e3a8a; font-size: 0.875rem; letter-spacing: 0.5px; font-weight: 700; min-width: 200px;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td style="padding: 1rem 1.25rem; vertical-align: middle;" class="text-center">
                                <span class="text-gray-800 fw-semibold" style="font-size: 0.9375rem; line-height: 1.5;">
                                    {{ $order->created_at ? $order->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            {{-- <td style="padding: 1rem 1.25rem; vertical-align: middle;" class="text-center">
                                <div style="line-height: 1.5;">
                                    {!! $this->renderOrderId($order) !!}
                                </div>
                            </td> --}}
                            <td style="padding: 1rem 1.25rem; vertical-align: middle;" class="text-center">
                                <div style="line-height: 1.5;">
                                    {!! $this->renderParentOrderId($order) !!}
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
                            <td colspan="6" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No LPOs found</div>
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
</div>
