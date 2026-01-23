@php
    use Illuminate\Support\Facades\Storage;
@endphp

<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Material' : 'Add Material' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Material Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model.blur="material_name"
                           class="form-control form-control-solid @error('material_name') is-invalid @enderror"
                           placeholder="Enter material name"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('material_name') 
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
            <div class="row g-3 mb-3">
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
                <div x-data="{}"
                     x-show="!$wire.isEditMode && (!$wire.store || $wire.store !== '{{ \App\Utility\Enums\StoreEnum::LPO->value }}')"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform -translate-x-4"
                     x-transition:enter-end="opacity-100 transform translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-x-0"
                     x-transition:leave-end="opacity-0 transform -translate-x-4"
                     class="col-md-6">
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
            </div>
            <div class="row g-3 mb-3">
                <div x-data="{}"
                     x-show="!$wire.store || $wire.store !== '{{ \App\Utility\Enums\StoreEnum::LPO->value }}'"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform -translate-x-4"
                     x-transition:enter-end="opacity-100 transform translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-x-0"
                     x-transition:leave-end="opacity-0 transform -translate-x-4"
                     class="col-md-6">
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
                        Material Management
                    </label>

                    @php
                        $materialTypeLabel = match((int) $material_type) {
                            0 => 'Material Only',
                            1 => 'Material As Product',
                            2 => 'Material + Product',
                            default => 'Material Only',
                        };
                    @endphp

                    @if($isEditMode)
                        {{-- Keep value for Livewire/save, but show as non-editable grey field in edit mode --}}
                        <input type="hidden" wire:model="material_type" />
                        <input type="text"
                               class="form-control form-control-solid"
                               value="{{ $materialTypeLabel }}"
                               disabled
                               style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb; background-color: #f3f4f6; cursor: not-allowed;"/>
                    @else
                        <select id="material_type"
                                wire:model.live="material_type"
                                class="form-select form-select-solid select2-field @error('material_type') is-invalid @enderror"
                                data-select2-field="material_type"
                                style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                            <option value="0">Material Only</option>
                            <option value="1">Material As Product</option>
                            <option value="2">Material + Product</option>
                        </select>
                    @endif

                    @error('material_type')
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            <div x-data="{ materialType: @entangle('material_type') }"
                 x-show="materialType == 1 || materialType == 2"
                 x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform -translate-y-2"
                 style="display: none;">
                <div class="row g-3 mb-3">
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
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Image @if(!$isEditMode)<span class="text-danger">*</span>@endif
                    </label>
                    <div class="d-flex gap-4 align-items-start flex-wrap">
                        <div class="material-image-upload-wrapper">
                            <label for="image" class="material-image-upload-label">
                                <input type="file" 
                                       id="image"
                                       wire:model="image"
                                       accept="image/*"
                                       class="d-none @error('image') is-invalid @enderror"/>
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

                        <div class="material-images-preview-wrapper d-flex flex-wrap gap-3">
                            @if($image)
                                <div class="material-image-preview">
                                    <img src="{{ $image->temporaryUrl() }}" 
                                         alt="Preview" 
                                         class="material-image-preview-img">
                                    <button type="button" 
                                            wire:click="removeImage"
                                            class="material-image-remove-btn">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            @elseif($isEditMode && !empty($this->existingImages))
                                @php
                                    $existingImage = $this->existingImages[0] ?? null;
                                @endphp
                                @if($existingImage)
                                    <div class="material-image-preview">
                                        <img src="{{ $existingImage['url'] }}" 
                                             alt="{{ $existingImage['name'] }}" 
                                             class="material-image-zoom material-image-preview-img"
                                             data-image-url="{{ $existingImage['url'] }}"
                                             data-material-name="{{ $material_name ?? 'Material Image' }}">
                                        <button type="button" 
                                                wire:click="removeExistingImage('{{ $existingImage['id'] }}')"
                                                wire:confirm="Are you sure you want to remove this image?"
                                                class="material-image-remove-btn">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                @endif
                            @else
                                <div class="material-image-placeholder">
                                    <i class="fa-solid fa-image text-gray-300 fs-1"></i>
                                    <div class="text-muted small mt-2">No image selected</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-muted mt-2 mb-0 small">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Recommended size: 500x500px. Max file size: 2MB.
                    </p>
                    @error('image') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>
            
            <!-- Materials Section - Show when Material As Product (1) or Material + Product (2) is selected -->
            @if(false)
            {{-- Materials section hidden in all cases --}}
            <div class="row g-3 mb-3" wire:key="materials-section-{{ $material_type }}" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Materials <span class="text-danger">*</span>
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
                                    <tr wire:key="material-row-{{ $index }}">
                                        <td class="text-center">
                                            @php
                                                $materialModel = \App\Models\Material::with('productImages')->find($material['material_id'] ?? null);
                                                $imageUrl = $materialModel ? $materialModel->primary_image_url : null;
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
                                                        @if($store !== 'hardware_store') disabled @endif
                                                        class="form-control form-control-sm d-flex align-items-center justify-content-between @error('materials.' . $index . '.material_id') is-invalid @enderror"
                                                        style="height: 38px; text-align: left; background: {{ $store === 'hardware_store' ? 'white' : '#f3f4f6' }}; border: 1px solid {{ $hasMaterialError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 0.75rem; min-width: 200px; width: 100%; cursor: {{ $store === 'hardware_store' ? 'pointer' : 'not-allowed' }};"
                                                        @if($store !== 'hardware_store') title="Please select Hardware Store to add materials" @endif>
                                                    <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis; color: {{ $store === 'hardware_store' ? '#1f2937' : '#9ca3af' }};">
                                                        {{ $store === 'hardware_store' ? $selectedMaterialName : 'Select Hardware Store first' }}
                                                    </span>
                                                    <i class="fa-solid fa-chevron-{{ isset($materialDropdownOpen[$index]) && $materialDropdownOpen[$index] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: {{ $store === 'hardware_store' ? '#6b7280' : '#9ca3af' }};"></i>
                                                </button>
                                                
                                                @php
                                                    $isDropdownOpen = isset($materialDropdownOpen[$index]) && $materialDropdownOpen[$index] === true;
                                                @endphp
                                                @if($isDropdownOpen && $store === 'hardware_store')
                                                    <div class="position-absolute bg-white border rounded shadow-lg" 
                                                         style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                                                         wire:click.stop
                                                         x-data="{ 
                                                             index: {{ $index ?? 0 }},
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
                                                             }
                                                         }"
                                                         x-show="isOpen"
                                                         x-cloak
                                                         x-on:click.outside="closeDropdown()">
                                                        <div style="padding: 0.75rem; border-bottom: 1px solid #e5e7eb; background: #f9fafb; flex-shrink: 0;">
                                                            <div class="position-relative">
                                                                <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 0.75rem; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 0.875rem; z-index: 1;"></i>
                                                                <input type="text"
                                                                       wire:model="materialSearch.{{ $index }}"
                                                                       wire:keyup.debounce.300ms="handleMaterialSearch($event.target.value, 'materialSearch.{{ $index }}')"
                                                                       wire:key="material-search-{{ $index }}"
                                                                       placeholder="Search materials..."
                                                                       class="form-control form-control-sm"
                                                                       style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem; background: white;"
                                                                       autofocus
                                                                       wire:ignore.self>
                                                            </div>
                                                        </div>
                                                        <div id="material-dropdown-{{ $index }}"
                                                             style="overflow-y: auto; overflow-x: hidden; max-height: 250px; flex: 1 1 auto; min-height: 0;"
                                                             x-on:scroll="handleScroll($event)"
                                                             class="material-dropdown-content">
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
                                                                         class="d-flex align-items-center gap-2 px-3 py-2"
                                                                         style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; min-height: 48px; {{ $currentMaterialId == $result['id'] ? 'background-color: #f0f9ff;' : 'background-color: white;' }}"
                                                                         onmouseover="this.style.backgroundColor='#f9fafb'"
                                                                         onmouseout="this.style.backgroundColor='{{ $currentMaterialId == $result['id'] ? '#f0f9ff' : 'white' }}'">
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
                                                        class="btn btn-sm btn-outline-secondary qty-btn"
                                                        style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                                                    <i class="fa-solid fa-minus" style="font-size: 0.7rem;"></i>
                                                </button>
                                                <input type="number" 
                                                       wire:model.blur="materials.{{ $index }}.quantity"
                                                       step="1"
                                                       min="0"
                                                       class="form-control form-control-sm text-center @error('materials.' . $index . '.quantity') is-invalid @enderror"
                                                       placeholder="0"
                                                       style="width: 70px;">
                                                <button type="button" 
                                                        wire:click="incrementQuantity({{ $index }})"
                                                        class="btn btn-sm btn-outline-primary qty-btn"
                                                        style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
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
                                            <button type="button" 
                                                    wire:click="removeMaterial({{ $index }})"
                                                    class="btn btn-sm btn-danger"
                                                    style="width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
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
                                        <button type="button" 
                                                wire:click="addMaterial"
                                                @if($store !== 'hardware_store') disabled @endif
                                                class="btn btn-sm btn-primary"
                                                style="background: {{ $store === 'hardware_store' ? '#1e3a8a' : '#9ca3af' }}; border: none; cursor: {{ $store === 'hardware_store' ? 'pointer' : 'not-allowed' }};"
                                                @if($store !== 'hardware_store') title="Please select Hardware Store to add materials" @endif>
                                            <i class="fa-solid fa-plus me-1"></i> Add Material
                                        </button>
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
                        {{ $isEditMode ? 'Update' : 'Add Material' }}
                    </span>
                </button>
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

    <style>
        [x-cloak] {
            display: none !important;
        }
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
        .material-image-upload-wrapper {
            flex: 0 0 auto;
        }
        .material-image-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 180px;
            height: 180px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background-color: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .material-image-upload-label:hover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .material-image-upload-label.drag-over {
            border-color: #3b82f6;
            background-color: #eff6ff;
            border-width: 3px;
        }
        .material-image-preview-wrapper {
            flex: 0 0 auto;
        }
        .material-images-preview-wrapper {
            flex: 1;
            min-width: 0;
        }
        .material-image-preview {
            position: relative;
            width: 180px;
            height: 180px;
            display: inline-block;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            line-height: 0;
        }
        .material-image-preview-img {
            width: 180px;
            height: 180px;
            object-fit: contain;
            border-radius: 8px;
            display: block;
        }
        .material-image-remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 32px;
            height: 32px;
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
        .material-image-remove-btn:hover {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
        }
        .material-image-remove-btn i {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .material-image-remove-btn:hover i {
            color: #ef4444;
        }
        .material-image-placeholder {
            height: 180px;
            width: 180px;
            min-width: 140px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #f9fafb;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .material-image-zoom {
            cursor: pointer;
        }
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
            .material-image-upload-label {
                width: 100%;
                max-width: 200px;
            }
            .material-image-preview {
                width: 100%;
                max-width: 100%;
                min-width: auto;
                height: 180px;
            }
            .material-image-preview-img {
                height: 180px;
                max-width: 100%;
                min-width: 120px;
            }
            .material-image-placeholder {
                width: 100%;
                max-width: 200px;
                height: 180px;
            }
        }
    </style>
