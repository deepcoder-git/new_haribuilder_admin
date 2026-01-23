<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Product' : 'Add Product' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <!-- First Row: Product Name and Category -->
            <div class="row g-3 mb-3" wire:key="first-row">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Product Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model.blur="product_name"
                           class="form-control form-control-solid @error('product_name') is-invalid @enderror"
                           placeholder="Enter product name"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('product_name')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Category <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        @php
                            $selectedCategoryName = 'Select Category';
                            if ($category_id) {
                                $category = \App\Models\Category::find($category_id);
                                $selectedCategoryName = $category ? $category->name : 'Select Category';
                            }
                            $hasCategoryError = $errors->has('category_id');
                        @endphp
                        <button type="button"
                                wire:click="toggleCategoryDropdown"
                                wire:loading.attr="disabled"
                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('category_id') is-invalid @enderror"
                                style="height: 44px; text-align: left; background: white; border: 1px solid {{ $hasCategoryError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                {{ $selectedCategoryName }}
                            </span>
                            <i class="fa-solid fa-chevron-{{ $categoryDropdownOpen ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        @if($categoryDropdownOpen)
                            <div class="position-absolute bg-white border rounded shadow-lg" 
                                 style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                                 wire:click.stop
                                 wire:key="category-dropdown"
                                 x-data="{ 
                                     isOpen: true,
                                     closeDropdown() {
                                         if (typeof $wire !== 'undefined') {
                                             $wire.call('closeCategoryDropdown');
                                         }
                                         this.isOpen = false;
                                     },
                                     handleScroll(event) {
                                         const el = event.target;
                                         if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                             if (typeof $wire !== 'undefined') {
                                                 $wire.call('loadMoreCategories');
                                             }
                                         }
                                     }
                                 }"
                                 x-show="isOpen"
                                 x-cloak
                                 x-on:click.outside="closeDropdown()">
                                <div style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                    <div class="position-relative">
                                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 0.875rem;"></i>
                                        <input type="text"
                                               wire:model="categorySearch"
                                               wire:keyup.debounce.300ms="handleCategorySearch($event.target.value)"
                                               wire:key="category-search"
                                               placeholder="Search categories..."
                                               class="form-control form-control-sm"
                                               style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                               autofocus
                                               wire:ignore.self>
                                    </div>
                                </div>
                                <div id="category-dropdown"
                                     style="overflow-y: auto; max-height: 250px; flex: 1;"
                                     x-on:scroll="handleScroll($event)">
                                    @if($categoryLoading && empty($categorySearchResults))
                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                            <div>Searching...</div>
                                        </div>
                                    @elseif(empty($categorySearchResults))
                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                            <div>No categories found</div>
                                        </div>
                                    @else
                                        @foreach($categorySearchResults as $result)
                                            <div wire:click="selectCategory({{ $result['id'] ?? 'null' }})"
                                                 class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                                 style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; {{ $category_id == $result['id'] ? 'background-color: #f0f9ff;' : '' }}"
                                                 onmouseover="this.style.backgroundColor='#f9fafb'"
                                                 onmouseout="this.style.backgroundColor='{{ $category_id == $result['id'] ? '#f0f9ff' : 'white' }}'">
                                                <div style="flex: 1; min-width: 0;">
                                                    <div class="fw-semibold text-truncate d-flex align-items-center gap-2" style="font-size: 0.875rem; color: #1f2937;">
                                                        @if($category_id == $result['id'])
                                                            <i class="fa-solid fa-check text-primary" style="font-size: 0.75rem;"></i>
                                                        @endif
                                                        <span>{{ $result['text'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                        @endforeach
                                        
                                        @if($categoryLoading && $categoryHasMore)
                                            <div class="text-center py-2"
                                                 style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                                <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                                Loading more...
                                            </div>
                                        @elseif($categoryHasMore)
                                            <div wire:click="loadMoreCategories"
                                                 class="text-center py-2"
                                                 style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #1e3a8a; cursor: pointer;"
                                                 onmouseover="this.style.backgroundColor='#f3f4f6'"
                                                 onmouseout="this.style.backgroundColor='#f9fafb'">
                                                <i class="fa-solid fa-chevron-down me-1"></i>
                                                Load More
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    @error('category_id')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            <!-- Second Row: Unit Type and Store -->
            <div class="row g-3 mb-3" wire:key="unit-store-row">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Unit Type <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        @php
                            $selectedUnitName = 'Select Unit Type';
                            if ($unit_type) {
                                $selectedUnitName = $unit_type;
                            }
                            $hasUnitError = $errors->has('unit_type');
                        @endphp
                        <button type="button"
                                wire:click="toggleUnitDropdown"
                                wire:loading.attr="disabled"
                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('unit_type') is-invalid @enderror"
                                style="height: 44px; text-align: left; background: white; border: 1px solid {{ $hasUnitError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                {{ $selectedUnitName }}
                            </span>
                            <i class="fa-solid fa-chevron-{{ $unitDropdownOpen ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        @if($unitDropdownOpen)
                            <div class="position-absolute bg-white border rounded shadow-lg" 
                                 style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                                 wire:click.stop
                                 wire:key="unit-dropdown"
                                 x-data="{ 
                                     isOpen: true,
                                     closeDropdown() {
                                         if (typeof $wire !== 'undefined') {
                                             $wire.call('closeUnitDropdown');
                                         }
                                         this.isOpen = false;
                                     },
                                     handleScroll(event) {
                                         const el = event.target;
                                         if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                             if (typeof $wire !== 'undefined') {
                                                 $wire.call('loadMoreUnits');
                                             }
                                         }
                                     }
                                 }"
                                 x-show="isOpen"
                                 x-cloak
                                 x-on:click.outside="closeDropdown()">
                                <div style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                    <div class="position-relative">
                                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 0.875rem;"></i>
                                        <input type="text"
                                               wire:model="unitSearch"
                                               wire:keyup.debounce.300ms="handleUnitSearch($event.target.value)"
                                               wire:key="unit-search"
                                               placeholder="Search units..."
                                               class="form-control form-control-sm"
                                               style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                               autofocus
                                               wire:ignore.self>
                                    </div>
                                </div>
                                <div id="unit-dropdown"
                                     style="overflow-y: auto; max-height: 250px; flex: 1;"
                                     x-on:scroll="handleScroll($event)">
                                    @if($unitLoading && empty($unitSearchResults))
                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                            <div>Searching...</div>
                                        </div>
                                    @elseif(empty($unitSearchResults))
                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                            <div>No units found</div>
                                        </div>
                                    @else
                                        @foreach($unitSearchResults as $result)
                                            <div wire:click="selectUnit('{{ $result['text'] }}')"
                                                 class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                                 style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; {{ $unit_type == $result['text'] ? 'background-color: #f0f9ff;' : '' }}"
                                                 onmouseover="this.style.backgroundColor='#f9fafb'"
                                                 onmouseout="this.style.backgroundColor='{{ $unit_type == $result['text'] ? '#f0f9ff' : 'white' }}'">
                                                <div style="flex: 1; min-width: 0;">
                                                    <div class="fw-semibold text-truncate d-flex align-items-center gap-2" style="font-size: 0.875rem; color: #1f2937;">
                                                        @if($unit_type == $result['text'])
                                                            <i class="fa-solid fa-check text-primary" style="font-size: 0.75rem;"></i>
                                                        @endif
                                                        <span>{{ $result['text'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                        @endforeach
                                        
                                        @if($unitLoading && $unitHasMore)
                                            <div class="text-center py-2"
                                                 style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                                <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                                Loading more...
                                            </div>
                                        @elseif($unitHasMore)
                                            <div wire:click="loadMoreUnits"
                                                 class="text-center py-2"
                                                 style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #1e3a8a; cursor: pointer;"
                                                 onmouseover="this.style.backgroundColor='#f3f4f6'"
                                                 onmouseout="this.style.backgroundColor='#f9fafb'">
                                                <i class="fa-solid fa-chevron-down me-1"></i>
                                                Load More
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    @error('unit_type')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Store <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        @php
                            $selectedStoreName = 'Select Store';
                            if ($store) {
                                $storeEnum = \App\Utility\Enums\StoreEnum::tryFrom($store);
                                $selectedStoreName = $storeEnum ? $storeEnum->getName() : 'Select Store';
                            }
                            $hasStoreError = $errors->has('store');
                        @endphp
                        <button type="button"
                                wire:click="toggleStoreDropdown"
                                wire:loading.attr="disabled"
                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('store') is-invalid @enderror"
                                style="height: 44px; text-align: left; background: white; border: 1px solid {{ $hasStoreError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                {{ $selectedStoreName }}
                            </span>
                            <i class="fa-solid fa-chevron-{{ $storeDropdownOpen ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        @if($storeDropdownOpen)
                            <div class="position-absolute bg-white border rounded shadow-lg" 
                                 style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                                 wire:click.stop
                                 wire:key="store-dropdown"
                                 x-data="{ 
                                     isOpen: true,
                                     closeDropdown() {
                                         if (typeof $wire !== 'undefined') {
                                             $wire.call('closeStoreDropdown');
                                         }
                                         this.isOpen = false;
                                     }
                                 }"
                                 x-show="isOpen"
                                 x-cloak
                                 x-on:click.outside="closeDropdown()">
                                <div style="overflow-y: auto; max-height: 250px; flex: 1;">
                        @foreach(\App\Utility\Enums\StoreEnum::cases() as $storeEnum)
                                        <div wire:click="selectStore('{{ $storeEnum->value }}')"
                                             class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                             style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; {{ $store == $storeEnum->value ? 'background-color: #f0f9ff;' : '' }}"
                                             onmouseover="this.style.backgroundColor='#f9fafb'"
                                             onmouseout="this.style.backgroundColor='{{ $store == $storeEnum->value ? '#f0f9ff' : 'white' }}'">
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="fw-semibold text-truncate d-flex align-items-center gap-2" style="font-size: 0.875rem; color: #1f2937;">
                                                    @if($store == $storeEnum->value)
                                                        <i class="fa-solid fa-check text-primary" style="font-size: 0.75rem;"></i>
                                                    @endif
                                                    <span>{{ $storeEnum->getName() }}</span>
                                                </div>
                                            </div>
                                        </div>
                        @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    @error('store')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            @if(!$isEditMode)
            <!-- Third Row: Available Quantity and Low Stock Threshold (Create Mode) -->
            @if($store !== \App\Utility\Enums\StoreEnum::LPO->value)
            <div class="row g-3 mb-3" wire:key="quantity-threshold-row-{{ $store ?? 'none' }}">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Available Quantity
                    </label>
                    <input type="number" 
                           wire:model.blur="available_qty"
                           step="1"
                           min="0"
                           class="form-control form-control-solid @error('available_qty') is-invalid @enderror"
                           placeholder="Enter quantity"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('available_qty')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Low Stock Threshold
                    </label>
                    <input type="number"
                           wire:model.blur="low_stock_threshold"
                           step="1"
                           min="0"
                           class="form-control form-control-solid @error('low_stock_threshold') is-invalid @enderror"
                           placeholder="Enter threshold"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('low_stock_threshold')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            @endif
            <!-- Fourth Row: Images (Create Mode - Full Width) -->
            <div class="row g-3 mb-3" wire:key="images-row-create">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Images <span class="text-danger">*</span>
                    </label>
                    <div class="d-flex gap-3 align-items-start flex-wrap">
                        <div class="product-image-upload-wrapper">
                            <label for="product-images" class="product-image-upload-label">
                                <input type="file" 
                                       id="product-images"
                                       wire:model="images"
                                       accept="image/*"
                                       multiple
                                       class="d-none @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"/>
                                <div class="d-flex flex-column align-items-center justify-content-center text-center p-1 h-100">
                                    <div class="mb-0 position-relative">
                                        <i class="fa-solid fa-cloud-arrow-up fs-5 text-gray-400"></i>
                                    </div>
                                    <div class="fw-semibold text-gray-600 mb-0" style="font-size: 0.65rem;">Upload</div>
                                    <div class="text-muted" style="font-size: 0.55rem;">PNG, JPG, GIF</div>
                                    <div class="text-muted" style="font-size: 0.55rem;">2MB max</div>
                                </div>
                            </label>
                        </div>

                        <div class="product-images-preview-wrapper d-flex flex-wrap gap-2">
                            @if(count($images) > 0)
                                @foreach($images as $index => $image)
                                    @if($image)
                                        <div class="product-image-preview">
                                            <img src="{{ $image->temporaryUrl() }}" 
                                                 alt="Preview {{ $index + 1 }}" 
                                                 class="product-image-preview-img">
                                            <button type="button" 
                                                    wire:click="removeImage({{ $index }})"
                                                    class="product-image-remove-btn">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    @endif
                                @endforeach
                            @endif

                            @if(count($images) === 0)
                                <div class="product-image-placeholder">
                                    <i class="fa-solid fa-image text-gray-300 fs-5"></i>
                                    <div class="text-muted mt-0" style="font-size: 0.6rem;">No images</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted mt-2 mb-0 small">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Recommended size: 500x500px. Max file size: 2MB per image. Multiple images allowed.
                    </p>
                    @error('images') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                    @error('images.*') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            @else
            <!-- Third Row: Low Stock Threshold and Images (Edit Mode) -->
            <div class="row g-3 mb-3" wire:key="threshold-images-row">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Low Stock Threshold
                    </label>
                    <input type="number"
                           wire:model.blur="low_stock_threshold"
                           step="1"
                           min="0"
                           class="form-control form-control-solid @error('low_stock_threshold') is-invalid @enderror"
                           placeholder="Enter threshold"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('low_stock_threshold')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Images
                    </label>
                    <div class="d-flex gap-3 align-items-start flex-wrap">
                        <div class="product-image-upload-wrapper">
                            <label for="product-images" class="product-image-upload-label">
                                <input type="file" 
                                       id="product-images"
                                       wire:model="images"
                                       accept="image/*"
                                       multiple
                                       class="d-none @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"/>
                                <div class="d-flex flex-column align-items-center justify-content-center text-center p-1 h-100">
                                    <div class="mb-0 position-relative">
                                        <i class="fa-solid fa-cloud-arrow-up fs-5 text-gray-400"></i>
                                    </div>
                                    <div class="fw-semibold text-gray-600 mb-0" style="font-size: 0.65rem;">Upload</div>
                                    <div class="text-muted" style="font-size: 0.55rem;">PNG, JPG, GIF</div>
                                    <div class="text-muted" style="font-size: 0.55rem;">2MB max</div>
                                </div>
                            </label>
                        </div>

                        <div class="product-images-preview-wrapper d-flex flex-wrap gap-2">
                            @if(count($images) > 0)
                                @foreach($images as $index => $image)
                                    @if($image)
                                        <div class="product-image-preview">
                                            <img src="{{ $image->temporaryUrl() }}" 
                                                 alt="Preview {{ $index + 1 }}" 
                                                 class="product-image-preview-img">
                                            <button type="button" 
                                                    wire:click="removeImage({{ $index }})"
                                                    class="product-image-remove-btn">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    @endif
                                @endforeach
                            @endif

                            @if(count($existingImages) > 0)
                                @foreach($existingImages as $existingImage)
                                    <div class="product-image-preview">
                                        <img src="{{ $existingImage['url'] }}" 
                                             alt="{{ $existingImage['name'] }}" 
                                             class="product-image-zoom product-image-preview-img"
                                             data-image-url="{{ $existingImage['url'] }}"
                                             data-product-name="{{ $product_name ?? 'Product Image' }}">
                                        <button type="button" 
                                                wire:click="removeExistingImage('{{ $existingImage['id'] }}')" 
                                                class="product-image-remove-btn">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @endif

                            @if(count($images) === 0 && count($existingImages) === 0)
                                <div class="product-image-placeholder">
                                    <i class="fa-solid fa-image text-gray-300 fs-5"></i>
                                    <div class="text-muted mt-0" style="font-size: 0.6rem;">No images</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted mt-2 mb-0 small">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Recommended size: 500x500px. Max file size: 2MB per image. Multiple images allowed.
                    </p>
                    @error('images') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                    @error('images.*') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            @endif
            
            <!-- Materials Section (Optional - quantity validation only applies when material is selected) -->
            <div class="row g-3 mb-3" wire:key="materials-section-{{ $store }}" style="overflow: visible;">
                <div class="col-md-12" style="overflow: visible;">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Materials
                    </label>
                    <div class="table-responsive @error('materials') border border-danger rounded @enderror" 
                         style="@error('materials') border-width: 2px !important; @enderror overflow-x: auto; overflow-y: visible !important; position: relative;">
                        <table class="table table-bordered table-hover" style="position: relative; margin-bottom: 0;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">Image</th>
                                    <th>Material</th>
                                    <th>Category</th>
                                    <th style="width: 180px;" class="text-center">Quantity</th>
                                    <th style="width: 120px;">Unit</th>
                                    <th style="width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($materials as $index => $material)
                                    <tr wire:key="material-row-{{ $index }}" style="position: relative; overflow: visible;">
                                        <td class="text-center">
                                            @php
                                                $materialModel = \App\Models\Material::find($material['material_id'] ?? null);
                                                $imageUrl = $materialModel && $materialModel->primary_image 
                                                    ? \Illuminate\Support\Facades\Storage::url($materialModel->primary_image)
                                                    : null;
                                            @endphp
                                            @if($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="{{ $material['material_name'] ?? 'Material' }}" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            @else
                                                <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid fa-image text-gray-400"></i>
                                                </div>
                                            @endif
                                        </td>
                                        <td style="padding: 1rem 0.75rem; min-width: 250px; position: relative;">
                                            <div class="position-relative" style="width: 100%;">
                                                @php
                                                    $currentMaterialId = isset($material['material_id']) ? (string)$material['material_id'] : '';
                                                    $selectedMaterialName = $material['material_name'] ?? 'Select Material';
                                                    $hasMaterialError = $errors->has('materials.' . $index . '.material_id');
                                                @endphp
                                                <button type="button"
                                                        wire:click="toggleMaterialDropdown({{ $index }})"
                                                        wire:loading.attr="disabled"
                                                        @if($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) disabled @endif
                                                        class="form-control form-control-sm d-flex align-items-center justify-content-between @error('materials.' . $index . '.material_id') is-invalid @enderror"
                                                        style="height: 38px; text-align: left; background: {{ ($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) ? '#f9fafb' : 'white' }}; border: 1px solid {{ $hasMaterialError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 0.75rem; min-width: 200px; width: 100%; {{ ($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) ? 'cursor: not-allowed;' : '' }}">
                                                    <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                                        {{ $selectedMaterialName }}
                                                    </span>
                                                    <i class="fa-solid fa-chevron-{{ isset($materialDropdownOpen[$index]) && $materialDropdownOpen[$index] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                                                </button>
                                                
                                                @php
                                                    $isDropdownOpen = isset($materialDropdownOpen[$index]) && $materialDropdownOpen[$index] === true && !($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value);
                                                @endphp
                                                @if($isDropdownOpen)
                                                    <div class="position-fixed bg-white border rounded shadow-lg material-dropdown-overlay" 
                                                         style="z-index: 999999; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; width: 300px; min-width: 300px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);"
                                                         wire:click.stop
                                                         x-data="{ 
                                                             index: {{ (int)($index ?? 0) }},
                                                             isOpen: true,
                                                             closeDropdown() {
                                                                 if (typeof $wire !== 'undefined') {
                                                                     $wire.call('closeMaterialDropdown', this.index);
                                                                 }
                                                                 this.isOpen = false;
                                                             },
                                                             handleScroll(event) {
                                                                 const el = event.target;
                                                                 if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                                                     if (typeof $wire !== 'undefined') {
                                                                         $wire.call('loadMoreMaterials', this.index);
                                                                     }
                                                                 }
                                                             },
                                                             updatePosition() {
                                                                 const button = $el.previousElementSibling;
                                                                 if (button) {
                                                                     const rect = button.getBoundingClientRect();
                                                                     const viewportHeight = window.innerHeight;
                                                                     const spaceBelow = viewportHeight - rect.bottom;
                                                                     
                                                                     // Position dropdown below button
                                                                     $el.style.left = rect.left + 'px';
                                                                     $el.style.top = (rect.bottom + 4) + 'px';
                                                                     $el.style.width = rect.width + 'px';
                                                                     
                                                                     // Adjust max-height based on available space
                                                                     if (spaceBelow < 300) {
                                                                         $el.style.maxHeight = Math.max(150, spaceBelow - 20) + 'px';
                                                                     } else {
                                                                         $el.style.maxHeight = '300px';
                                                                     }
                                                                 }
                                                             }
                                                         }"
                                                         x-show="isOpen"
                                                         x-cloak
                                                         x-on:click.outside="closeDropdown()"
                                                         x-init="updatePosition(); $watch('isOpen', value => { if (value) { setTimeout(() => updatePosition(), 50); } }); window.addEventListener('scroll', () => { if (isOpen) updatePosition(); }, true); window.addEventListener('resize', () => { if (isOpen) updatePosition(); });">
                                                        <div style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                                            <div class="position-relative">
                                                                <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 0.875rem;"></i>
                                                                <input type="text"
                                                                       wire:model="materialSearch.{{ $index }}"
                                                                       wire:keyup.debounce.300ms="handleMaterialSearch($event.target.value, 'materialSearch.{{ $index }}')"
                                                                       wire:key="material-search-{{ $index }}"
                                                                       placeholder="Q Search materials..."
                                                                       class="form-control form-control-solid"
                                                                       style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                                                       autofocus
                                                                       wire:ignore.self>
                                                            </div>
                                                        </div>
                                                        <div id="material-dropdown-{{ $index }}"
                                                             style="overflow-y: auto; max-height: 250px; flex: 1;"
                                                             x-on:scroll="handleScroll($event)">
                                                            @if(isset($materialLoading[$index]) && $materialLoading[$index] && empty($materialSearchResults[$index] ?? []))
                                                                <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                    <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                    <div>Searching...</div>
                                                                </div>
                                                            @elseif(empty($materialSearchResults[$index] ?? []))
                                                                <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                    <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                    <div>No materials found</div>
                                                                </div>
                                                            @else
                                                                @foreach($materialSearchResults[$index] ?? [] as $result)
                                                                    <div wire:click="selectMaterial({{ $index }}, {{ $result['id'] ?? 'null' }})"
                                                                         class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                                                         style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer;"
                                                                         onmouseover="this.style.backgroundColor='#f9fafb'"
                                                                         onmouseout="this.style.backgroundColor='white'">
                                                                        @if(!empty($result['image_url']))
                                                                            <img src="{{ $result['image_url'] }}" 
                                                                                 alt="{{ $result['text'] }}"
                                                                                 style="width: 32px; height: 32px; object-fit: cover; border-radius: 0.25rem; flex-shrink: 0;">
                                                                        @else
                                                                            <div class="d-flex align-items-center justify-content-center bg-light" 
                                                                                 style="width: 32px; height: 32px; border-radius: 0.25rem; flex-shrink: 0;">
                                                                                <i class="fa-solid fa-image text-muted" style="font-size: 0.875rem;"></i>
                                                                            </div>
                                                                        @endif
                                                                        <div style="flex: 1; min-width: 0;">
                                                                            <div class="fw-semibold text-truncate" style="font-size: 0.875rem; color: #1f2937;">
                                                                                {{ $result['text'] }}
                                                                            </div>
                                                                            @if(!empty($result['category_name']))
                                                                                <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                                                                    {{ $result['category_name'] }}
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            
                                                                @if(isset($materialLoading[$index]) && $materialLoading[$index] && !empty($materialHasMore[$index] ?? false))
                                                                    <div class="text-center py-2"
                                                                         style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                                                        <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                                                        Loading more...
                                                                    </div>
                                                                @elseif(!empty($materialHasMore[$index] ?? false))
                                                                    <div wire:click="loadMoreMaterials({{ $index }})"
                                                                         class="text-center py-2"
                                                                         style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #1e3a8a; cursor: pointer;"
                                                                         onmouseover="this.style.backgroundColor='#f3f4f6'"
                                                                         onmouseout="this.style.backgroundColor='#f9fafb'">
                                                                        <i class="fa-solid fa-chevron-down me-1"></i>
                                                                        Load More
                                                                    </div>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            @error('materials.' . $index . '.material_id')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <div class="form-control form-control-sm" style="background: #f9fafb; border: 1px solid #e5e7eb;">
                                                {{ $material['category_name'] ?? '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <button type="button" 
                                                        wire:click="decrementQuantity({{ $index }})"
                                                        @if($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) disabled @endif
                                                        class="btn btn-sm btn-outline-secondary qty-btn"
                                                        style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 6px; {{ ($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) ? 'opacity: 0.5; cursor: not-allowed;' : '' }}">
                                                    <i class="fa-solid fa-minus" style="font-size: 0.7rem;"></i>
                                                </button>
                                                <input type="number" 
                                                       wire:model.blur="materials.{{ $index }}.quantity"
                                                       step="1"
                                                       min="0"
                                                       @if($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) readonly @endif
                                                       class="form-control form-control-sm text-center @error('materials.' . $index . '.quantity') is-invalid @enderror"
                                                       placeholder="0"
                                                       style="width: 70px; {{ ($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) ? 'background-color: #f9fafb; cursor: not-allowed;' : '' }}">
                                                <button type="button" 
                                                        wire:click="incrementQuantity({{ $index }})"
                                                        @if($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) disabled @endif
                                                        class="btn btn-sm btn-outline-primary qty-btn"
                                                        style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 6px; {{ ($isEditMode && $type !== \App\Utility\Enums\ProductTypeEnum::Product->value) ? 'opacity: 0.5; cursor: not-allowed;' : '' }}">
                                                    <i class="fa-solid fa-plus" style="font-size: 0.7rem;"></i>
                                                </button>
                                            </div>
                                            @error('materials.' . $index . '.quantity')
                                                <div class="text-danger small mt-1 text-center">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <div class="form-control form-control-sm" style="background: #f9fafb; border: 1px solid #e5e7eb;">
                                                {{ $material['unit_type'] ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @if(!$isEditMode || ($isEditMode && $type === \App\Utility\Enums\ProductTypeEnum::Product->value))
                                                <button type="button" 
                                                        wire:click="removeMaterial({{ $index }})"
                                                        class="btn btn-sm btn-danger"
                                                        style="width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            @else
                                                <span class="text-muted" style="font-size: 0.875rem;">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No materials added. Click "Add Material" to add one.
                                        </td>
                                    </tr>
                                @endforelse
                                <tr>
                                    <td colspan="6" class="text-center">
                                        @php
                                            $currentMaterialCount = count($materials);
                                            $canAddMore = true;
                                            $maxMaterials = 999; // Large number for Workshop store
                                            
                                            if ($store === \App\Utility\Enums\StoreEnum::HardwareStore->value || 
                                                $store === \App\Utility\Enums\StoreEnum::LPO->value) {
                                                $maxMaterials = 1;
                                                $canAddMore = $currentMaterialCount < $maxMaterials;
                                            }
                                        @endphp
                                        @if(!$isEditMode || ($isEditMode && $type === \App\Utility\Enums\ProductTypeEnum::Product->value))
                                            <button type="button" 
                                                    wire:click="addMaterial"
                                                    class="btn btn-sm btn-primary"
                                                    style="background: #1e3a8a; border: none; @if(!$canAddMore) opacity: 0.6; cursor: not-allowed; @endif"
                                                    @if(!$canAddMore) disabled @endif
                                                    wire:loading.attr="disabled">
                                                <i class="fa-solid fa-plus me-1"></i> Add Material
                                            </button>
                                        @endif
                                        @if(!$canAddMore && $store)
                                            <div class="text-muted small mt-2">
                                                @if($store === \App\Utility\Enums\StoreEnum::HardwareStore->value)
                                                    Only one material is allowed for Hardware Store.
                                                @elseif($store === \App\Utility\Enums\StoreEnum::LPO->value)
                                                    Only one material is allowed for LPO Store.
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @error('materials')
                        <div class="text-danger small mt-2 d-flex align-items-center">
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
                        {{ $isEditMode ? 'Update' : 'Add Product' }}
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
        .product-image-upload-wrapper {
            flex: 0 0 auto;
        }
        .product-image-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 90px;
            height: 90px;
            border: 2px dashed #d1d5db;
            border-radius: 6px;
            background-color: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .product-image-upload-label:hover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .product-images-preview-wrapper {
            flex: 1;
            min-width: 0;
        }
        .product-image-preview {
            position: relative;
            width: 80px;
            height: 80px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background-color: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            line-height: 0;
        }
        .product-image-preview-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 6px;
            display: block;
        }
        .product-image-remove-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 20px;
            height: 20px;
            background-color: rgba(255, 255, 255, 0.95);
            border: 1px solid #e5e7eb;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        .product-image-remove-btn:hover {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
        }
        .product-image-remove-btn i {
            font-size: 0.6rem;
            color: #6b7280;
        }
        .product-image-remove-btn:hover i {
            color: #ef4444;
        }
        .product-image-placeholder {
            width: 80px;
            height: 80px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background-color: #f9fafb;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        /* Quantity buttons */
        .qty-btn {
            transition: all 0.2s ease;
        }
        .qty-btn:hover {
            transform: scale(1.05);
        }
        .btn-outline-secondary.qty-btn:hover {
            background: #6b7280;
            border-color: #6b7280;
            color: white;
        }
        .btn-outline-primary.qty-btn {
            border-color: #1e3a8a;
            color: #1e3a8a;
        }
        .btn-outline-primary.qty-btn:hover {
            background: #1e3a8a;
            border-color: #1e3a8a;
            color: white;
        }
        /* Hide number input spinner */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }
        /* Fix dropdown display in table */
        .table-responsive {
            overflow-x: auto;
            overflow-y: visible !important;
            position: relative;
        }
        .table-responsive .table {
            margin-bottom: 0;
            position: relative;
        }
        .table tbody {
            position: relative;
        }
        .table tbody tr {
            position: relative;
            overflow: visible !important;
        }
        .table td {
            position: relative;
            overflow: visible !important;
        }
        .table td .position-relative {
            overflow: visible;
            position: relative;
        }
        /* Ensure dropdown appears properly without being cut */
        .table td .position-absolute {
            position: absolute !important;
        }
        /* Material dropdown specific styling */
        .material-dropdown {
            position: absolute !important;
            z-index: 99999 !important;
        }
        /* Material dropdown content scrollbar styling */
        .material-dropdown-content {
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        .material-dropdown-content::-webkit-scrollbar {
            width: 8px;
        }
        .material-dropdown-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .material-dropdown-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        .material-dropdown-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        /* Ensure card body allows dropdown overflow */
        .card-body {
            overflow: visible !important;
        }
        /* Ensure all parent containers allow overflow */
        .card {
            overflow: visible !important;
        }
        /* Fix table container overflow */
        .table-responsive {
            overflow-x: auto !important;
            overflow-y: visible !important;
        }
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem !important;
            }
            .product-image-upload-label {
                width: 80px;
                height: 80px;
            }
            .product-image-preview {
                width: 70px;
                height: 70px;
            }
            .product-image-preview-img {
                width: 70px;
                height: 70px;
            }
            .product-image-placeholder {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</div>