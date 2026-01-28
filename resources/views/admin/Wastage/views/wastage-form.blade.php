<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Wastage' : 'Add Wastage' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Type <span class="text-danger">*</span>
                    </label>
                    <select wire:model.blur="type"
                            class="form-select form-select-solid @error('type') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        @foreach($wastageTypes as $wastageType)
                            <option value="{{ $wastageType->value }}">{{ $wastageType->getName() }}</option>
                        @endforeach
                    </select>
                    @error('type') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Site Name
                    </label>
                    <select wire:model.live="site_id"
                            class="form-select form-select-solid @error('site_id') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="">Select Site</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </select>
                    @error('site_id') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Manager Name <span class="text-danger">*</span>
                    </label>
                    <select wire:model.blur="manager_id"
                            class="form-select form-select-solid @error('manager_id') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"
                            @if(!$site_id) disabled @endif>
                        <option value="">Select Manager</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                        @endforeach
                    </select>
                    @if(!$site_id)
                        <small class="text-muted mt-1 d-block">
                            <i class="fa-solid fa-info-circle"></i> Please select a site first
                        </small>
                    @endif
                    @error('manager_id') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Order ID
                    </label>
                    <select wire:model.live="order_id"
                            class="form-select form-select-solid @error('order_id') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="">Select Order</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}">ORD{{ $order->id }}</option>
                        @endforeach
                        <option value="other">Other</option>
                    </select>
                    @error('order_id') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" 
                           wire:model.blur="date"
                           class="form-control form-control-solid @error('date') is-invalid @enderror"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('date') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Reason
                    </label>
                    <textarea wire:model.blur="reason"
                              class="form-control form-control-solid @error('reason') is-invalid @enderror"
                              placeholder="Enter reason for wastage"
                              rows="2"
                              style="border-radius: 0.5rem; border: 1px solid #e5e7eb;"></textarea>
                    @error('reason') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold text-gray-700 mb-0">
                            Products <span class="text-danger">*</span>
                        </label>
                        @if($order_id === 'other')
                            <button type="button" 
                                    wire:click="addProductRow"
                                    class="btn btn-sm btn-primary"
                                    style="padding: 0.375rem 0.75rem; font-size: 0.875rem;">
                                <i class="fa-solid fa-plus me-1"></i>Add Product
                            </button>
                        @endif
                    </div>
                    
                    <div class="table-responsive" style="border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden;">
                        <table class="table table-bordered mb-0" style="margin-bottom: 0;">
                            <thead style="background: #f9fafb;">
                                <tr>
                                    <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Product</th>
                                    <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Category</th>
                                    <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600; text-align: right;">Wastage Qty</th>
                                    <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600;">Unit Type</th>
                                    <th style="padding: 0.75rem; font-size: 0.875rem; font-weight: 600; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($wastageProducts as $index => $product)
                                    @php
                                        $productModel = !empty($product['product_id']) ? \App\Models\Product::find($product['product_id']) : null;
                                    @endphp
                                    <tr wire:key="product-row-{{ $index }}">
                                        <td style="padding: 0.5rem;">
                                            <select wire:model.blur="wastageProducts.{{ $index }}.product_id"
                                                    wire:change="$wire.updatedProductId($event.target.value, {{ $index }})"
                                                    class="form-select form-select-solid @error('wastageProducts.'.$index.'.product_id') is-invalid @enderror"
                                                    style="height: 42px; font-size: 0.875rem;">
                                                <option value="">Select Product</option>
                                                @foreach($products as $prod)
                                                    <option value="{{ $prod->id }}">{{ $prod->product_name }}</option>
                                                @endforeach
                                            </select>
                                            @error("wastageProducts.{$index}.product_id") 
                                                <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> 
                                            @enderror
                                        </td>
                                        <td style="padding: 0.5rem;">
                                            <div class="form-control form-control-solid" style="background: #f9fafb; height: 42px; display: flex; align-items: center; font-size: 0.875rem;">
                                                {{ $productModel && $productModel->category ? $productModel->category->name : '-' }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.5rem;">
                                            <input type="number" 
                                                   wire:model.blur="wastageProducts.{{ $index }}.wastage_qty"
                                                   step="1"
                                                   min="1"
                                                   class="form-control form-control-solid @error('wastageProducts.'.$index.'.wastage_qty') is-invalid @enderror"
                                                   placeholder="0"
                                                   style="height: 42px; text-align: right; font-size: 0.875rem;"/>
                                            @error("wastageProducts.{$index}.wastage_qty") 
                                                <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> 
                                            @enderror
                                        </td>
                                        <td style="padding: 0.5rem;">
                                            <input type="text" 
                                                   wire:model.blur="wastageProducts.{{ $index }}.unit_type"
                                                   class="form-control form-control-solid @error('wastageProducts.'.$index.'.unit_type') is-invalid @enderror"
                                                   placeholder="Unit"
                                                   style="height: 42px; font-size: 0.875rem;"/>
                                            @error("wastageProducts.{$index}.unit_type") 
                                                <div class="text-danger small mt-1" style="font-size: 0.75rem;">{{ $message }}</div> 
                                            @enderror
                                        </td>
                                        <td style="padding: 0.5rem; text-align: center;">
                                            @if(count($wastageProducts) > 1)
                                                <button type="button" 
                                                        wire:click="removeProductRow({{ $index }})"
                                                        class="btn btn-sm btn-danger"
                                                        style="width: 32px; height: 32px; padding: 0;">
                                                    <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No products added</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @error('wastageProducts') 
                        <div class="text-danger small mt-2">{{ $message }}</div> 
                    @enderror
                </div>
            </div>
        </div>
        <div class="card-footer border-0 pt-3 bg-white">
            <div class="d-flex justify-content-end gap-2">
                <button type="button" 
                        wire:click="cancel" 
                        class="btn btn-light fw-semibold px-4"
                        style="height: 44px; border-radius: 0.5rem; min-width: 100px;"
                        wire:loading.attr="disabled">
                    Cancel
                </button>
                <button type="button" 
                        wire:click="save" 
                        class="btn btn-primary fw-semibold px-4 d-flex align-items-center justify-content-center" 
                        style="background: #1e3a8a; border: none; height: 44px; border-radius: 0.5rem; min-width: 120px; color: #ffffff;"
                        wire:loading.attr="disabled">
                    <span wire:target="save" class="d-flex align-items-center">
                        <i class="fa-solid fa-{{ $isEditMode ? 'check' : 'plus' }} me-2"></i>
                        {{ $isEditMode ? 'Update' : 'Add Wastage' }}
                    </span>
                </button>
            </div>
        </div>
    </div>

    <style>
        .form-control:focus, .form-select:focus {
            border-color: #1e3a8a !important;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.1) !important;
        }
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc3545 !important;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
        .btn-primary:hover {
            background: #1e40af !important;
            border-color: #1e40af !important;
        }
        .btn-primary:focus {
            background: #1e3a8a !important;
            border-color: #1e3a8a !important;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25) !important;
        }
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem !important;
            }
        }
    </style>
</div>

