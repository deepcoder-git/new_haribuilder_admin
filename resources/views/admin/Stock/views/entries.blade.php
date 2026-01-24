<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-4 py-3">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center gap-3 flex-wrap" style="align-items: center;">
                    @if($product)
                        <span class="badge badge-light-primary d-inline-flex align-items-center" style="font-weight: 600; font-size: 0.875rem; height: 44px; padding: 0 1rem; white-space: nowrap;">
                            <span class="fw-bold me-2">{{ $product->product_name }}</span>
                            <i class="fa-solid fa-box me-1"></i>
                            Current Stock: {{ formatQty($product->total_stock_quantity) }}
                        </span>
                    @else
                        <h4 class="mb-0 fw-bold text-gray-800 fs-4" style="line-height: 44px;">Stock Entries</h4>
                    @endif
                    <div class="d-flex align-items-center position-relative" style="min-width: 250px; flex: 1 1 300px; max-width: 100%;">
                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 1;"></i>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               class="form-control form-control-solid"
                               placeholder="Search by product name..."
                               style="border-radius: 0.5rem; height: 44px; padding-left: 3rem; width: 100%;">
                    </div>
                    <select wire:model.live="adjustmentType" class="form-select form-select-solid" style="border-radius: 0.5rem; height: 44px; min-width: 160px; flex-shrink: 0;">
                        <option value="">All Types</option>
                        <option value="in">Stock In (+)</option>
                        <option value="out">Stock Out (-)</option>
                        <option value="adjustment">Adjustment (=)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed mb-0" style="font-size: 0.9375rem;">
                    <thead>
                        <tr class="fw-bold text-uppercase" style="border-bottom: 2px solid #e5e7eb; background: #f9fafb;">
                            <th class="text-start" style="padding: 1rem 0.75rem; color: #374151; font-size: 0.8125rem; font-weight: 600;">DATE & TIME</th>
                            @if(!$product)
                                <th class="text-start" style="padding: 1rem 0.75rem; color: #374151; font-size: 0.8125rem; font-weight: 600;">PRODUCT</th>
                            @endif
                            <th class="text-center" style="padding: 1rem 0.75rem; color: #374151; font-size: 0.8125rem; font-weight: 600;">TYPE</th>
                            <th class="text-center" style="padding: 1rem 0.75rem; color: #374151; font-size: 0.8125rem; font-weight: 600;">QUANTITY</th>
                            <th class="text-start" style="padding: 1rem 0.75rem; color: #374151; font-size: 0.8125rem; font-weight: 600;">NOTES</th>
                            <th class="text-start" style="padding: 1rem 0.75rem; color: #374151; font-size: 0.8125rem; font-weight: 600;">REFERENCE</th>
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
                                // Find previous stock entry for the same product (global timeline, any site)
                                $previousStock = \App\Models\Stock::where('product_id', $stock->product_id)
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
                            <tr class="border-bottom stock-entry-row" style="transition: background-color 0.2s ease;">
                                <td class="text-start" style="padding: 1rem 0.75rem;">
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 fw-semibold" style="font-size: 0.9375rem;">
                                            {{ $stock->created_at ? $stock->created_at->format('d/m/Y') : 'N/A' }}
                                        </span>
                                        <span class="text-gray-500" style="font-size: 0.8125rem; margin-top: 2px;">
                                            {{ $stock->created_at ? $stock->created_at->format('H:i:s') : '' }}
                                        </span>
                                    </div>
                                </td>
                                @if(!$product)
                                    <td class="text-start" style="padding: 1rem 0.75rem;">
                                        <span class="text-gray-800 fw-semibold" style="font-size: 0.9375rem;">{{ $stock->product->product_name ?? 'N/A' }}</span>
                                    </td>
                                @endif
                                <td class="text-center" style="padding: 1rem 0.75rem;">
                                    @php
                                        $typeLabel = strtoupper($stock->adjustment_type);
                                        $changeDisplay = '';
                                        if ($changeAmount != 0) {
                                            if ($stock->adjustment_type === 'in') {
                                                $changeDisplay = '+' . number_format(abs($changeAmount));
                                            } elseif ($stock->adjustment_type === 'out') {
                                                $changeDisplay = '  ' . number_format(abs($changeAmount));
                                            } else {
                                                $changeDisplay = number_format($changeAmount);
                                            }
                                        }
                                    @endphp
                                    <span class="badge {{ $adjustmentBadge }} d-inline-flex align-items-center gap-2" style="font-size: 0.875rem; padding: 0.5rem 0.875rem;">
                                        <i class="fa-solid {{ $adjustmentIcon }}" style="font-size: 0.875rem;"></i>
                                        <span class="fw-bold">{{ $typeLabel }}</span>
                                        @if($changeDisplay)
                                            <span class="fw-bold">{{ $changeDisplay }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="text-center" style="padding: 1rem 0.75rem;">
                                    <span class="text-gray-800 fw-bold" style="font-size: 1rem;">
                                        {{ formatQty($stock->quantity) }}
                                    </span>
                                </td>
                                <td class="text-start" style="padding: 1rem 0.75rem;">
                                    <span class="text-gray-700" style="font-size: 0.875rem; line-height: 1.5;">
                                        {{ $stock->notes ?? ($stock->name ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="text-start" style="padding: 1rem 0.75rem;">
                                    @php
                                        $referenceUrl = $this->getReferenceUrl($stock);
                                    @endphp
                                    @if($referenceInfo)
                                        @if($referenceUrl)
                                            <a href="{{ $referenceUrl }}" 
                                               class="badge badge-light-info text-decoration-none stock-reference-link" 
                                               style="cursor: pointer; transition: all 0.2s ease;">
                                                {{ $referenceInfo }}
                                            </a>
                                        @else
                                            <span class="badge badge-light-info">{{ $referenceInfo }}</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $product ? '6' : '7' }}" class="text-center py-10">
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
    
    <style>
        .stock-entry-row:hover {
            background-color: #f9fafb !important;
        }
        
        .stock-reference-link:hover {
            background-color: #0dcaf0 !important;
            color: white !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
    </style>
</div>