</div>

@push('footer')
<script>
(function() {
    function initImageDragDrop() {
        const imageInput = document.getElementById('image');
        if (!imageInput) return;

        const uploadLabel = document.querySelector('.material-image-upload-label');
        if (!uploadLabel) return;

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadLabel.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadLabel.addEventListener(eventName, function() {
                uploadLabel.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadLabel.addEventListener(eventName, function() {
                uploadLabel.classList.remove('drag-over');
            }, false);
        });

        uploadLabel.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                const imageFile = Array.from(files).find(file => file.type.startsWith('image/'));
                if (imageFile) {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(imageFile);
                    imageInput.files = dataTransfer.files;
                    
                    const event = new Event('change', { bubbles: true });
                    imageInput.dispatchEvent(event);
                } else {
                    alert('Please drop an image file only.');
                }
            }
        }, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImageDragDrop);
    } else {
        initImageDragDrop();
    }

    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:update', function() {
            setTimeout(initImageDragDrop, 100);
        }, { passive: true });
        
        document.addEventListener('livewire:init', function() {
            Livewire.hook('morph.updated', function() {
                setTimeout(initImageDragDrop, 100);
                initImageZoom();
            });
        });
    }

    function initImageZoom() {
        document.addEventListener('click', function(e) {
            const img = e.target.closest('.material-image-zoom');
            if (img) {
                e.preventDefault();
                const imageUrl = img.getAttribute('data-image-url');
                const materialName = img.getAttribute('data-material-name');
                
                if (imageUrl) {
                    document.getElementById('zoomedImage').src = imageUrl;
                    document.getElementById('zoomedImage').alt = materialName || 'Material Image';
                    document.getElementById('imageZoomModalTitle').textContent = materialName || 'Material Image';
                    
                    const modal = new bootstrap.Modal(document.getElementById('imageZoomModal'));
                    modal.show();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImageZoom);
    } else {
        initImageZoom();
    }

    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:init', function() {
            Livewire.hook('morph.updated', function() {
                setTimeout(initImageZoom, 100);
            });
        });
    }

    let isInitializing = false;
    let initializedFields = new Set();
    let observer = null;
    let morphUpdateTimeout = null;

    function initSelect2() {
        if (isInitializing) {
            return false;
        }

        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
            return false;
        }

        const select2Fields = document.querySelectorAll('.select2-field');
        let initialized = false;

        select2Fields.forEach(function(select) {
            const fieldId = select.id || select.getAttribute('data-select2-field');
            
            if (!fieldId) {
                return;
            }
            
            if (jQuery(select).hasClass('select2-hidden-accessible')) {
                if (!initializedFields.has(fieldId)) {
                    initializedFields.add(fieldId);
                }
                return;
            }

            const isVisible = select.offsetParent !== null || 
                             (select.closest('.modal') && select.closest('.modal').style.display !== 'none') ||
                             (select.closest('[x-show]') && select.closest('[x-show]').offsetParent !== null);
            
            if (isVisible || select.closest('.modal')) {
                isInitializing = true;
                initialized = true;

                const fieldName = jQuery(select).attr('data-select2-field') || select.id;
                let currentValue = select.value || '';
                
                try {
                    if (typeof @this !== 'undefined' && @this.get) {
                        const livewireValue = @this.get(fieldName);
                        if (livewireValue !== null && livewireValue !== undefined) {
                            currentValue = String(livewireValue);
                        }
                    }
                } catch (e) {
                }
                
                if (fieldName === 'material_type' && (!currentValue || currentValue === '')) {
                    currentValue = '0';
                }
                
                try {
                    if (jQuery(select).data('select2')) {
                        jQuery(select).select2('destroy');
                    }
                    
                    const placeholderText = jQuery(select).attr('data-placeholder') || jQuery(select).find('option:first').text();
                    
                    jQuery(select).select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: placeholderText,
                        dropdownParent: jQuery(select).closest('.modal, body'),
                        allowClear: false,
                        minimumResultsForSearch: 0
                    });

                    if (currentValue) {
                        jQuery(select).val(currentValue);
                        select.value = currentValue;
                    } else {
                        jQuery(select).val(null);
                        select.value = '';
                    }
                    jQuery(select).trigger('change.select2');
                    
                    if (select.hasAttribute('readonly') || select.disabled) {
                        jQuery(select).prop('disabled', true);
                        jQuery(select).select2('enable', false);
                    }
                    
                    initializedFields.add(fieldId);
                } catch (e) {
                    console.error('Select2 initialization error:', e);
                }

                let isUpdating = false;
                const selectFieldName = fieldName;
                
                const handleSelect2Change = function(e) {
                    if (isUpdating) return;
                    
                    const $select = jQuery(this);
                    let newValue = $select.val();
                    const selectElement = $select[0];
                    
                    if (newValue === null || newValue === undefined) {
                        newValue = '';
                    }
                    
                    if (selectElement) {
                        selectElement.value = newValue || '';
                    }
                    
                    try {
                        if (typeof @this !== 'undefined') {
                            isUpdating = true;
                            
                            if (@this.set && selectFieldName) {
                                @this.set(selectFieldName, newValue);
                            }
                            
                            setTimeout(() => { isUpdating = false; }, 150);
                        }
                    } catch (e) {
                        console.error('Select2 update error:', e);
                        isUpdating = false;
                    }
                };
                
                jQuery(select).off('change.select2-livewire').on('change.select2-livewire', handleSelect2Change);
                
                jQuery(select).on('select2:select select2:unselect select2:clear', function(e) {
                    const $select = jQuery(this);
                    const val = $select.val();
                    const selectElement = $select[0];
                    
                    if (selectElement) {
                        selectElement.value = val || '';
                        const changeEvent = new Event('change', { bubbles: true });
                        selectElement.dispatchEvent(changeEvent);
                    }
                    
                    setTimeout(function() {
                        $select.trigger('change.select2-livewire');
                    }, 50);
                });
            }
        });

        isInitializing = false;
        return initialized;
    }

    function tryInit() {
        if (isInitializing) {
            return;
        }
        
        if (!initSelect2()) {
            if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
                setTimeout(tryInit, 200);
            }
        }
    }

    function debounceMorphUpdate() {
        if (morphUpdateTimeout) {
            clearTimeout(morphUpdateTimeout);
        }
        morphUpdateTimeout = setTimeout(function() {
            initializedFields.clear();
            tryInit();
        }, 500);
    }

    function setupObserver() {
        if (observer) {
            observer.disconnect();
        }

        observer = new MutationObserver(function(mutations) {
            let shouldReinit = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            const hasSelect2Field = node.querySelector && (
                                node.querySelector('.select2-field') || 
                                node.classList && node.classList.contains('select2-field')
                            );
                            if (hasSelect2Field) {
                                shouldReinit = true;
                            }
                            if (node.tagName === 'SELECT' && node.classList && node.classList.contains('select2-field')) {
                                shouldReinit = true;
                            }
                        }
                    });
                }
            });

            if (shouldReinit && !isInitializing) {
                setTimeout(function() {
                    initializedFields.clear();
                    tryInit();
                }, 300);
            }
        });

        const targetNode = document.body;
        if (targetNode) {
            observer.observe(targetNode, {
                childList: true,
                subtree: true
            });
        }
    }

    function initializeOnReady() {
        setTimeout(function() {
            tryInit();
            setupObserver();
        }, 100);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeOnReady);
    } else {
        initializeOnReady();
    }

    if (typeof Livewire !== 'undefined') {
        document.addEventListener('livewire:init', function() {
            Livewire.hook('morph.updated', function() {
                debounceMorphUpdate();
            });
        });
        
        document.addEventListener('livewire:update', function() {
            debounceMorphUpdate();
        }, { passive: true });
    }
})();
</script>
@endpush

