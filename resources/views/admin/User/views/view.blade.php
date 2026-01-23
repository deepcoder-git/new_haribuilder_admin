@php
use Illuminate\Support\Facades\Storage;
@endphp

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="fa-solid fa-user position-absolute ms-4" style="color: #1e3a8a;"></i>
                <h2 class="text-gray-800 fw-bold mb-0 ms-10">View User</h2>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary me-2" style="background: #1e3a8a; border: none;">
                <i class="fa-solid fa-pen me-2"></i>Edit
            </a>
            <a href="{{ route('admin.users.index') }}" class="btn btn-light">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>
    <div class="card-body p-8">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body p-6">
                        <div class="d-flex flex-column align-items-center mb-6">
                            @if($user->image)
                                <div class="mb-4">
                                    <img src="{{ Storage::url($user->image) }}" 
                                         alt="Profile image" 
                                         style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 4px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                                </div>
                            @else
                                <div class="mb-4">
                                    <div style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; border: 4px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                                        <i class="fa-solid fa-user text-white" style="font-size: 4rem;"></i>
                                    </div>
                                </div>
                            @endif
                            <h3 class="text-gray-800 fw-bold mb-2">{{ $user->name ?? 'N/A' }}</h3>
                            @if($user->role)
                                @php
                                    $role = $user->role instanceof \App\Utility\Enums\RoleEnum ? $user->role->value : $user->role;
                                    $badgeClass = match($role) {
                                        'super_admin' => 'badge-light-danger',
                                        'admin' => 'badge-light-primary',
                                        'moderator' => 'badge-light-info',
                                        'site_supervisor' => 'badge-light-warning',
                                        'store_manager' => 'badge-light-success',
                                        'transport_manager' => 'badge-light-dark',
                                        default => 'badge-light-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} fs-6 px-3 py-2 mb-3">{{ ucfirst(str_replace('_', ' ', $role)) }}</span>
                            @endif
                        </div>
                        <div class="separator separator-dashed my-6"></div>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 mb-0">
                                <tbody class="text-gray-700 fw-semibold">
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td class="min-w-150px fw-bold" style="padding: 0.75rem 1rem; vertical-align: middle; color: #1e3a8a;">
                                            User ID
                                        </td>
                                        <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                                            <span class="text-gray-800 fw-bold fs-5">{{ $user->id ?? 'N/A' }}</span>
                                        </td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td class="min-w-150px fw-bold" style="padding: 0.75rem 1rem; vertical-align: middle; color: #1e3a8a;">
                                            Email Address
                                        </td>
                                        <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                                            <span class="text-gray-800 fw-bold fs-5">{{ $user->email ?? 'N/A' }}</span>
                                        </td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td class="min-w-150px fw-bold" style="padding: 0.75rem 1rem; vertical-align: middle; color: #1e3a8a;">
                                            Mobile Number
                                        </td>
                                        <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                                            <span class="text-gray-800 fw-bold fs-5">{{ $user->mobile_number ?? 'N/A' }}</span>
                                        </td>
                                    </tr>
                                    @if($user->created_at)
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td class="min-w-150px fw-bold" style="padding: 0.75rem 1rem; vertical-align: middle; color: #1e3a8a;">
                                            Created At
                                        </td>
                                        <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                                            <span class="text-gray-800 fw-bold fs-5">{{ $user->created_at->format('d-m-Y H:i:s') }}</span>
                                        </td>
                                    </tr>
                                    @endif
                                    @if($user->updated_at)
                                    <tr style="border-bottom: 1px solid #f3f4f6;">
                                        <td class="min-w-150px fw-bold" style="padding: 0.75rem 1rem; vertical-align: middle; color: #1e3a8a;">
                                            Updated At
                                        </td>
                                        <td style="padding: 0.75rem 1rem; vertical-align: middle;">
                                            <span class="text-gray-800 fw-bold fs-5">{{ $user->updated_at->format('d-m-Y H:i:s') }}</span>
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @php
                $userRole = $user->role instanceof \App\Utility\Enums\RoleEnum ? $user->role->value : $user->role;
                $assignedSites = [];
                if ($userRole === 'site_supervisor') {
                    $assignedSites = \App\Models\Site::where('site_manager_id', $user->id)->get();
                }
            @endphp
            @if($userRole === 'site_supervisor' && count($assignedSites) > 0)
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header border-0 bg-white pb-3">
                        <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a;">
                            <i class="fa-solid fa-building me-2"></i>Assigned Sites
                        </h4>
                    </div>
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 mb-0">
                                <thead>
                                    <tr class="text-start text-gray-700 fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-100px" style="padding: 0.75rem 1rem;">Site Name</th>
                                        <th class="min-w-150px" style="padding: 0.75rem 1rem;">Location</th>
                                        <th class="min-w-100px" style="padding: 0.75rem 1rem;">Type</th>
                                        <th class="min-w-100px" style="padding: 0.75rem 1rem;">Status</th>
                                        <th class="text-end min-w-100px" style="padding: 0.75rem 1rem;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    @foreach($assignedSites as $site)
                                    <tr>
                                        <td style="padding: 0.75rem 1rem;">
                                            <span class="text-gray-800 fw-bold">{{ $site->name ?? 'N/A' }}</span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <span class="text-gray-700">{{ $site->location ?? 'N/A' }}</span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            @if($site->type)
                                                <span class="badge badge-light-info">{{ ucfirst(str_replace('_', ' ', $site->type->value ?? $site->type)) }}</span>
                                            @else
                                                <span class="text-muted">N/A</span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            @if($site->status)
                                                <span class="badge badge-light-success">Active</span>
                                            @else
                                                <span class="badge badge-light-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-end" style="padding: 0.75rem 1rem;">
                                            @if($site->slug)
                                                <a href="{{ route('admin.sites.view', $site->slug) }}" 
                                                   class="btn btn-sm btn-icon btn-light-primary"
                                                   title="View Site"
                                                   style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                                </a>
                                            @else
                                                <span class="text-muted small">N/A</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @elseif($userRole === 'site_supervisor')
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header border-0 bg-white pb-3">
                        <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a;">
                            <i class="fa-solid fa-building me-2"></i>Assigned Sites
                        </h4>
                    </div>
                    <div class="card-body p-6">
                        <div class="text-center py-8">
                            <i class="fa-solid fa-inbox text-gray-400 fs-2x mb-3"></i>
                            <p class="text-gray-600 fw-semibold fs-5 mb-2">No Sites Assigned</p>
                            <p class="text-gray-500 fs-6 mb-0">This Site Supervisor has no assigned sites yet.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

