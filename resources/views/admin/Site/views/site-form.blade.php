<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Site' : 'Add Site' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Site Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model.blur="name"
                           class="form-control form-control-solid @error('name') is-invalid @enderror"
                           placeholder="Enter site name"
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
                        Location <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model.blur="location"
                           class="form-control form-control-solid @error('location') is-invalid @enderror"
                           placeholder="Enter location"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('location') 
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
                        Site Supervisor <span class="text-danger">*</span>
                    </label>
                    <select wire:model.blur="site_manager_id"
                            class="form-select form-select-solid @error('site_manager_id') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="">Select Site Supervisor</option>
                        @foreach($siteManagers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                        @endforeach
                    </select>
                    @error('site_manager_id') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Start Date <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative" x-data="{
                        dateValue: @entangle('start_date'),
                        tempDate: '',
                        dateInput: null,
                        init() {
                            if (this.dateValue && this.dateValue.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                                const [d, m, y] = this.dateValue.split('/');
                                this.tempDate = `${y}-${m}-${d}`;
                            }
                            this.$nextTick(() => {
                                this.dateInput = this.$refs.datePicker;
                            });
                        },
                        openDatePicker() {
                            if (this.dateInput) {
                                this.dateInput.showPicker();
                            }
                        },
                        updateDate() {
                            if (this.tempDate) {
                                const date = new Date(this.tempDate);
                                const day = String(date.getDate()).padStart(2, '0');
                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                const year = date.getFullYear();
                                this.dateValue = `${day}/${month}/${year}`;
                            }
                        }
                    }">
                        <input type="text" 
                               x-model="dateValue"
                               @click="openDatePicker()"
                               readonly
                               placeholder="dd/mm/yyyy"
                               class="form-control form-control-solid @error('start_date') is-invalid @enderror"
                               style="cursor: pointer; padding-right: 3.5rem; height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                        <i class="fa-solid fa-calendar-days position-absolute end-0 top-50 translate-middle-y me-3" 
                           @click="openDatePicker()"
                           style="color: #1e3a8a; cursor: pointer; z-index: 10;"></i>
                        <input type="date" 
                               x-ref="datePicker"
                               x-model="tempDate"
                               @change="updateDate()"
                               style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;"/>
                    </div>
                    @error('start_date') 
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
                        Type
                    </label>
                    <select wire:model.blur="type"
                            class="form-select form-select-solid @error('type') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="">Select Type</option>
                        @foreach(\App\Utility\Enums\SiteTypeEnum::cases() as $siteType)
                            <option value="{{ $siteType->value }}">{{ $siteType->getName() ?? ucfirst($siteType->value) }}</option>
                        @endforeach
                    </select>
                    @error('type') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Work Type
                    </label>
                    <input type="text" 
                           wire:model.blur="work_type"
                           class="form-control form-control-solid @error('work_type') is-invalid @enderror"
                           placeholder="Enter work type"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('work_type') 
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
                    <select wire:model.blur="status"
                            class="form-select form-select-solid @error('status') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="">Select Status</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                    @error('status') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        End Date
                    </label>
                    <div class="position-relative" x-data="{
                        dateValue: @entangle('end_date'),
                        tempDate: '',
                        dateInput: null,
                        init() {
                            if (this.dateValue && this.dateValue.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                                const [d, m, y] = this.dateValue.split('/');
                                this.tempDate = `${y}-${m}-${d}`;
                            }
                            this.$nextTick(() => {
                                this.dateInput = this.$refs.datePicker;
                            });
                        },
                        openDatePicker() {
                            if (this.dateInput) {
                                this.dateInput.showPicker();
                            }
                        },
                        updateDate() {
                            if (this.tempDate) {
                                const date = new Date(this.tempDate);
                                const day = String(date.getDate()).padStart(2, '0');
                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                const year = date.getFullYear();
                                this.dateValue = `${day}/${month}/${year}`;
                            }
                        }
                    }">
                        <input type="text" 
                               x-model="dateValue"
                               @click="openDatePicker()"
                               readonly
                               placeholder="dd/mm/yyyy"
                               class="form-control form-control-solid @error('end_date') is-invalid @enderror"
                               style="cursor: pointer; padding-right: 3.5rem; height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                        <i class="fa-solid fa-calendar-days position-absolute end-0 top-50 translate-middle-y me-3" 
                           @click="openDatePicker()"
                           style="color: #1e3a8a; cursor: pointer; z-index: 10;"></i>
                        <input type="date" 
                               x-ref="datePicker"
                               x-model="tempDate"
                               @change="updateDate()"
                               style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;"/>
                    </div>
                    @error('end_date') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                
            </div>
        </div>
        <div class="card-footer border-0 pt-3 pb-3 bg-white">
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
                        {{ $isEditMode ? 'Update' : 'Add Site' }}
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
