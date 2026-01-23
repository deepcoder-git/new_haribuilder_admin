<div class="card">
    <x-view-header 
        :moduleName="$moduleName ?? 'Wastage'" 
        :moduleIcon="$moduleIcon ?? 'trash'" 
        :indexRoute="$indexRoute ?? 'admin.wastages.index'"
        :editRoute="$editRoute ?? 'admin.wastages.edit'"
        :editId="$editId ?? $wastage->id ?? null"
    />
    <div class="card-body p-8">
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Type</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="ms-2">
                @if($wastage->type)
                    @if($wastage->type instanceof \App\Utility\Enums\WastageTypeEnum)
                        @php
                            $badgeClass = $wastage->type === \App\Utility\Enums\WastageTypeEnum::SiteWastage ? 'badge-light-info' : 'badge-light-warning';
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $wastage->type->getName() }}</span>
                    @else
                        <span class="text-gray-800 fw-bold fs-6">{{ ucfirst(str_replace('_', ' ', (string) $wastage->type)) }}</span>
                    @endif
                @else
                    <span class="text-gray-800 fw-bold fs-6">N/A</span>
                @endif
            </span>
        </div>
        @if($wastage->manager)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Manager</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $wastage->manager->name ?? 'N/A' }}</span>
        </div>
        @endif
        @if($wastage->site)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $wastage->site->name ?? 'N/A' }}</span>
        </div>
        @endif
        @if($wastage->order)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Order ID</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">ORD{{ $wastage->order->id ?? 'N/A' }}</span>
        </div>
        @endif
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Date</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">
                {{ $wastage->date ? $wastage->date->format('d-m-Y') : 'N/A' }}
            </span>
        </div>
        @if($wastage->reason)
        <div class="mb-3" style="line-height: 2.5;">
            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Reason</span>
            <span class="text-gray-600" style="margin: 0 8px;">:</span>
            <span class="text-gray-800 fw-bold fs-6">{{ $wastage->reason }}</span>
        </div>
        @endif
        
        @if($wastage->products && $wastage->products->count() > 0)
        <div class="mb-3 mt-6">
            <h5 class="mb-4 fw-bold text-gray-800">Products</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Product</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Category</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600; text-align: right;">Wastage Quantity</th>
                            <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Unit Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($wastage->products as $product)
                        <tr>
                            <td style="padding: 0.75rem;">
                                <div class="d-flex align-items-center gap-2">
                                    @if($product->first_image_url)
                                        <img src="{{ $product->first_image_url }}" alt="{{ $product->product_name }}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 0.375rem;">
                                    @endif
                                    <span class="text-gray-800 fw-semibold">{{ $product->product_name }}</span>
                                </div>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span class="text-gray-700">{{ $product->category->name ?? '-' }}</span>
                            </td>
                            <td style="padding: 0.75rem; text-align: right;">
                                <span class="text-gray-800 fw-bold">{{ formatQty($product->pivot->wastage_qty ?? 0) }}</span>
                            </td>
                            <td style="padding: 0.75rem;">
                                <span class="text-gray-700">{{ $product->pivot->unit_type ?? ($product->unit_type ?? '-') }}</span>
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

