<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pt-2 pb-2">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-0">
                    <i class="fa-solid fa-shopping-cart position-absolute ms-4" style="color: #1e3a8a; font-size: 1rem;"></i>
                    <h2 class="text-gray-800 fw-bold mb-0 ms-10" style="font-size: 1.25rem;">View Product Purchase</h2>
                </div>
            </div>
            <div class="card-toolbar">
                <button type="button" 
                        wire:click="edit"
                        class="btn btn-primary btn-sm me-2"
                        style="background: #1e3a8a; border: none;">
                    <i class="fa-solid fa-pen me-2"></i>Edit
                </button>
                <button type="button" 
                        wire:click="back"
                        class="btn btn-light btn-sm">
                    <i class="fa-solid fa-arrow-left me-2"></i>Back to List
                </button>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-600 mb-1" style="font-size: 0.875rem;">
                        Invoice number
                    </label>
                    <div class="text-gray-800 fw-bold" style="font-size: 1rem;">
                        {{ $purchase->purchase_number }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-600 mb-1" style="font-size: 0.875rem;">
                        Supplier
                    </label>
                    <div class="text-gray-800" style="font-size: 1rem;">
                        {{ $purchase->supplier->name ?? 'N/A' }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-600 mb-1" style="font-size: 0.875rem;">
                        Purchase Date
                    </label>
                    <div class="text-gray-800" style="font-size: 1rem;">
                        {{ $purchase->purchase_date ? $purchase->purchase_date->format('d/m/Y') : 'N/A' }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-600 mb-1" style="font-size: 0.875rem;">
                        Items Count
                    </label>
                    <div class="text-gray-800 fw-semibold" style="font-size: 1rem;">
                        {{ $purchase->items->count() }} item(s)
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-600 mb-1" style="font-size: 0.875rem;">
                        Created By
                    </label>
                    <div class="text-gray-800" style="font-size: 1rem;">
                        {{ $purchase->creator->name ?? 'N/A' }}
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-600 mb-1" style="font-size: 0.875rem;">
                        Created At
                    </label>
                    <div class="text-gray-800" style="font-size: 1rem;">
                        {{ $purchase->created_at ? $purchase->created_at->format('d/m/Y') : 'N/A' }}
                    </div>
                </div>
            </div>

            @if($purchase->notes)
            <div class="row g-4 mb-4">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-gray-600 mb-2" style="font-size: 0.875rem;">
                        Notes
                    </label>
                    <div class="text-gray-800" style="font-size: 1rem; padding: 1rem; background: #f9fafb; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        {{ $purchase->notes }}
                    </div>
                </div>
            </div>
            @endif

            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-gray-700 mb-3" style="font-size: 1.0625rem;">
                        Purchase Items
                    </label>
                    <div class="table-responsive" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
                        <table class="table table-bordered mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 50px; padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">#</th>
                                    <th style="padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">Product</th>
                                    <th style="padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">Category</th>
                                    <th style="padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">Unit</th>
                                    <th style="padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">Quantity</th>
                                    <th style="padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">Unit Price</th>
                                    <th style="padding: 0.875rem 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; background: #f3f4f6; border-bottom: 2px solid #1e3a8a;">Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchase->items as $index => $item)
                                    <tr style="border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s ease;">
                                        <td style="padding: 0.875rem 0.75rem; text-align: center; vertical-align: middle; color: #374151; font-size: 0.9375rem;">
                                            {{ $index + 1 }}
                                        </td>
                                        <td style="padding: 0.875rem 0.75rem; text-align: left; vertical-align: middle;">
                                            <span class="text-gray-800 fw-semibold" style="font-size: 0.9375rem;">
                                                {{ $item->product->product_name ?? 'N/A' }}
                                            </span>
                                        </td>
                                        <td style="padding: 0.875rem 0.75rem; text-align: center; vertical-align: middle; color: #374151; font-size: 0.9375rem;">
                                            {{ $item->product->category->name ?? 'N/A' }}
                                        </td>
                                        <td style="padding: 0.875rem 0.75rem; text-align: center; vertical-align: middle; color: #374151; font-size: 0.9375rem;">
                                            {{ $item->product->unit_type ?? 'N/A' }}
                                        </td>
                                        <td style="padding: 0.875rem 0.75rem; text-align: center; vertical-align: middle; color: #374151; font-size: 0.9375rem;">
                                            {{ number_format($item->quantity, 0) }}
                                        </td>
                                        <td style="padding: 0.875rem 0.75rem; text-align: center; vertical-align: middle; color: #374151; font-size: 0.9375rem;">
                                            {{ number_format($item->unit_price, 0) }}
                                        </td>
                                        <td style="padding: 0.875rem 0.75rem; text-align: center; vertical-align: middle;">
                                            <span class="text-gray-800 fw-semibold" style="font-size: 0.9375rem;">
                                                {{ number_format($item->total_price, 0) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted" style="font-size: 0.9375rem;">
                                            No items found
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr style="background: #f9fafb; border-top: 2px solid #1e3a8a;">
                                    <td colspan="6" style="padding: 1rem 0.75rem; text-align: right; font-weight: 600; font-size: 1rem; color: #374151; vertical-align: middle;">
                                        Grand Total:
                                    </td>
                                    <td style="padding: 1rem 0.75rem; text-align: center; font-weight: 700; font-size: 1.125rem; color: #1e3a8a; vertical-align: middle;">
                                        {{ number_format($purchase->total_amount, 0) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .table tbody tr:hover {
        background-color: #f9fafb !important;
    }
    
    .table tbody tr {
        transition: background-color 0.2s ease;
    }
</style>
@endpush

