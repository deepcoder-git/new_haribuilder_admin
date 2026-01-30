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
                               placeholder="Search returns by manager, site, ID..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <button type="button" 
                                wire:click="openCreateForm"
                                class="btn btn-primary d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #1e3a8a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-plus me-2"></i>
                            New Return
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed mb-0">
                    <thead>
                        <tr class="fw-bold text-uppercase">
                            <th>ID</th>
                            <th>ORDER</th>
                            <th>SITE</th>
                            <th>MANAGER</th>
                            <th class="cursor-pointer" wire:click="sortBy('date')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>DATE</span>
                                    @if($sortField === 'date')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>STATUS</th>
                            <th>PRODUCTS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $return)
                        <tr>
                            <td>
                                <span class="text-gray-800 fw-semibold">#{{ $return->id }}</span>
                            </td>
                            <td>
                                @if($return->order)
                                    <span class="badge badge-light-primary">ORD{{ $return->order->id }}</span>
                                @else
                                    <span class="text-gray-500">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $return->site?->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $return->manager?->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $return->date ? $return->date->format('d-m-Y') : 'N/A' }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $status = strtolower($return->status ?? 'pending');
                                    $badgeClass = [
                                        'pending' => 'badge-light-warning',
                                        'approved' => 'badge-light-primary',
                                        'rejected' => 'badge-light-danger',
                                        'completed' => 'badge-light-success',
                                    ][$status] ?? 'badge-light-secondary';
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td>
                                @if($return->relationLoaded('items') && $return->items->isNotEmpty())
                                    @php
                                        $names = $return->items->map(function ($item) {
                                            return $item->product?->product_name;
                                        })->filter()->take(3)->implode(', ');
                                        $extra = max($return->items->count() - 3, 0);
                                    @endphp
                                    <span class="text-gray-700">{{ $names }}@if($extra > 0) + {{ $extra }} more @endif</span>
                                @else
                                    <span class="text-gray-500">No products</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="#" 
                                       wire:click.prevent="openViewModal({{ $return->id }})"
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Details"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="openEditForm({{ $return->id }})"
                                       class="btn btn-sm btn-icon btn-light-info"
                                       title="Edit Return"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No returns found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-datatable-pagination :items="$returns" />
        </div>
    </div>

    <x-datatable-styles />
</div>
