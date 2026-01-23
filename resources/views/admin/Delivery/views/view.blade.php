<div>
    <div class="card">
        <x-view-header 
            :moduleName="$moduleName ?? 'Delivery'" 
            :moduleIcon="$moduleIcon ?? 'truck'" 
            :indexRoute="$indexRoute ?? 'admin.deliveries.index'"
            :editRoute="null"
            :editId="null"
        />
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-5">
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Delivery ID</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">#{{ $delivery->id ?? 'N/A' }}</span>
                    </div>
                    @if($delivery->order)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Order ID</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">#{{ $delivery->order->id ?? 'N/A' }}</span>
                    </div>
                    @endif
                    @if($delivery->order && $delivery->order->site)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $delivery->order->site->name ?? ($delivery->site->name ?? 'N/A') }}</span>
                    </div>
                    @endif
                    @if($delivery->transportManager)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Transport Manager</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $delivery->transportManager->name ?? ($delivery->order->transportManager->name ?? 'N/A') }}</span>
                    </div>
                    @endif
                    @if($delivery->delivery_date)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Delivery Date</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $delivery->delivery_date->format('d-m-Y') }}</span>
                    </div>
                    @endif
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="ms-2">
                            @php
                                $status = $delivery->status ?? 'pending';
                                $badgeClass = match($status) {
                                    'pending' => 'badge-light-warning',
                                    'assigned' => 'badge-light-primary',
                                    'in_transit' => 'badge-light-info',
                                    'delivered' => 'badge-light-success',
                                    'cancelled' => 'badge-light-danger',
                                    default => 'badge-light-secondary',
                                };
                                $statusLabel = match($status) {
                                    'pending' => 'Pending',
                                    'assigned' => 'Assigned',
                                    'in_transit' => 'In Transit',
                                    'delivered' => 'Delivered',
                                    'cancelled' => 'Cancelled',
                                    default => ucfirst($status),
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </span>
                    </div>
                </div>
                @if($delivery->order && $delivery->order->products && $delivery->order->products->count() > 0)
                <div class="col-md-7">
                    <div class="mb-4">
                        <h5 class="mb-3 text-gray-800 fw-bold">Order Products</h5>
                        <div class="table-responsive">
                            <table class="table mb-0" style="margin-bottom: 0; table-layout: fixed; border: none; width: 100%;">
                                <thead style="background: #f9fafb;">
                                    <tr>
                                        <th style="width: 40%; padding: 0.5rem; font-size: 0.875rem; font-weight: 600; text-align: left; vertical-align: middle; border: none;">Product</th>
                                        <th style="width: 20%; padding: 0.5rem; font-size: 0.875rem; font-weight: 600; text-align: center; vertical-align: middle; border: none;">Unit</th>
                                        <th style="width: 40%; padding: 0.5rem; font-size: 0.875rem; font-weight: 600; text-align: right; vertical-align: middle; border: none;">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($delivery->order->products as $product)
                                    <tr style="vertical-align: middle;">
                                        <td style="padding: 0.5rem; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; border: none; text-align: left;">
                                            <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.3; max-width: 150px; word-break: break-word;">
                                                {{ $product->product_name ?? $product->name ?? 'N/A' }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.5rem; vertical-align: middle; text-align: center; border: none;">
                                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; min-height: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 500; color: #374151; border-radius: 0.375rem; width: 100%;">
                                                {{ $product->unit_type ?? '-' }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.5rem; vertical-align: middle; text-align: right; border: none;">
                                            <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.3; white-space: nowrap;">
                                                {{ formatQty($product->pivot->quantity ?? 0) }}
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 600; font-size: 0.875rem; color: #374151; border: none;">
                                            <strong>Total Quantity:</strong>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 700; font-size: 1rem; color: #1e3a8a; border: none;">
                                            <strong>{{ formatQty($delivery->order->products->sum(function($product) { return $product->pivot->quantity ?? 0; })) }}</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>