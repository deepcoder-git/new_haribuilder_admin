<div class="card">
    <x-view-header 
        moduleName="Role" 
        moduleIcon="user-shield" 
        indexRoute="admin.role-permissions.index" 
    />
    <div class="card-body p-8">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body p-6">
                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #1e3a8a; margin-bottom: 0.5rem;">
                                Role Name
                            </label>
                            <input type="text" 
                                   class="form-control form-control-solid" 
                                   value="{{ $role->label() }}" 
                                   readonly
                                   style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 0.75rem 1rem;">
                        </div>
                        {{-- <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #1e3a8a; margin-bottom: 0.5rem;">
                                Role Value
                            </label>
                            <input type="text" 
                                   class="form-control form-control-solid" 
                                   value="{{ $role->value }}" 
                                   readonly
                                   style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 0.75rem 1rem;">
                        </div> --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #1e3a8a; margin-bottom: 0.5rem;">
                                User Assigned
                            </label>
                            <input type="text" 
                                   class="form-control form-control-solid" 
                                   value="{{ $userCount }}" 
                                   readonly
                                   style="background-color: #f9fafb; border: 1px solid #e5e7eb; padding: 0.75rem 1rem;">
                        </div>
                        <div class="mb-0">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       value="1" 
                                       id="fullAccessCheckbox"
                                       @if($role === \App\Utility\Enums\RoleEnum::SuperAdmin) checked @endif
                                       disabled
                                       style="width: 1.25rem; height: 1.25rem; cursor: not-allowed;">
                                <label class="form-check-label fw-semibold" for="fullAccessCheckbox" style="color: #374151; margin-left: 0.5rem;">
                                    Full Access To System
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                @if(!empty($modulesWithActions))
                <div class="card shadow-sm">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h6 class="fw-bold text-primary mb-0">
                                <i class="fa-solid fa-shield-halved me-2"></i>
                                Modules
                            </h6>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 mb-0">
                                <thead>
                                    <tr class="fw-bold fs-7 text-uppercase gs-0" style="background: transparent; border-bottom: 2px solid #1e3a8a;">
                                        <th class="min-w-200px" style="padding: 0.625rem 0.5rem; border-top: none; color: #1e3a8a; font-weight: 700;">
                                            Modules
                                        </th>
                                        <th class="text-center" style="padding: 0.625rem 0.5rem; border-top: none; color: #1e3a8a; font-weight: 700;">
                                            View
                                        </th>
                                        <th class="text-center" style="padding: 0.625rem 0.5rem; border-top: none; color: #1e3a8a; font-weight: 700;">
                                            Add
                                        </th>
                                        <th class="text-center" style="padding: 0.625rem 0.5rem; border-top: none; color: #1e3a8a; font-weight: 700;">
                                            Edit
                                        </th>
                                        <th class="text-center" style="padding: 0.625rem 0.5rem; border-top: none; color: #1e3a8a; font-weight: 700;">
                                            Delete
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 fw-semibold">
                                    @foreach($modulesWithActions as $module)
                                        <tr style="border-bottom: 1px solid #f3f4f6;">
                                            <td style="padding: 0.75rem 0.5rem; vertical-align: middle;">
                                                <span class="fw-semibold" style="font-size: 0.9375rem;">{{ $module['name'] }}</span>
                                            </td>
                                            <td class="text-center" style="padding: 0.75rem 0.5rem; vertical-align: middle;">
                                                <div class="form-check form-check-custom form-check-solid d-flex justify-content-center">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           @if($module['actions']['view']) checked @endif
                                                           disabled
                                                           style="width: 1.25rem; height: 1.25rem; cursor: not-allowed;">
                                                </div>
                                            </td>
                                            <td class="text-center" style="padding: 0.75rem 0.5rem; vertical-align: middle;">
                                                <div class="form-check form-check-custom form-check-solid d-flex justify-content-center">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           @if($module['actions']['add']) checked @endif
                                                           disabled
                                                           style="width: 1.25rem; height: 1.25rem; cursor: not-allowed;">
                                                </div>
                                            </td>
                                            <td class="text-center" style="padding: 0.75rem 0.5rem; vertical-align: middle;">
                                                <div class="form-check form-check-custom form-check-solid d-flex justify-content-center">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           @if($module['actions']['edit']) checked @endif
                                                           disabled
                                                           style="width: 1.25rem; height: 1.25rem; cursor: not-allowed;">
                                                </div>
                                            </td>
                                            <td class="text-center" style="padding: 0.75rem 0.5rem; vertical-align: middle;">
                                                <div class="form-check form-check-custom form-check-solid d-flex justify-content-center">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           @if($module['actions']['delete']) checked @endif
                                                           disabled
                                                           style="width: 1.25rem; height: 1.25rem; cursor: not-allowed;">
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

