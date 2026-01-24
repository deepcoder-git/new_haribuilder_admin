<div>
    <div class="card">
        <x-view-header 
            :moduleName="$moduleName ?? 'Material'" 
            :moduleIcon="$moduleIcon ?? 'box'" 
            :indexRoute="$indexRoute ?? 'admin.materials.index'"
            :editRoute="$editRoute ?? 'admin.materials.edit'"
            :editId="$editId ?? $material->id ?? null"
        />
        <div class="card-body pt-0">
@php
    $materialImages = [];
    foreach($material->productImages as $productImage) {
        $materialImages[] = $productImage->image_url;
    }
    if(empty($materialImages) && $material->image) {
        $materialImages[] = Storage::url($material->image);
    }
@endphp
            <div class="row">
                <div class="col-12">
                    <div class="card-body p-8">
                        <div class="row">
                            <div class="col-md-5 mb-5 mb-md-0 pe-md-5">
                                @if(!empty($materialImages))
                                    <div class="material-images-grid">
                                        @foreach($materialImages as $index => $imageUrl)
                                            <div class="material-view-image-wrapper">
                                                <img src="{{ $imageUrl }}" 
                                                     alt="Material image {{ $index + 1 }}" 
                                                     class="material-view-image-display material-image-zoom"
                                                     data-image-url="{{ $imageUrl }}"
                                                     data-material-name="{{ $material->material_name ?? 'Material Image' }}">
                                                <div class="text-muted small mt-1 text-center">Image {{ $index + 1 }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="material-image-placeholder-view">
                                        <i class="fa-solid fa-image text-gray-300 fs-1"></i>
                                        <div class="text-muted small mt-2">No image available</div>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-7 ps-md-5">
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Material Name</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $material->material_name ?? 'N/A' }}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Category</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $material->category->name ?? 'N/A' }}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Unit Type</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ ucfirst($material->unit_type ?? 'N/A') }}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Product</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="ms-2">
                                        @if($material->is_product)
                                            <span class="badge badge-light-success">Yes</span>
                                        @else
                                            <span class="badge badge-light-secondary">No</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Low Stock Threshold</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">{{ $material->low_stock_threshold ?? 'N/A' }}</span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Available Qty</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="text-gray-800 fw-bold fs-6">
                                        @if($material->store === \App\Utility\Enums\StoreEnum::LPO)
                                            0
                                        @else
                                            {{ $material->available_qty ?? 'N/A' }}
                                        @endif
                                    </span>
                                </div>
                                <div class="mb-3" style="line-height: 2.5;">
                                    <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
                                    <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                    <span class="ms-2">
                                        @if($material->status)
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
    </div>

    <div id="imageZoomModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2" style="padding: 1rem 1.25rem; background: #f8f9fa; border-bottom: 2px solid #1e3a8a; display: flex; align-items: center; justify-content: center; position: relative;">
                <h5 class="modal-title fw-bold text-center" id="imageZoomModalTitle" style="color: #1e3a8a; font-size: 1.125rem; margin: 0; flex: 1;">Material Image</h5>
                <button type="button" class="btn position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; color: #6b7280; transition: all 0.2s; cursor: pointer; flex-shrink: 0;" onmouseover="this.style.color='#ffffff'; this.style.background='#ef4444'; this.style.borderColor='#ef4444';" onmouseout="this.style.color='#6b7280'; this.style.background='#ffffff'; this.style.borderColor='#e5e7eb';">
                    <i class="fa-solid fa-xmark" style="font-size: 1rem; font-weight: bold;"></i>
                </button>
            </div>
            <div class="modal-body text-center p-3" style="max-height: 400px; overflow: auto; background: #ffffff;">
                <img id="zoomedImage" src="" alt="" style="max-width: 100%; max-height: 350px; width: auto; height: auto; object-fit: contain; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
            </div>
        </div>
    </div>
</div>

@push('header')
<style>
.material-images-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    max-width: 320px;
}

.material-view-image-wrapper {
    width: 100%;
}

.material-view-image-display {
    width: 100%;
    height: 140px;
    object-fit: contain;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    display: block;
    background-color: #ffffff;
}

.material-view-image-display:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.material-image-placeholder-view {
    width: 100%;
    height: 140px;
    border: 2px dashed #d1d5db;
    border-radius: 8px;
    background-color: #f9fafb;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .material-view-image-wrapper {
        width: 100%;
    }
}
</style>

@endpush

@push('footer')
<script>
(function() {
    let bound = false;
    function initImageZoom() {
        if (bound) return;
        bound = true;

        document.addEventListener('click', function(e) {
            const img = e.target.closest('.material-image-zoom');
            if (!img) return;

            e.preventDefault();
            const imageUrl = img.getAttribute('data-image-url');
            const materialName = img.getAttribute('data-material-name');

            if (!imageUrl || typeof bootstrap === 'undefined') return;

            const zoomed = document.getElementById('zoomedImage');
            const title = document.getElementById('imageZoomModalTitle');
            const modalEl = document.getElementById('imageZoomModal');

            if (zoomed) {
                zoomed.src = imageUrl;
                zoomed.alt = materialName || 'Material Image';
            }
            if (title) {
                title.textContent = materialName || 'Material Image';
            }
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImageZoom);
    } else {
        initImageZoom();
    }
})();
</script>
@endpush

