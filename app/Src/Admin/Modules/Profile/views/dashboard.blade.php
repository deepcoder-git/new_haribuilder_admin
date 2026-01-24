<div id="kt_app_content_container" class="app-container container-fluid py-3 py-lg-6">
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body px-4 pb-4 pt-0">
            <div class="row g-5 g-xl-8">
                <div class="col-sm-6 col-xl-3">
                    <div class="h-100 px-4 py-4 rounded-3 bg-white shadow-sm d-flex flex-column justify-content-between border-start border-3 border-warning">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3 d-flex align-items-center justify-content-center rounded-circle bg-light-warning" style="width: 40px; height: 40px;">
                                <i class="fa fa-clock text-warning fs-4"></i>
                            </div>
                            <div>
                                <div class="text-gray-500 small fw-semibold">Pending Orders</div>
                                <div class="fs-1 fw-bolder text-gray-900">{{ number_format($stats['pending_orders'] ?? 0) }}</div>
                            </div>
                        </div>
                        {{-- <a href="{{ route('admin.orders.index') }}" class="text-warning fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.manage') }}
                        </a> --}}
                        <a href="#" class="text-warning fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.manage') }}
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="h-100 px-4 py-4 rounded-3 bg-white shadow-sm d-flex flex-column justify-content-between border-start border-3 border-success">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3 d-flex align-items-center justify-content-center rounded-circle bg-light-success" style="width: 40px; height: 40px;">
                                <i class="fa fa-city text-success fs-4"></i>
                            </div>
                            <div>
                                <div class="text-gray-500 small fw-semibold">Active Sites</div>
                                <div class="fs-1 fw-bolder text-gray-900">{{ number_format($stats['sites'] ?? 0) }}</div>
                            </div>
                        </div>
                        {{-- <a href="{{ route('admin.sites.index') }}" class="text-success fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.manage') }}
                        </a> --}}
                        <a href="#" class="text-success fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.manage') }}
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="h-100 px-4 py-4 rounded-3 bg-white shadow-sm d-flex flex-column justify-content-between border-start border-3 border-danger">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3 d-flex align-items-center justify-content-center rounded-circle bg-light-danger" style="width: 40px; height: 40px;">
                                <i class="fa fa-box-open text-danger fs-4"></i>
                            </div>
                            <div>
                                <div class="text-gray-500 small fw-semibold">Low Stock Items</div>
                                <div class="fs-1 fw-bolder text-gray-900">{{ number_format($stats['low_stock_items'] ?? 0) }}</div>
                            </div>
                        </div>
                        {{-- <a href="{{ route('admin.stock.low-report') }}" class="text-danger fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.view_all') }}
                        </a> --}}
                        <a href="#" class="text-danger fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.view_all') }}
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="h-100 px-4 py-4 rounded-3 bg-white shadow-sm d-flex flex-column justify-content-between border-start border-3 border-primary">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3 d-flex align-items-center justify-content-center rounded-circle bg-light-primary" style="width: 40px; height: 40px;">
                                <i class="fa fa-truck-moving text-primary fs-4"></i>
                            </div>
                            <div>
                                <div class="text-gray-500 small fw-semibold">Active Dispatches</div>
                                <div class="fs-1 fw-bolder text-gray-900">{{ number_format($stats['active_dispatches'] ?? 0) }}</div>
                            </div>
                        </div>
                        {{-- <a href="{{ route('admin.deliveries.index') }}" class="text-primary fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.manage') }}
                        </a> --}}
                        <a href="#" class="text-primary fw-semibold small" target="_blank" rel="noopener">
                            {{ __('admin.manage') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Orders & Low Stock Alert --}}
    <div class="row g-5 g-xl-8 mb-5">
        <div class="col-xl-8">
            <div class="card card-flush shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bolder text-gray-900 mb-0">Recent Orders</h3>
                    {{-- <a href="{{ route('admin.orders.index') }}" class="text-primary fw-semibold small" target="_blank" rel="noopener">
                        {{ __('admin.view_all') }}
                    </a> --}}
                    <a href="#" class="text-primary fw-semibold small" target="_blank" rel="noopener">
                        {{ __('admin.view_all') }}
                    </a>
                </div>
                <div class="card-body py-4">
                    @forelse($recentOrders as $order)
                        @php
                            $status = $order->status?->value ?? ($order->status ?? 'pending');
                            $badgeClass = match ($status) {
                                'delivery' => 'badge-light-success',
                                'pending' => 'badge-light-warning',
                                'rejected', 'cancelled' => 'badge-light-danger',
                                default => 'badge-light-secondary',
                            };
                        @endphp
                        {{-- <a href="{{ route('admin.orders.index', ['search' => 'ORD' . $order->id]) }}" --}}
                        <a href="#"
                           target="_blank"
                           rel="noopener"
                           class="d-flex align-items-center justify-content-between py-3 px-3 mb-3 rounded border bg-light text-decoration-none">
                            <div>
                                <div class="fw-semibold text-gray-900 mb-1">
                                    Order #{{ $order->id }}
                                </div>
                                <div class="text-gray-600 small">
                                    {{ $order->site->name ?? 'No site assigned' }}
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge {{ $badgeClass }} text-capitalize mb-1">
                                    {{ $status ?: 'N/A' }}
                                </span>
                                <div class="text-gray-500 small">
                                    {{ optional($order->created_at)->diffForHumans() }}
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center text-gray-500 py-6">
                            No recent orders found.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bolder text-gray-900 mb-0">Low Stock Alert</h3>
                    {{-- <a href="{{ route('admin.stock.low-report') }}" class="text-primary fw-semibold small" target="_blank" rel="noopener">
                        {{ __('admin.view_all') }}
                    </a> --}}
                    <a href="#" class="text-primary fw-semibold small" target="_blank" rel="noopener">
                        {{ __('admin.view_all') }}
                    </a>
                </div>
                <div class="card-body py-4">
                    @forelse($lowStockProducts as $product)
                        @php
                            $remaining = $product->total_stock_quantity;
                        @endphp
                        <div class="d-flex align-items-center justify-content-between py-3 px-3 mb-3 rounded border bg-light">
                            <div>
                                <div class="fw-semibold text-gray-900 mb-1">
                                    {{ $product->product_name }}
                                </div>
                                <div class="text-gray-600 small">
                                    {{ $product->category->name ?? 'Uncategorized' }}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-danger">
                                    {{ (int) $remaining }}
                                    <span class="text-gray-500 small ms-1">{{ $product->unit_type ?? 'Unit' }} left</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-6">
                            No low stock items right now.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
