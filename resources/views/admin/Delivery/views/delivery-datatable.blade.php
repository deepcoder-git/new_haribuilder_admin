<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-nowrap w-100">
                    <div class="d-flex align-items-center position-relative" style="min-width: 280px; max-width: 400px; flex: 0 0 auto;">
                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 10; pointer-events: none;"></i>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               class="form-control form-control-solid" 
                               placeholder="Search approved orders by ID, site, product..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <x-filter-dropdown
                            :filters="[
                                [
                                    'label' => 'Site',
                                    'wireModel' => 'tempSiteFilter',
                                    'options' => $sites->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->prepend(['id' => 'all', 'name' => 'All Sites'])->values()->all(),
                                    'placeholder' => 'All Sites'
                                ],
                                [
                                    'label' => 'Delivery Status',
                                    'wireModel' => 'tempDeliveryStatusFilter',
                                    'options' => [
                                        ['id' => 'all', 'name' => 'All Status'],
                                        ['id' => 'approved', 'name' => 'Approved'],
                                        ['id' => 'in_transit', 'name' => 'In Transit'],
                                        ['id' => 'delivered', 'name' => 'Delivered']
                                    ],
                                    'placeholder' => 'All Status'
                                ],
                                [
                                    'label' => 'Transport Manager',
                                    'wireModel' => 'tempTransportManagerFilter',
                                    'options' => collect([['id' => 'unassigned', 'name' => 'Unassigned']])->merge($transportManagers->map(fn($tm) => ['id' => $tm->id, 'name' => $tm->name]))->prepend(['id' => 'all', 'name' => 'All Managers'])->values()->all(),
                                    'placeholder' => 'All Managers'
                                ]
                            ]"
                            :hasActiveFilters="$this->hasActiveFilters()"
                            applyMethod="applyFilters"
                            resetMethod="resetFilters"
                        />
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
                            <th>ORDER ID</th>
                            <th>SITE NAME</th>
                            <th>TRANSPORT MANAGER</th>
                            <th>DELIVERY STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td>
                                <span class="text-gray-700">
                                    {{ $order->created_at ? $order->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.orders.view', $order->id) }}" class="text-gray-800 fw-semibold" style="text-decoration: none;">ORD{{ $order->id }}</a>
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $order->site->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $order->transportManager->name ?? 'Not Assigned' }}</span>
                            </td>
                            <td>
                                @php
                                    $statusClass = match($order->delivery_status) {
                                        'delivered' => 'success',
                                        'in_transit' => 'primary',
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected' => 'danger',
                                        'outfordelivery' => 'primary',
                                        default => 'secondary'
                                    };
                                    $statusLabel = match($order->delivery_status) {
                                        'in_transit' => 'In Transit',
                                        'approved' => 'Approved',
                                        'outfordelivery' => 'Out of Delivery',
                                        default => ucfirst($order->delivery_status ?? 'Pending')
                                    };
                                @endphp
                                <span class="badge badge-light-{{ $statusClass }}" style="font-size: 0.8125rem; padding: 0.25rem 0.5rem;">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('admin.orders.view', $order->id) }}" 
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Order"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="{{ route('admin.deliveries.create', ['order_id' => $order->id]) }}" 
                                       class="btn btn-sm btn-icon btn-light-success"
                                       title="Create Delivery / Assign Transport Manager"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-truck" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
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
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No approved orders found</div>
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