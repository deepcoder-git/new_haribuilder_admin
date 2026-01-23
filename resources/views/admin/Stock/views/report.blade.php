<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div class="d-flex align-items-center position-relative" style="min-width: 260px; max-width: 360px;">
                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               class="form-control form-control-solid"
                               placeholder="Search by product or category..."
                               style="border-radius: 0.5rem; height: 44px; padding-left: 3rem;">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed mb-0" style="font-size: 0.9375rem;">
                    <thead>
                        <tr class="fw-bold text-uppercase" style="border-bottom: 2px solid #1e3a8a; background: #ffffff;">
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">PRODUCT</th>
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">DATE</th>
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">CATEGORY</th>
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">UNIT</th>
                            <th class="text-center" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">QUANTITY</th>
                            <th class="text-center" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">THRESHOLD</th>
                            <th class="text-center" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">LOW STOCK</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $totalStock = $this->getTotalQuantity($product);
                            @endphp
                            <tr class="border-bottom">
                                <td class="text-start" style="padding: 0.375rem 0.5rem;">
                                    <span class="text-gray-800 fw-semibold">{{ $product->product_name }}</span>
                                </td>
                                <td class="text-start" style="padding: 0.375rem 0.5rem; white-space: nowrap;">
                                    <span class="text-gray-700">
                                        {{ $product->created_at ? $product->created_at->format('d/m/Y') : 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-start" style="padding: 0.375rem 0.5rem;">
                                    <span class="text-gray-700">
                                        {{ $product->category->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-start" style="padding: 0.375rem 0.5rem;">
                                    <span class="text-gray-700">
                                        {{ $product->unit_type ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-center" style="padding: 0.375rem 0.5rem;">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span class="text-gray-800 fw-semibold">
                                            {{ formatQty($totalStock) }}
                                        </span>
                                        <a href="{{ route('admin.stock.entries', ['product_id' => $product->id]) }}" 
                                           class="btn btn-sm btn-light-primary" 
                                           title="View Stock Entries">
                                            <i class="fa-solid fa-history"></i>
                                        </a>
                                    </div>
                                </td>
                                <td class="text-center" style="padding: 0.375rem 0.5rem;">
                                    <span class="text-gray-700">
                                        {{ $product->low_stock_threshold !== null ? formatQty($product->low_stock_threshold) : 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-center" style="padding: 0.375rem 0.5rem;">
                                    {!! $this->renderLowStock($product) !!}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-10">
                                    <div class="d-flex flex-column align-items-center">
                                        <div class="symbol symbol-circle symbol-80px mb-4">
                                            <div class="symbol-label bg-light">
                                                <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                            </div>
                                        </div>
                                        <div class="text-gray-600 fw-semibold fs-5 mb-2">
                                            No stock records found
                                        </div>
                                        <div class="text-gray-500 fs-6">
                                            Try changing your search or low-stock filter
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->count())
                <x-datatable-pagination :items="$products" />
            @endif
        </div>
    </div>

    <x-datatable-styles />
</div>


