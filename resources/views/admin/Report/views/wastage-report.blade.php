<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.4rem;">
                    Wastage Report
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row mb-3 g-2">
                <div class="col-md-4">
                    <input type="text"
                           wire:model.debounce.500ms="search"
                           class="form-control form-control-solid"
                           placeholder="Search by product or category..."
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
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-end">Wastage Qty (-)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->product_name }}</td>
                                <td>{{ $row->category_name ?? '-' }}</td>
                                <td class="text-end text-danger fw-bold">
                                    -{{ formatQty($row->total_wastage_qty ?? 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    No wastage records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} results
                </div>
                <div>
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

