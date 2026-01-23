@props([
    'moduleName' => 'Item',
    'moduleIcon' => 'box',
    'indexRoute' => '#',
    'editRoute' => null,
    'editId' => null,
])

<div class="card-header border-0 pt-2 pb-2">
    <div class="card-title">
        <div class="d-flex align-items-center position-relative my-0">
            <i class="fa-solid fa-{{ $moduleIcon }} position-absolute ms-4" style="color: #1e3a8a; font-size: 1rem;"></i>
            <h2 class="text-gray-800 fw-bold mb-0 ms-10" style="font-size: 1.25rem;">View {{ $moduleName }}</h2>
        </div>
    </div>
    <div class="card-toolbar">
        @if($editRoute && $editId)
            <a href="{{ route($editRoute, $editId) }}" class="btn btn-primary btn-sm me-2" style="background: #1e3a8a; border: none;">
                <i class="fa-solid fa-pen me-2"></i>Edit
            </a>
        @endif
        <a href="{{ route($indexRoute) }}" class="btn btn-light btn-sm">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to List
        </a>
    </div>
</div>
