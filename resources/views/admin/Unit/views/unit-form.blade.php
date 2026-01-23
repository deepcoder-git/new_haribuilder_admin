<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Unit' : 'Add Unit' }}
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
                           wire:model.blur="name"
                           class="form-control form-control-solid @error('name') is-invalid @enderror"
                           placeholder="Enter unit name"
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
                        Status
                    </label>
                    <div class="form-check form-switch form-check-custom form-check-solid form-check-success d-flex align-items-center" style="height: 44px;">
                        <input class="form-check-input" 
                               type="checkbox" 
                               wire:model="status"
                               style="cursor: pointer; width: 40px; height: 20px;"/>
                        <label class="form-check-label fw-semibold ms-3" style="margin-top: 0;">
                            <span class="text-gray-700">Status:</span>
                            <span class="ms-2 {{ $status ? 'text-success' : 'text-danger' }}">
                                {{ $status ? 'Active' : 'Inactive' }}
                            </span>
                        </label>
                    </div>
                    @error('status') 
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
                        {{ $isEditMode ? 'Update' : 'Add Unit' }}
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

