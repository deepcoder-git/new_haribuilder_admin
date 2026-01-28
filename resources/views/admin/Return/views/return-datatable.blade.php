<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.4rem;">
                    Returns
                </h4>
                <button type="button"
                        wire:click="openCreateForm"
                        class="btn btn-primary fw-semibold d-flex align-items-center"
                        style="background: #1e3a8a; border: none; border-radius: 0.5rem; padding: 0.5rem 1rem;">
                    <i class="fa-solid fa-plus me-2"></i> New Return
                </button>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row mb-3 g-2">
                <div class="col-md-4">
                    <input type="text"
                           wire:model.debounce.500ms="search"
                           class="form-control form-control-solid"
                           placeholder="Search by manager, site or ID..."
                           style="border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                </div>
                <div class="col-md-2 ms-auto">
                    <select wire:model="perPage"
                            class="form-select form-select-solid"
                            style="border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive" style="border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden;">
                <table class="table table-hover mb-0">
                    <thead style="background: #f9fafb;">
                        <tr>
                            <th wire:click="sortBy('id')" style="cursor: pointer;">ID</th>
                            <th>Order</th>
                            <th>Site</th>
                            <th>Manager</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Products</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $return)
                            <tr>
                                <td>#{{ $return->id }}</td>
                                <td>
                                    @if($return->order)
                                        <span class="badge badge-light-primary">ORD{{ $return->order->id }}</span>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>{{ $return->site?->name ?? 'N/A' }}</td>
                                <td>{{ $return->manager?->name ?? 'N/A' }}</td>
                                <td>{{ optional($return->date)->format('Y-m-d') }}</td>
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
                                        <span>{{ $names }}@if($extra > 0) + {{ $extra }} more @endif</span>
                                    @else
                                        <span class="text-muted">No products</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                            wire:click="openViewModal({{ $return->id }})"
                                            class="btn btn-sm btn-light-primary me-1">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <button type="button"
                                            wire:click="openEditForm({{ $return->id }})"
                                            class="btn btn-sm btn-light-warning me-1">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No returns found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $returns->firstItem() }} to {{ $returns->lastItem() }} of {{ $returns->total() }} results
                </div>
                <div>
                    {{ $returns->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

