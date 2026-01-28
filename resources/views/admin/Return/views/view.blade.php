<div class="card">
    <x-view-header 
        :moduleName="$moduleName ?? 'Return'" 
        :moduleIcon="$moduleIcon ?? 'rotate-left'" 
        :indexRoute="$indexRoute ?? 'admin.returns.index'"
        :editRoute="$editRoute ?? 'admin.returns.edit'"
        :editId="$editId ?? $return->id ?? null"
    />
    <div class="card-body p-8">
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Type</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $return->type ?? 'N/A' }}</span>
        </div>
        @if($return->manager)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Manager</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $return->manager->name ?? 'N/A' }}</span>
        </div>
        @endif
        @if($return->site)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $return->site->name ?? 'N/A' }}</span>
        </div>
        @endif
        @if($return->order)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Order ID</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">ORD{{ $return->order->id ?? 'N/A' }}</span>
        </div>
        @endif
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Date</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">
                {{ $return->date ? $return->date->format('d-m-Y') : 'N/A' }}
            </span>
        </div>
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ ucfirst($return->status ?? 'pending') }}</span>
        </div>
        @if($return->reason)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Reason</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $return->reason }}</span>
        </div>
        @endif
        
        @if($return->items && $return->items->count() > 0)
        <div class="mb-3 mt-6">
            <h5 class="mb-4 fw-bold text-gray-800">Products</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Product</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Category</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600; text-align: right;">Ordered Quantity</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600; text-align: right;">Return Quantity</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Unit Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($return->items as $item)
                        <tr>
                            <td style="padding: 0.75rem;">
                                <div class="d-flex align-items-center gap-2">
                                    @if($item->product && $item->product->first_image_url)
                                        <img src="{{ $item->product->first_image_url }}" alt="{{ $item->product->product_name }}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 0.375rem;">
                                    @endif
                                    <span class="text-gray-800 fw-semibold">{{ $item->product->product_name ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span class="text-gray-700">{{ $item->product->category->name ?? '-' }}</span>
                            </td>
                            <td style="padding: 0.75rem; text-align: right;">
                                <span class="text-gray-800 fw-bold">{{ formatQty($item->ordered_quantity ?? 0) }}</span>
                            </td>
                            <td style="padding: 0.75rem; text-align: right;">
                                <span class="text-gray-800 fw-bold">{{ formatQty($item->return_quantity ?? 0) }}</span>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span class="text-gray-700">{{ $item->unit_type ?? ($item->product->unit_type ?? '-') }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

