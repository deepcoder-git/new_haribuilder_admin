<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    @if($product)
                        <div class="d-flex align-items-center gap-3">
                            <h4 class="mb-0 fw-bold text-gray-800">{{ $product->product_name }}</h4>
                            <span class="badge badge-light-primary">Current Stock: {{ formatQty($product->total_stock_quantity) }}</span>
                        </div>
                    @else
                        <h4 class="mb-0 fw-bold text-gray-800">Stock Entries</h4>
                    @endif
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center position-relative" style="min-width: 260px; max-width: 360px;">
                            <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280;"></i>
                            <input type="text"
                                   wire:model.live.debounce.300ms="search"
                                   class="form-control form-control-solid"
                                   placeholder="Search by product name..."
                                   style="border-radius: 0.5rem; height: 44px; padding-left: 3rem;">
                        </div>
                        <select wire:model.live="adjustmentType" class="form-select form-select-solid" style="border-radius: 0.5rem; height: 44px; min-width: 150px;">
                            <option value="">All Types</option>
                            <option value="in">Stock In (+)</option>
                            <option value="out">Stock Out (-)</option>
                            <option value="adjustment">Adjustment (=)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed mb-0" style="font-size: 0.9375rem;">
                    <thead>
                        <tr class="fw-bold text-uppercase" style="border-bottom: 2px solid #1e3a8a; background: #ffffff;">
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">DATE & TIME</th>
                            @if(!$product)
                                <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">PRODUCT</th>
                            @endif
                            <th class="text-center" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">TYPE</th>
                            <th class="text-center" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">QUANTITY</th>
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">NOTES</th>
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">REFERENCE</th>
                            <th class="text-start" style="padding: 0.5rem; color: #1e3a8a; font-size: 0.8125rem;">SITE</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stocks as $stock)
                            @php
                                $adjustmentIcon = $this->getAdjustmentIcon($stock->adjustment_type);
                                $adjustmentLabel = $this->getAdjustmentLabel($stock->adjustment_type);
                                $adjustmentBadge = $this->getAdjustmentBadgeClass($stock->adjustment_type);
                                $referenceInfo = $this->getReferenceInfo($stock);
                                
                                // Calculate change amount (difference from previous stock)
                                $previousStock = \App\Models\Stock::where('product_id', $stock->product_id)
                                    ->where(function($q) use ($stock) {
                                        if ($stock->site_id) {
                                            $q->where('site_id', $stock->site_id);
                                        } else {
                                            $q->whereNull('site_id');
                                        }
                                    })
                                    ->where('status', true)
                                    ->where(function($q) use ($stock) {
                                        $q->where('created_at', '<', $stock->created_at)
                                          ->orWhere(function($q2) use ($stock) {
                                              $q2->where('created_at', '=', $stock->created_at)
                                                 ->where('id', '<', $stock->id);
                                          });
                                    })
                                    ->orderBy('created_at', 'desc')
                                    ->orderBy('id', 'desc')
                                    ->first();
                                
                                $previousQty = $previousStock ? $previousStock->quantity : 0;
                                
                                // Calculate change based on adjustment type
                                if ($stock->adjustment_type === 'in') {
                                    $changeAmount = $stock->quantity - $previousQty; // Positive
                                } elseif ($stock->adjustment_type === 'out') {
                                    $changeAmount = $previousQty - $stock->quantity; // Positive (but displayed as negative)
                                } else {
                                    // adjustment type
                                    $changeAmount = $stock->quantity - $previousQty; // Can be positive or negative
                                }
                            @endphp
                            <tr class="border-bottom">
                                <td class="text-start" style="padding: 0.75rem;">
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-semibold">
                                            {{ $stock->created_at ? $stock->created_at->format('d/m/Y') : 'N/A' }}
                                        </span>
                                        <span class="text-gray-500" style="font-size: 0.8125rem;">
                                            {{ $stock->created_at ? $stock->created_at->format('H:i:s') : '' }}
                                        </span>
                                    </div>
                                </td>
                                @if(!$product)
                                    <td class="text-start" style="padding: 0.75rem;">
                                        <span class="text-gray-800 fw-semibold">{{ $stock->product->product_name ?? 'N/A' }}</span>
                                    </td>
                                @endif
                                <td class="text-center" style="padding: 0.75rem;">
                                    <span class="badge {{ $adjustmentBadge }} d-inline-flex align-items-center gap-1" style="font-size: 0.875rem; padding: 0.375rem 0.75rem;">
                                        <i class="fa-solid {{ $adjustmentIcon }}" style="font-size: 0.875rem;"></i>
                                        <span class="fw-bold">{{ strtoupper($stock->adjustment_type) }}</span>
                                        @if($changeAmount != 0)
                                            <span class="fw-bold ms-1">
                                                @if($stock->adjustment_type === 'in')
                                                    +{{ formatQty(abs($changeAmount)) }}
                                                @elseif($stock->adjustment_type === 'out')
                                                    -{{ formatQty(abs($changeAmount)) }}
                                                @else
                                                    {{ formatQty($changeAmount) }}
                                                @endif
                                            </span>
                                        @endif
                                    </span>
                                </td>
                                <td class="text-center" style="padding: 0.75rem;">
                                    <span class="text-gray-800 fw-semibold" style="font-size: 1rem;">
                                        {{ formatQty($stock->quantity) }}
                                    </span>
                                </td>
                                <td class="text-start" style="padding: 0.75rem;">
                                    <span class="text-gray-700" style="font-size: 0.875rem;">
                                        {{ $stock->notes ?? ($stock->name ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="text-start" style="padding: 0.75rem;">
                                    @if($referenceInfo)
                                        <span class="badge badge-light-info">{{ $referenceInfo }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="text-start" style="padding: 0.75rem;">
                                    @if($stock->site)
                                        <span class="text-gray-700">{{ $stock->site->name ?? 'N/A' }}</span>
                                    @else
                                        <span class="badge badge-light-secondary">General</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $product ? '7' : '8' }}" class="text-center py-10">
                                    <div class="d-flex flex-column align-items-center">
                                        <div class="symbol symbol-circle symbol-80px mb-4">
                                            <div class="symbol-label bg-light">
                                                <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                            </div>
                                        </div>
                                        <div class="text-gray-600 fw-semibold fs-5 mb-2">
                                            No stock entries found
                                        </div>
                                        <div class="text-gray-500 fs-6">
                                            @if($search || $adjustmentType)
                                                Try changing your search or filter
                                            @else
                                                No stock entries have been recorded yet
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($stocks->count())
                <x-datatable-pagination :items="$stocks" />
            @endif
        </div>
    </div>

    <x-datatable-styles />
</div>
