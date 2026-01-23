<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="fa-solid fa-tags position-absolute ms-4" style="color: #1e3a8a;"></i>
                <h2 class="text-gray-800 fw-bold mb-0 ms-10">View Category</h2>
            </div>
        </div>
        <div class="card-toolbar">
            <a href="{{ route('admin.categories.edit', $category->id) }}" class="btn btn-primary me-2" style="background: #1e3a8a; border: none;">
                <i class="fa-solid fa-pen me-2"></i>Edit
            </a>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-light">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>
    <div class="card-body pt-0">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-8">
                        <div class="mb-3" style="line-height: 2.5;">
                            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Name</span>
                            <span class="text-gray-600" style="margin: 0 8px;">:</span>
                            <span class="text-gray-800 fw-bold fs-6">{{ $category->name ?? 'N/A' }}</span>
                        </div>
                        <div class="mb-3" style="line-height: 2.5;">
                            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
                            <span class="text-gray-600" style="margin: 0 8px;">:</span>
                            <span class="ms-2">
                                @if($category->status)
                                    <span class="badge badge-light-success">Active</span>
                                @else
                                    <span class="badge badge-light-danger">Inactive</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

