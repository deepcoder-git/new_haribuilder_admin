<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Supplier' : 'Add Supplier' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model="name"
                           class="form-control form-control-solid @error('name') is-invalid @enderror"
                           placeholder="Enter supplier name"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('name') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Type <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        @php
                            $typeOptions = ['General Supplier', 'LPO Supplier', 'Overseas Supplier'];
                            $selectedType = $supplier_type ?: 'Select Type';
                        @endphp
                        <button type="button"
                                wire:click="toggleDropdown('supplier_type')"
                                wire:loading.attr="disabled"
                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('supplier_type') is-invalid @enderror"
                                style="height: 44px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                {{ $selectedType }}
                            </span>
                            <i class="fa-solid fa-chevron-{{ isset($dropdownOpen['supplier_type']) && $dropdownOpen['supplier_type'] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        @if(isset($dropdownOpen['supplier_type']) && $dropdownOpen['supplier_type'])
                            <div class="position-absolute bg-white border rounded shadow-lg" 
                                 style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 200px; overflow-y: auto; width: 100%;"
                                 wire:click.stop
                                 x-data="{ open: @entangle('dropdownOpen.supplier_type') }"
                                 x-show="open"
                                 x-cloak
                                 x-on:click.outside="if (typeof @this !== 'undefined') { @this.call('closeDropdown', 'supplier_type'); }">
                                @foreach($typeOptions as $option)
                                    <div wire:click="selectOption('supplier_type', '{{ $option }}')"
                                         class="px-3 py-2 cursor-pointer"
                                         style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer;"
                                         onmouseover="this.style.backgroundColor='#f9fafb'"
                                         onmouseout="this.style.backgroundColor='white'">
                                        <div class="fw-semibold" style="font-size: 0.875rem; color: #1f2937;">
                                            {{ $option }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('supplier_type') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Email
                    </label>
                    <input type="email" 
                           wire:model="email"
                           class="form-control form-control-solid @error('email') is-invalid @enderror"
                           placeholder="Enter email"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('email') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Phone
                    </label>
                    <input type="text" 
                           wire:model="phone"
                           class="form-control form-control-solid @error('phone') is-invalid @enderror"
                           placeholder="Enter phone number"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('phone') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        TIN Number
                    </label>
                    <input type="text" 
                           wire:model="tin_number"
                           class="form-control form-control-solid @error('tin_number') is-invalid @enderror"
                           placeholder="Enter TIN number"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('tin_number') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Status <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        @php
                            $statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
                            $selectedStatus = $statusOptions[$status] ?? 'Select Status';
                        @endphp
                        <button type="button"
                                wire:click="toggleDropdown('status')"
                                wire:loading.attr="disabled"
                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('status') is-invalid @enderror"
                                style="height: 44px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                {{ $selectedStatus }}
                            </span>
                            <i class="fa-solid fa-chevron-{{ isset($dropdownOpen['status']) && $dropdownOpen['status'] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        @if(isset($dropdownOpen['status']) && $dropdownOpen['status'])
                            <div class="position-absolute bg-white border rounded shadow-lg" 
                                 style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 200px; overflow-y: auto; width: 100%;"
                                 wire:click.stop
                                 x-data="{ open: @entangle('dropdownOpen.status') }"
                                 x-show="open"
                                 x-cloak
                                 x-on:click.outside="if (typeof @this !== 'undefined') { @this.call('closeDropdown', 'status'); }">
                                @foreach($statusOptions as $value => $label)
                                    <div wire:click="selectOption('status', '{{ $value }}')"
                                         class="px-3 py-2 cursor-pointer"
                                         style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer;"
                                         onmouseover="this.style.backgroundColor='#f9fafb'"
                                         onmouseout="this.style.backgroundColor='white'">
                                        <div class="fw-semibold" style="font-size: 0.875rem; color: #1f2937;">
                                            {{ $label }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('status') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Address
                    </label>
                    <textarea wire:model="address"
                              class="form-control form-control-solid @error('address') is-invalid @enderror"
                              placeholder="Enter address"
                              rows="3"
                              style="border-radius: 0.5rem; border: 1px solid #e5e7eb;"></textarea>
                    @error('address') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea wire:model="description"
                              class="form-control form-control-solid @error('description') is-invalid @enderror"
                              placeholder="Enter description"
                              rows="3"
                              style="border-radius: 0.5rem; border: 1px solid #e5e7eb;"></textarea>
                    @error('description') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
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
                        {{ $isEditMode ? 'Update' : 'Add Supplier' }}
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

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</div>

