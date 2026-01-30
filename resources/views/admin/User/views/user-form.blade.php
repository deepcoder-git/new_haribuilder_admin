@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit User' : 'Add User' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Full Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model.blur="name"
                           class="form-control form-control-solid @error('name') is-invalid @enderror"
                           placeholder="Enter full name"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('name') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Email Address <span class="text-muted">(Optional)</span>
                    </label>
                    <input type="email" 
                           wire:model.blur="email"
                           class="form-control form-control-solid @error('email') is-invalid @enderror"
                           placeholder="Enter email address"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('email') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Mobile Number <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" style="border-radius: 0.5rem 0 0 0.5rem;">
                            +248
                        </span>
                        <input type="tel" 
                               wire:model.blur="mobile_number"
                               class="form-control form-control-solid @error('mobile_number') is-invalid @enderror"
                               placeholder="Enter 7 digit mobile number"
                               maxlength="7"
                               style="height: 44px; border-radius: 0 0.5rem 0.5rem 0; border: 1px solid #e5e7eb; border-left: 0;"/>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Country code is fixed to +248. Please enter only the 7 digit local number.
                    </small>
                    @error('mobile_number') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Role <span class="text-danger">*</span>
                    </label>
                    <select wire:model.live="role"
                            class="form-select form-select-solid @error('role') is-invalid @enderror"
                            style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                        <option value="">Select Role</option>
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                  
                </div>
                <div class="col-md-4">
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
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group position-relative">
                        <input type="{{ $showPassword ? 'text' : 'password' }}" 
                               id="password-input"
                               wire:model.blur="password"
                               class="form-control form-control-solid @error('password') is-invalid @enderror"
                               placeholder="{{ $isEditMode ? 'Leave blank to keep current password' : 'Enter password' }}"
                               style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb; padding-right: 45px;"/>
                        <button type="button" 
                                wire:click="togglePasswordVisibility"
                                class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3 password-toggle-btn"
                                style="border: none; background: none; z-index: 10; color: #6b7280; text-decoration: none;"
                                title="{{ $showPassword ? 'Hide password' : 'Show password' }}">
                            <i class="fa-solid {{ $showPassword ? 'fa-eye-slash' : 'fa-eye' }}" style="font-size: 0.875rem;"></i>
                        </button>
                    </div>
                    @error('password') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Confirm Password <span class="text-danger">*</span>
                    </label>
                    <div class="input-group position-relative">
                        <input type="{{ $showPasswordConfirmation ? 'text' : 'password' }}" 
                               id="password-confirmation-input"
                               wire:model.blur="password_confirmation"
                               class="form-control form-control-solid @error('password_confirmation') is-invalid @enderror"
                               placeholder="Confirm password"
                               style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb; padding-right: 45px;"/>
                        <button type="button" 
                                wire:click="togglePasswordConfirmationVisibility"
                                class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3 password-toggle-btn"
                                style="border: none; background: none; z-index: 10; color: #6b7280; text-decoration: none;"
                                title="{{ $showPasswordConfirmation ? 'Hide password' : 'Show password' }}">
                            <i class="fa-solid {{ $showPasswordConfirmation ? 'fa-eye-slash' : 'fa-eye' }}" style="font-size: 0.875rem;"></i>
                        </button>
                    </div>
                    @error('password_confirmation') 
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
                        Image
                    </label>
                    <div class="d-flex gap-4 align-items-start flex-wrap">
                        <div class="user-image-upload-wrapper">
                            <label for="image" class="user-image-upload-label">
                                <input type="file" 
                                       id="image"
                                       wire:model="image"
                                       accept="image/*"
                                       class="d-none"/>
                                <div class="d-flex flex-column align-items-center justify-content-center text-center p-4 h-100">
                                    <div class="mb-3 position-relative">
                                        <i class="fa-solid fa-cloud-arrow-up fs-1 text-gray-400"></i>
                                    </div>
                                    <div class="fw-semibold text-gray-600 mb-1">Click to upload</div>
                                    <div class="text-muted small">or drag and drop</div>
                                    <div class="text-muted small mt-1">PNG, JPG, GIF up to 2MB</div>
                                </div>
                            </label>
                        </div>

                        <div class="user-image-preview-wrapper">
                            @if($image)
                                @php
                                    $imageUrl = null;
                                    try {
                                        $imageUrl = $image->temporaryUrl();
                                    } catch (\Exception $e) {
                                        $imageUrl = null;
                                    }
                                @endphp
                                @if($imageUrl)
                                    <div class="user-image-preview">
                                        <img src="{{ $imageUrl }}" 
                                             alt="Preview" 
                                             class="user-image-preview-img">
                                    </div>
                                @else
                                    <div class="user-image-placeholder">
                                        <i class="fa-solid fa-file-image text-gray-300 fs-1"></i>
                                        <div class="text-muted small mt-2">File selected (preview not available)</div>
                                    </div>
                                @endif
                            @elseif($isEditMode && $editingId)
                                @php
                                    $existingUser = \App\Models\Moderator::find($editingId);
                                @endphp
                                @if($existingUser && $existingUser->image)
                                    <div class="user-image-preview">
                                        <img src="{{ Storage::url($existingUser->image) }}" 
                                             alt="User image" 
                                             class="user-image-preview-img">
                                    </div>
                                @else
                                    <div class="user-image-placeholder">
                                        <i class="fa-solid fa-image text-gray-300 fs-1"></i>
                                        <div class="text-muted small mt-2">No image selected</div>
                                    </div>
                                @endif
                            @else
                                <div class="user-image-placeholder">
                                    <i class="fa-solid fa-image text-gray-300 fs-1"></i>
                                    <div class="text-muted small mt-2">No image selected</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted mt-2 mb-0 small">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Recommended size: 500x500px. Max file size: 2MB
                    </p>
                    @error('image') 
                        <div class="text-danger mt-1">
                            <i class="fa-solid fa-exclamation-circle me-1"></i>{{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            @if($role === 'site_supervisor')
            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Assign Sites <span class="text-muted">(Optional)</span>
                    </label>
                    <div class="card border" style="border-color: #e5e7eb !important;">
                        <div class="card-body p-3">
                            @if(count($availableSites) > 0)
                                <div class="row g-2">
                                    @foreach($availableSites as $site)
                                        <div class="col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       value="{{ $site->id }}"
                                                       id="site_{{ $site->id }}"
                                                       wire:model="selectedSites"
                                                       style="cursor: pointer;">
                                                <label class="form-check-label" for="site_{{ $site->id }}" style="cursor: pointer;">
                                                    <span class="text-gray-800 fw-semibold">{{ $site->name }}</span>
                                                    @if($site->location)
                                                        <br><small class="text-muted">{{ $site->location }}</small>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="fa-solid fa-inbox text-gray-400 fs-2x mb-2"></i>
                                    <p class="text-muted mb-0">No available sites to assign</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted mt-2 mb-0 small">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Select sites to assign to this Site Supervisor. Only unassigned sites are shown.
                    </p>
                </div>
            </div>
            @endif
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
                        {{ $isEditMode ? 'Update' : 'Add User' }}
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
        .user-image-upload-wrapper {
            flex: 0 0 auto;
        }
        .user-image-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 240px;
            height: 240px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background-color: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .user-image-upload-label:hover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .user-image-preview-wrapper {
            flex: 0 0 auto;
        }
        .user-image-preview {
            position: relative;
            height: 240px;
            display: inline-block;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            line-height: 0;
        }
        .user-image-preview-img {
            height: 240px;
            width: auto;
            max-width: 500px;
            min-width: 200px;
            object-fit: contain;
            border-radius: 8px;
            display: block;
        }
        .user-image-placeholder {
            height: 240px;
            width: 240px;
            min-width: 200px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #f9fafb;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .password-toggle-btn {
            transition: color 0.2s ease;
        }
        .password-toggle-btn:hover {
            color: #1e3a8a !important;
        }
        .password-toggle-btn:focus {
            outline: none;
            box-shadow: none;
        }
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem !important;
            }
        }
    </style>
</div>