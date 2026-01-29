<div>
    <div class="card">
        <x-view-header 
            :moduleName="$moduleName ?? 'Product'" 
            :moduleIcon="$moduleIcon ?? 'box'" 
            :indexRoute="$indexRoute ?? 'admin.products.index'"
            :editRoute="$editRoute ?? 'admin.products.edit'"
            :editId="$editId ?? $product->id ?? null"
        />
        <div class="card-body pt-0">
            <div class="row">
                <div class="col-12">
                        <div class="card-body p-8">
                            <div class="row">
                                <div class="col-md-5 mb-5 mb-md-0 pe-md-5">
                                    <label class="text-gray-600 fw-semibold fs-6 mb-3 d-block">Images</label>
                                    @php
                                        use Illuminate\Support\Facades\Storage;

                                        $productImages = [];
                                        foreach ($product->productImages as $productImage) {
                                            $productImages[] = $productImage->image_url;
                                        }
                                        // Fallback to legacy products.image (supports string/JSON via image_paths accessor)
                                        if (empty($productImages)) {
                                            foreach (($product->image_paths ?? []) as $path) {
                                                if (!empty($path)) {
                                                    $productImages[] = Storage::url($path);
                                                }
                                            }
                                        }
                                    @endphp
                                    @if(!empty($productImages))
                                        <div class="product-main-image-container mb-3">
                                            <img src="{{ $productImages[0] }}" 
                                                 alt="{{ $product->product_name ?? 'Product Image' }}" 
                                                 class="product-main-image product-image-zoom"
                                                 data-image-url="{{ $productImages[0] }}"
                                                 data-product-name="{{ $product->product_name ?? 'Product Image' }}">
                                        </div>
                                        
                                        @if(count($productImages) > 1)
                                            <div class="product-thumbnails-grid">
                                                @foreach($productImages as $index => $imgUrl)
                                                    <div class="product-thumbnail-item {{ $index === 0 ? 'active' : '' }}" 
                                                         data-image-url="{{ $imgUrl }}"
                                                         data-product-name="{{ htmlspecialchars($product->product_name ?? 'Product Image', ENT_QUOTES) }}">
                                                        <img src="{{ $imgUrl }}" 
                                                             alt="Product image {{ $index + 1 }}" 
                                                             class="product-thumbnail">
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        
                                        <small class="text-muted d-block mt-2">
                                            <i class="fa-solid fa-image me-1"></i>{{ count($productImages) }} image(s)
                                        </small>
                                    @else
                                        <div class="product-image-placeholder-view">
                                            <i class="fa-solid fa-image text-gray-300 fs-1"></i>
                                            <div class="text-muted small mt-2">No images available</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-7 ps-md-5">
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Product Name</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="text-gray-800 fw-bold fs-6">{{ $product->product_name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Category</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="text-gray-800 fw-bold fs-6">{{ $product->category->name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Type</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="text-gray-800 fw-bold fs-6">{{ $product->type ? $product->type->getName() : 'N/A' }}</span>
                                    </div>
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Unit Type</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="text-gray-800 fw-bold fs-6">{{ ucfirst($product->unit_type ?? 'N/A') }}</span>
                                    </div>
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="ms-2">
                                            @if($product->status)
                                                <span class="badge badge-light-success">Active</span>
                                            @else
                                                <span class="badge badge-light-danger">Inactive</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Low Stock Threshold</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="text-gray-800 fw-bold fs-6">{{ $product->low_stock_threshold !== null ? formatQty($product->low_stock_threshold) : 'N/A' }}</span>
                                    </div>
                                    <div class="mb-3" style="line-height: 2.5;">
                                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Available Qty</span>
                                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                                        <span class="text-gray-800 fw-bold fs-6">
                                            @if($product->store === \App\Utility\Enums\StoreEnum::LPO)
                                                0
                                            @else
                                                {{ $product->available_qty !== null ? formatQty($product->available_qty) : 'N/A' }}
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

    @if($product->store === \App\Utility\Enums\StoreEnum::WarehouseStore && $product->materials && $product->materials->count() > 0)
    <div class="card mt-4">
        <div class="card-header border-0 pt-6">
            <h3 class="card-title align-items-start flex-column">
                <span class="card-label fw-bold fs-3 mb-1">Raw Materials</span>
                <span class="text-muted mt-1 fw-semibold fs-7">Materials used in this warehouse product</span>
            </h3>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;" class="text-center">Image</th>
                            <th>Material Name</th>
                            <th>Category</th>
                            <th style="width: 150px;" class="text-center">Quantity</th>
                            <th style="width: 120px;" class="text-center">Unit Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->materials as $material)
                            <tr>
                                <td class="text-center">
                                    @php
                                        // Use accessor from Product/Material model to resolve primary image URL
                                        $materialImage = $material->primary_image_url ?? null;
                                    @endphp
                                    @if($materialImage)
                                        <img src="{{ $materialImage }}"
                                             alt="{{ $material->product_name ?? 'Material Image' }}"
                                             class="rounded"
                                             style="width: 60px; height: 60px; object-fit: cover; border: 1px solid #e5e7eb;">
                                    @else
                                        <div class="d-flex align-items-center justify-content-center rounded bg-light"
                                             style="width: 60px; height: 60px; border: 1px solid #e5e7eb;">
                                            <i class="fa-solid fa-image text-muted"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-gray-800 fw-semibold">{{ $material->product_name ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <span class="text-gray-600">{{ $material->category->name ?? 'N/A' }}</span>
                                </td>
                                <td class="text-center">
                                    @php
                                        $qtyRaw = $material->pivot->quantity ?? null;
                                        $qtyDisplay = $qtyRaw !== null && $qtyRaw !== '' ? formatQty($qtyRaw) : 'N/A';
                                    @endphp
                                    <span class="text-gray-800 fw-bold">{{ $qtyDisplay }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light-primary">{{ $material->pivot->unit_type ?? 'N/A' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div id="imageZoomModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
            <div class="modal-content">
                <div class="modal-header border-0 pb-2" style="padding: 1rem 1.25rem; background: #f8f9fa; border-bottom: 2px solid #1e3a8a; display: flex; align-items: center; justify-content: center; position: relative;">
                    <h5 class="modal-title fw-bold text-center" id="imageZoomModalTitle" style="color: #1e3a8a; font-size: 1.125rem; margin: 0; flex: 1;">Product Image</h5>
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
</div>

@push('header')
<style>
/* Main Large Image Container */
.product-main-image-container {
    width: 100%;
    max-width: 100%;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
    background-color: #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-main-image {
    width: 100%;
    max-width: 100%;
    height: auto;
    max-height: 400px;
    object-fit: contain;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.3s ease;
    display: block;
}

.product-main-image:hover {
    transform: scale(1.02);
}

/* Thumbnail Grid */
.product-thumbnails-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 10px;
    max-width: 100%;
    margin-top: 12px;
}

.product-thumbnail-item {
    position: relative;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #ffffff;
    padding: 4px;
}

.product-thumbnail-item:hover {
    border-color: #1e3a8a;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.product-thumbnail-item.active {
    border-color: #1e3a8a;
    border-width: 3px;
    box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.2);
}

.product-thumbnail {
    width: 100%;
    height: 80px;
    object-fit: contain;
    border-radius: 4px;
    display: block;
}

.product-image-placeholder-view {
    width: 100%;
    min-height: 300px;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    background-color: #f9fafb;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

@media (max-width: 768px) {
    .product-main-image-container {
        min-height: 250px;
    }
    
    .product-main-image {
        max-height: 300px;
    }
    
    .product-thumbnails-grid {
        grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
        gap: 8px;
    }
    
    .product-thumbnail {
        height: 60px;
    }
    
    .product-image-placeholder-view {
        min-height: 250px;
    }
}
</style>

@endpush

@push('footer')
<script>
(function() {
    function initImageZoom() {
        if (window.__productViewImageInit) return;
        window.__productViewImageInit = true;

        // Event delegation: stable on refresh and avoids duplicate listeners
        document.addEventListener('click', function(e) {
            const thumb = e.target.closest('.product-thumbnail-item');
            if (thumb) {
                e.preventDefault();
                e.stopPropagation();

                const imageUrl = thumb.getAttribute('data-image-url');
                const productName = thumb.getAttribute('data-product-name');

                if (imageUrl) {
                    const mainImage = document.querySelector('.product-main-image');
                    if (mainImage) {
                        mainImage.src = imageUrl;
                        mainImage.setAttribute('data-image-url', imageUrl);
                        mainImage.setAttribute('data-product-name', productName);
                        mainImage.alt = productName || 'Product Image';
                    }

                    document.querySelectorAll('.product-thumbnail-item').forEach(item => item.classList.remove('active'));
                    thumb.classList.add('active');
                }
                return;
            }

            const img = e.target.closest('.product-image-zoom');
            if (!img) return;

            e.preventDefault();
            const imageUrl = img.getAttribute('data-image-url');
            const productName = img.getAttribute('data-product-name');

            if (!imageUrl || typeof bootstrap === 'undefined') return;

            const zoomed = document.getElementById('zoomedImage');
            const title = document.getElementById('imageZoomModalTitle');
            const modalEl = document.getElementById('imageZoomModal');

            if (zoomed) {
                zoomed.src = imageUrl;
                zoomed.alt = productName || 'Product Image';
            }
            if (title) {
                title.textContent = productName || 'Product Image';
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

