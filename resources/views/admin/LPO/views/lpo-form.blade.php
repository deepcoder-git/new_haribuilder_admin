<div>
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit LPO' : 'Add LPO' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
        <div class="row">
            @if(!auth('moderator')->user()->hasRole(\App\Utility\Enums\RoleEnum::TransportManager))
            @php
                $isStoreManager = auth('moderator')->user()->hasRole(\App\Utility\Enums\RoleEnum::StoreManager);
                $shouldDisableSiteFields = $isStoreManager && $this->isEditMode;
            @endphp
            <div class="col-md-4 mb-4">
                <label for="site_id" class="form-label" style="font-size: 0.9375rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; letter-spacing: -0.01em;">
                    Site <span class="text-danger">*</span>
                </label>
                <div class="position-relative">
                    @php
                        $selectedSite = $sites->firstWhere('id', $site_id);
                        $selectedSiteName = $selectedSite ? $selectedSite->name : 'Select Site';
                    @endphp
                    <button type="button"
                            wire:click="toggleSiteDropdown"
                            wire:loading.attr="disabled"
                            @if($shouldDisableSiteFields) disabled @endif
                            class="form-control form-control-solid d-flex align-items-center justify-content-between @error('site_id') is-invalid @enderror"
                            style="height: 44px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                        <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                            {{ $selectedSiteName }}
                        </span>
                        <i class="fa-solid fa-chevron-{{ $siteDropdownOpen ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                    </button>
                    
                    @if($siteDropdownOpen)
                        <div class="position-absolute bg-white border rounded shadow-lg" 
                             style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                             wire:click.stop
                             x-data="{ 
                                 isOpen: true,
                                 closeDropdown() {
                                     if (typeof $wire !== 'undefined') {
                                         $wire.call('closeSiteDropdown');
                                     }
                                     this.isOpen = false;
                                 },
                                 handleScroll(event) {
                                     const el = event.target;
                                     if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                         if (typeof $wire !== 'undefined') {
                                             $wire.call('loadMoreSites');
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
                                           wire:model="siteSearch"
                                           wire:keyup.debounce.300ms="handleSiteSearch($event.target.value, 'siteSearch')"
                                           wire:key="site-search"
                                           placeholder="Search sites..."
                                           class="form-control form-control-solid"
                                           style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                           autofocus
                                           wire:ignore.self>
                                </div>
                            </div>
                            <div style="overflow-y: auto; max-height: 250px; flex: 1;"
                                 x-on:scroll="handleScroll($event)">
                                @if($siteLoading && empty($siteSearchResults))
                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                        <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                        <div>Searching...</div>
                                    </div>
                                @elseif(empty($siteSearchResults))
                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                        <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                        <div>No sites found</div>
                                    </div>
                                @else
                                    @foreach($siteSearchResults as $result)
                                        <div wire:click="selectSite({{ $result['id'] ?? 'null' }})"
                                             class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                             style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; {{ $site_id == $result['id'] ? 'background-color: #f0f9ff;' : '' }}"
                                             onmouseover="this.style.backgroundColor='#f9fafb'"
                                             onmouseout="this.style.backgroundColor='{{ $site_id == $result['id'] ? '#f0f9ff' : 'white' }}'">
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="fw-semibold text-truncate" style="font-size: 0.875rem; color: #1f2937;">
                                                    {{ $result['text'] }}
                                                </div>
                                                @if(!empty($result['location']))
                                                    <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                                        {{ $result['location'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    @if($siteLoading && $siteHasMore)
                                        <div class="text-center py-2"
                                             style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                            <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                            Loading more...
                                        </div>
                                    @elseif($siteHasMore)
                                        <div wire:click="loadMoreSites"
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
                @error('site_id') 
                    <div class="text-danger mt-1">{{ $message }}</div> 
                @enderror
                @if($site_id)
                    <small class="text-muted d-block mt-2" style="display: flex; align-items: center; gap: 0.375rem; color: #6b7280; font-size: 0.8125rem;">
                        <i class="fa-solid fa-location-dot" style="font-size: 0.875rem; color: {{ $drop_location ? '#059669' : '#6b7280' }};"></i> 
                        <span>{{ $drop_location ?: 'No address available' }}</span>
                    </small>
                @endif
            </div>

            <div class="col-md-4 mb-4">
                <label for="site_manager_id" class="form-label" style="font-size: 0.9375rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; letter-spacing: -0.01em;">
                    Site Supervisor
                </label>
                <div class="position-relative">
                    @php
                        $selectedSiteManager = $siteManagers->firstWhere('id', $site_manager_id);
                        $selectedSiteManagerName = $selectedSiteManager ? $selectedSiteManager->name : 'Select Site Supervisor';
                    @endphp
                    <button type="button"
                            wire:click="toggleSiteManagerDropdown"
                            wire:loading.attr="disabled"
                            @if($shouldDisableSiteFields || $this->shouldDisableSiteSupervisor) disabled @endif
                            class="form-control form-control-solid d-flex align-items-center justify-content-between @error('site_manager_id') is-invalid @enderror"
                            style="height: 44px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                        <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                            {{ $selectedSiteManagerName }}
                        </span>
                        <i class="fa-solid fa-chevron-{{ $siteManagerDropdownOpen ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                    </button>
                    
                    @if($siteManagerDropdownOpen && $site_id)
                        <div class="position-absolute bg-white border rounded shadow-lg" 
                             style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                             wire:click.stop
                             x-data="{ 
                                 isOpen: true,
                                 closeDropdown() {
                                     if (typeof $wire !== 'undefined') {
                                         $wire.call('closeSiteManagerDropdown');
                                     }
                                     this.isOpen = false;
                                 },
                                 handleScroll(event) {
                                     const el = event.target;
                                     if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                         if (typeof $wire !== 'undefined') {
                                             $wire.call('loadMoreSiteManagers');
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
                                           wire:model="siteManagerSearch"
                                           wire:keyup.debounce.300ms="handleSiteManagerSearch($event.target.value, 'siteManagerSearch')"
                                           wire:key="site-manager-search"
                                           placeholder="Search site supervisors..."
                                           class="form-control form-control-solid"
                                           style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                           autofocus
                                           wire:ignore.self>
                                </div>
                            </div>
                            <div style="overflow-y: auto; max-height: 250px; flex: 1;"
                                 x-on:scroll="handleScroll($event)">
                                @if($siteManagerLoading && empty($siteManagerSearchResults))
                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                        <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                        <div>Searching...</div>
                                    </div>
                                @elseif(empty($siteManagerSearchResults))
                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                        <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                        <div>No site supervisors found</div>
                                    </div>
                                @else
                                    @foreach($siteManagerSearchResults as $result)
                                        <div wire:click="selectSiteManager({{ $result['id'] ?? 'null' }})"
                                             class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                             style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; {{ $site_manager_id == $result['id'] ? 'background-color: #f0f9ff;' : '' }}"
                                             onmouseover="this.style.backgroundColor='#f9fafb'"
                                             onmouseout="this.style.backgroundColor='{{ $site_manager_id == $result['id'] ? '#f0f9ff' : 'white' }}'">
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="fw-semibold text-truncate" style="font-size: 0.875rem; color: #1f2937;">
                                                    {{ $result['text'] }}
                                                </div>
                                                @if(!empty($result['email']))
                                                    <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                                        {{ $result['email'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    @if($siteManagerLoading && $siteManagerHasMore)
                                        <div class="text-center py-2"
                                             style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                            <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                            Loading more...
                                        </div>
                                    @elseif($siteManagerHasMore)
                                        <div wire:click="loadMoreSiteManagers"
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
                @error('site_manager_id') 
                    <div class="text-danger mt-1">{{ $message }}</div> 
                @enderror
                @if(!$site_id)
                    <small class="text-muted d-block mt-2" style="display: flex; align-items: center; gap: 0.375rem; color: #6b7280; font-size: 0.8125rem;">
                        <i class="fa-solid fa-info-circle" style="font-size: 0.875rem; color: #6b7280;"></i> 
                        <span>Please select a site first</span>
                    </small>
                @elseif($this->shouldDisableSiteSupervisor && $site_manager_id)
                    <small class="text-muted d-block mt-2" style="display: flex; align-items: center; gap: 0.375rem; color: #059669; font-size: 0.8125rem;">
                        <i class="fa-solid fa-check-circle" style="font-size: 0.875rem; color: #059669;"></i> 
                        <span>Auto-filled from selected site</span>
                    </small>
                @endif
            </div>

            <div class="col-md-4 mb-4">
                <label for="note" class="form-label" style="font-size: 0.9375rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; letter-spacing: -0.01em;">
                    Note
                </label>
                <textarea id="note"
                          wire:model="note"
                          rows="2"
                          class="form-control form-control-solid @error('note') is-invalid @enderror"
                          placeholder="Enter note"
                          ></textarea>
                @error('note') 
                    <div class="text-danger mt-1">{{ $message }}</div> 
                @enderror
            </div>

            @endif
        </div>

        @if(!auth('moderator')->user()->hasRole(\App\Utility\Enums\RoleEnum::TransportManager))
        <div class="row">
            <div class="col-md-4 mb-4">
                <label for="priority" class="form-label" style="font-size: 0.9375rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; letter-spacing: -0.01em;">
                    Priority <span class="text-danger">*</span>
                </label>
                <select id="priority"
                        wire:model="priority"
                        class="form-select form-select-solid @error('priority') is-invalid @enderror">
                    <option value="">Select Priority</option>
                    @foreach($priorities as $priorityOption)
                        <option value="{{ $priorityOption->value }}">{{ $priorityOption->getName() }}</option>
                    @endforeach
                </select>
                @error('priority') 
                    <div class="text-danger mt-1">{{ $message }}</div> 
                @enderror
            </div>

            <div class="col-md-4 mb-4">
                <label for="expected_delivery_date" class="form-label" style="font-size: 0.9375rem; font-weight: 600; color: #374151; margin-bottom: 0.5rem; letter-spacing: -0.01em;">
                    Exp. date of delivery <span class="text-danger">*</span>
                </label>
                <input type="date"
                       id="expected_delivery_date"
                       wire:model="expected_delivery_date"
                       class="form-control form-control-solid @error('expected_delivery_date') is-invalid @enderror"
                       />
                @error('expected_delivery_date')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @endif

        @if(!auth('moderator')->user()->hasRole(\App\Utility\Enums\RoleEnum::TransportManager))
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="form-label mb-0 fw-semibold" style="font-size: 1.0625rem; color: #1f2937; letter-spacing: -0.01em;">
                        Products & Materials <span class="text-danger">*</span>
                    </label>
                </div>

                <div class="mb-4" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: visible; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff; position: relative;">
                    <div style="background: #e9d5ff; padding: 0.75rem 1rem; color: #1f2937; font-weight: 600; font-size: 0.9375rem; border-radius: 0.75rem 0.75rem 0 0; display: flex; align-items: center; justify-content: flex-start; gap: 0.5rem; border-bottom: 2px solid rgba(0,0,0,0.1);">
                        <span style="display: flex; align-items: center; gap: 0.5rem; color: #1f2937;">
                            <i class="fa-solid fa-boxes" style="font-size: 1rem; color: #8b5cf6;"></i>
                            <span style="color: #1f2937; font-weight: 600;">LPO</span>
                        </span>
                    </div>

                    <div class="table-responsive order-table-container" style="overflow: visible;">
                    <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                        <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                            <tr>
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 350px; width: 45%;">Product</th>
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 100px;">Category</th>
                                {{-- New Supplier column to match order edit layout --}}
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 150px;">Supplier</th>
                                {{-- Supplier Status column (per-supplier LPO status) --}}
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 170px;">Supplier Status</th>
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 70px;">Unit</th>
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orderProducts as $index => $product)
                                @php
                                    $isCustom = $product['is_custom'] ?? 0;
                                    $productId = $product['product_id'] ?? '';
                                    $customNote = $product['custom_note'] ?? '';
                                    $customImages = $product['custom_images'] ?? [];
                                    if (!is_array($customImages)) {
                                        $customImages = $customImages ? [$customImages] : [];
                                    }
                                    
                                    // Only display rows with actual LPO products selected (not empty rows)
                                    // For regular products: must have product_id
                                    // getProductsProperty already filters to only LPO products, so if found, it's an LPO product
                                    // For custom products: must have custom_note or custom_images
                                    $selectedProduct = !$isCustom && !empty($productId) ? $products->firstWhere('id', $productId) : null;
                                    $hasProduct = !$isCustom && !empty($productId) && $selectedProduct !== null;
                                    $hasCustomContent = $isCustom && (!empty($customNote) || !empty($customImages));
                                @endphp
                                
                                @if($hasProduct || $hasCustomContent)
                                    <tr wire:key="product-row-{{ $index }}" style="vertical-align: middle; border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s ease;">
                                    <td style="padding: 1rem 0.75rem; text-align: center; vertical-align: middle;">
                                        <div style="width: 50px; height: 50px; background: #f9fafb; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 1px solid #e5e7eb;">
                                            @if($isCustom)
                                                @php
                                                    $customImages = $product['custom_images'] ?? [];
                                                    if (!is_array($customImages)) {
                                                        $customImages = $customImages ? [$customImages] : [];
                                                    }
                                                    $firstImageUrl = null;
                                                    if (!empty($customImages)) {
                                                        $firstImage = is_string($customImages[0]) ? $customImages[0] : null;
                                                        $firstImageUrl = $firstImage ? \Storage::url($firstImage) : null;
                                                    }
                                                @endphp
                                                @if($firstImageUrl)
                                                    <img src="{{ $firstImageUrl }}" 
                                                         alt="Custom Product"
                                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">
                                                @else
                                                    <i class="fa-solid fa-image text-gray-400" style="font-size: 1.25rem;"></i>
                                                @endif
                                            @else
                                                @php
                                                    $imageUrl = null;
                                                    if($selectedProduct) {
                                                        $firstImage = $selectedProduct->productImages->first();
                                                        $imageUrl = $firstImage ? $firstImage->image_url : ($selectedProduct->image ? \Storage::url($selectedProduct->image) : null);
                                                    }
                                                @endphp
                                                @if($imageUrl)
                                                    <img src="{{ $imageUrl }}" 
                                                         alt="{{ $selectedProduct->product_name }}"
                                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">
                                                @else
                                                    <i class="fa-solid fa-image text-gray-400" style="font-size: 1.25rem;"></i>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td style="padding: 1rem 0.75rem; vertical-align: middle;" colspan="{{ $isCustom ? '2' : '1' }}">
                                        @if($isCustom)
                                            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                                                <div style="min-height: 42px;">
                                                    <textarea wire:model="orderProducts.{{ $index }}.custom_note"
                                                              rows="2"
                                                              class="form-control form-control-solid @error('orderProducts.'.$index.'.custom_note') is-invalid @enderror"
                                                              placeholder="Enter custom product description..."
                                                              style="font-size: 0.9375rem; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 0.625rem;"
                                                              ></textarea>
                                                </div>
                                                @error("orderProducts.{$index}.custom_note") 
                                                    <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444;">{{ $message }}</div> 
                                                @enderror
                                                <div>
                                                    <input type="file" 
                                                           wire:model="orderProducts.{{ $index }}.custom_images"
                                                           accept="image/jpeg,image/jpg,image/png"
                                                           multiple
                                                           class="form-control form-control-solid @error('orderProducts.'.$index.'.custom_images') is-invalid @enderror"
                                                           style="font-size: 0.875rem; padding: 0.5rem;"
                                                           >
                                                    @error("orderProducts.{$index}.custom_images") 
                                                        <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444;">{{ $message }}</div> 
                                                    @enderror
                                                    @error("orderProducts.{$index}.custom_images.*") 
                                                        <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444;">{{ $message }}</div> 
                                                    @enderror
                                                    @php
                                                        $customImages = $product['custom_images'] ?? [];
                                                        if (!is_array($customImages)) {
                                                            $customImages = $customImages ? [$customImages] : [];
                                                        }
                                                    @endphp
                                                    @if(!empty($customImages))
                                                        <div class="mt-2" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                                            @foreach($customImages as $imgIndex => $customImage)
                                                                @if(is_string($customImage))
                                                                    <div style="position: relative; display: inline-block;">
                                                                        <a href="{{ \Storage::url($customImage) }}" target="_blank" style="display: block;">
                                                                            <img src="{{ \Storage::url($customImage) }}" 
                                                                                 alt="Custom Product Image {{ $imgIndex + 1 }}"
                                                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 0.5rem; border: 1px solid #e5e7eb; cursor: pointer;">
                                                                        </a>
                                                                        <button type="button" 
                                                                                wire:click="removeCustomImage({{ $index }}, {{ $imgIndex }})"
                                                                                style="position: absolute; top: -8px; right: -8px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.75rem; padding: 0;">
                                                                            ×
                                                                        </button>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                                                <div style="min-height: 42px; position: relative;">
                                                    <div class="position-relative" style="width: 100%;">
                                                        @php
                                                            $currentProductId = isset($product['product_id']) ? (string)$product['product_id'] : '';
                                                            $selectedProduct = $products->firstWhere('id', $currentProductId);
                                                            $selectedProductName = 'Select Product / Material';
                                                            if ($selectedProduct) {
                                                                $typeLabel = $selectedProduct->type ? $selectedProduct->type->getName() : '';
                                                                $itemType = $selectedProduct->is_product ? 'Product' : 'Material';
                                                                if ($selectedProduct->is_product == 1 && $selectedProduct->type == \App\Utility\Enums\ProductTypeEnum::Product) {
                                                                    $selectedProductName = $selectedProduct->product_name . ' [Product]';
                                                                } elseif ($selectedProduct->is_product == 1 && $selectedProduct->type == \App\Utility\Enums\ProductTypeEnum::Material) {
                                                                    $selectedProductName = $selectedProduct->product_name . ' [Material as Product]';
                                                                } elseif ($selectedProduct->is_product == 2) {
                                                                    $selectedProductName = $selectedProduct->product_name . ' [Material + Product]';
                                                                } else {
                                                                    $selectedProductName = $selectedProduct->product_name . ' [' . $itemType . ']' . ($typeLabel ? ' (' . $typeLabel . ')' : '');
                                                                }
                                                            }
                                                        @endphp
                                                        @php
                                                            $hasProductError = $errors->has('orderProducts.' . $index . '.product_id');
                                                        @endphp
                                                        <button type="button"
                                                                wire:click="toggleProductDropdown({{ $index }})"
                                                                wire:loading.attr="disabled"
                                                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('orderProducts.' . $index . '.product_id') is-invalid @enderror"
                                                                style="height: 40px; text-align: left; background: white; border: 1px solid {{ $hasProductError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 1rem; min-width: 250px; width: 100%;">
                                                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                                                {{ $selectedProductName }}
                                                            </span>
                                                            <i class="fa-solid fa-chevron-{{ isset($productDropdownOpen[$index]) && $productDropdownOpen[$index] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                                                        </button>
                                                        
                                                        @if(isset($productDropdownOpen[$index]) && $productDropdownOpen[$index])
                                                        <div class="position-absolute bg-white border rounded shadow-lg" 
                                                             style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                                                             wire:click.stop
                                                             x-data="{ 
                                                                 index: {{ (int)($index ?? 0) }},
                                                                 isOpen: true,
                                                                 closeDropdown() {
                                                                     if (typeof $wire !== 'undefined') {
                                                                         $wire.call('closeProductDropdown', this.index);
                                                                     }
                                                                     this.isOpen = false;
                                                                 },
                                                                 handleScroll(event) {
                                                                     const el = event.target;
                                                                     if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                                                         if (typeof $wire !== 'undefined') {
                                                                             $wire.call('loadMoreProducts', this.index);
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
                                                                           wire:model="productSearch.{{ $index }}"
                                                                           wire:keyup.debounce.300ms="handleProductSearch($event.target.value, 'productSearch.{{ $index }}')"
                                                                           wire:key="product-search-{{ $index }}"
                                                                           placeholder="Search products..."
                                                                           class="form-control form-control-solid"
                                                                           style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                                                           autofocus
                                                                           wire:ignore.self>
                                                                </div>
                                                            </div>
                                                            <div id="product-dropdown-{{ $index }}"
                                                                 style="overflow-y: auto; max-height: 250px; flex: 1;"
                                                                 x-on:scroll="handleScroll($event)">
                                                                @if(isset($productLoading[$index]) && $productLoading[$index] && empty($productSearchResults[$index] ?? []))
                                                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                        <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                        <div>Searching...</div>
                                                                    </div>
                                                                @elseif(empty($productSearchResults[$index] ?? []))
                                                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                        <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                        <div>No products found</div>
                                                                    </div>
                                                                @else
                                                                    @foreach($productSearchResults[$index] ?? [] as $result)
                                                                        <div wire:click="selectProduct({{ $index }}, {{ $result['id'] ?? 'null' }})"
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
                                                                    
                                                                    @if(isset($productLoading[$index]) && $productLoading[$index] && !empty($productHasMore[$index] ?? false))
                                                                        <div class="text-center py-2"
                                                                             style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                                                            <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                                                            Loading more...
                                                                        </div>
                                                                    @elseif(!empty($productHasMore[$index] ?? false))
                                                                        <div wire:click="loadMoreProducts({{ $index }})"
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
                                                </div>
                                                @error("orderProducts.{$index}.product_id") 
                                                    <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444; font-weight: 500;">{{ $message }}</div> 
                                                @enderror
                                                @if($selectedProduct)
                                                    @php
                                                        $isLPO = $selectedProduct->store === \App\Utility\Enums\StoreEnum::LPO;
                                                        $typeLabel = $selectedProduct->type ? $selectedProduct->type->getName() : '';
                                                    @endphp
                                                    <div style="line-height: 1.4; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                                        @if($typeLabel)
                                                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                                                <i class="fa-solid fa-tag" style="font-size: 0.75rem; color: #6366f1;"></i>
                                                                <small class="text-muted" style="font-size: 0.8125rem; color: #6b7280;">
                                                                    Type: <strong style="color: #6366f1; font-weight: 600;">{{ $typeLabel }}</strong>
                                                                </small>
                                                            </div>
                                                        @endif
                                                        @if(!$isLPO)
                                                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                                                <i class="fa-solid fa-box" style="font-size: 0.75rem; color: #059669;"></i>
                                                                <small class="text-muted" style="font-size: 0.8125rem; color: #6b7280;">
                                                                    Available Stock: <strong style="color: #059669; font-weight: 600;">{{ number_format($this->getCurrentStockForProduct($product['product_id'], $site_id)) }}</strong>
                                                                </small>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    @if(!$isCustom)
                                    <td style="padding: 1rem 0.75rem;">
                                        @php
                                            // Get category name from product - display only, not stored
                                            $categoryName = '';
                                            if ($selectedProduct) {
                                                // Get category name - products collection has categories loaded
                                                if ($selectedProduct->category_id && isset($selectedProduct->category)) {
                                                    $categoryName = $selectedProduct->category->name ?? '';
                                                }
                                            }
                                        @endphp
                                        <input type="text" 
                                               value="{{ $categoryName }}"
                                               class="form-control form-control-solid"
                                               readonly
                                               disabled
                                               style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                    </td>
                                    {{-- Supplier column (same UI as order edit page, specific for LPO products) --}}
                                    <td style="padding: 1rem 0.75rem;">
                                        @php
                                            $currentSupplierId = $product['supplier_id'] ?? null;
                                            $selectedSupplier = null;
                                            $supplierName = 'Select Supplier';

                                            if ($currentSupplierId) {
                                                // First try to find in search results
                                                if (isset($supplierSearchResults[$index]) && !empty($supplierSearchResults[$index])) {
                                                    $selectedSupplier = collect($supplierSearchResults[$index])->firstWhere('id', (int) $currentSupplierId);
                                                }

                                                // If not found in search results, try to load from database
                                                if (!$selectedSupplier) {
                                                    $supplier = \App\Models\Supplier::find((int) $currentSupplierId);
                                                    if ($supplier) {
                                                        $supplierName = $supplier->name;
                                                    }
                                                } else {
                                                    $supplierName = $selectedSupplier['text'];
                                                }
                                            }
                                        @endphp
                                        <div class="position-relative" style="width: 100%; z-index: auto; overflow: visible;">
                                            <button type="button"
                                                    wire:click="toggleSupplierDropdown({{ $index }})"
                                                    wire:loading.attr="disabled"
                                                    class="form-control form-control-solid d-flex align-items-center justify-content-between"
                                                    style="height: 40px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                                                <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                                    {{ $supplierName }}
                                                </span>
                                                <i class="fa-solid fa-chevron-{{ isset($supplierDropdownOpen[$index]) && $supplierDropdownOpen[$index] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                                            </button>

                                            @if(isset($supplierDropdownOpen[$index]) && $supplierDropdownOpen[$index])
                                                <div class="position-fixed bg-white border rounded shadow-lg supplier-dropdown-overlay" 
                                                     style="z-index: 999999; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; width: 300px; min-width: 300px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);"
                                                     wire:click.stop
                                                     x-data="{ 
                                                         index: {{ (int) ($index ?? 0) }},
                                                         isOpen: true,
                                                         closeDropdown() {
                                                             if (typeof $wire !== 'undefined') {
                                                                 $wire.call('closeSupplierDropdown', this.index);
                                                             }
                                                             this.isOpen = false;
                                                         },
                                                         handleScroll(event) {
                                                             const el = event.target;
                                                             if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                                                 if (typeof $wire !== 'undefined') {
                                                                     $wire.call('loadMoreSuppliers', this.index);
                                                                 }
                                                             }
                                                         },
                                                         updatePosition() {
                                                             const button = $el.previousElementSibling;
                                                             if (button) {
                                                                 const rect = button.getBoundingClientRect();
                                                                 const viewportHeight = window.innerHeight;
                                                                 const spaceBelow = viewportHeight - rect.bottom;
                                                                 
                                                                 $el.style.left = rect.left + 'px';
                                                                 $el.style.top = (rect.bottom + 4) + 'px';
                                                                 $el.style.width = rect.width + 'px';
                                                                 
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
                                                                   wire:model="supplierSearch.{{ $index }}"
                                                                   wire:keyup.debounce.300ms="handleSupplierSearch($event.target.value, 'supplierSearch.{{ $index }}')"
                                                                   wire:key="supplier-search-{{ $index }}"
                                                                   placeholder="Search suppliers..."
                                                                   class="form-control form-control-solid"
                                                                   style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                                                   autofocus
                                                                   wire:ignore.self>
                                                        </div>
                                                    </div>
                                                    <div id="supplier-dropdown-{{ $index }}"
                                                         style="overflow-y: auto; max-height: 250px; flex: 1;"
                                                         x-on:scroll="handleScroll($event)">
                                                        @if(isset($supplierLoading[$index]) && $supplierLoading[$index] && empty($supplierSearchResults[$index] ?? []))
                                                            <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                <div>Searching...</div>
                                                            </div>
                                                        @elseif(empty($supplierSearchResults[$index] ?? []))
                                                            <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                <div>No suppliers found</div>
                                                            </div>
                                                        @else
                                                            @foreach($supplierSearchResults[$index] ?? [] as $result)
                                                                <div wire:click="selectSupplier({{ $index }}, {{ $result['id'] ?? 'null' }})"
                                                                     class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                                                     style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer;"
                                                                     onmouseover="this.style.backgroundColor='#f9fafb'"
                                                                     onmouseout="this.style.backgroundColor='white'">
                                                                    <div style="flex: 1; min-width: 0;">
                                                                        <div class="fw-semibold text-truncate" style="font-size: 0.875rem; color: #1f2937;">
                                                                            {{ $result['text'] }}
                                                                        </div>
                                                                        @if(!empty($result['type']))
                                                                            <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                                                                {{ $result['type'] }}
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    {{-- Supplier Status column (per-row, supplier-wise LPO status) --}}
                                    <td style="padding: 1rem 0.75rem;">
                                        @php
                                            $currentSupplierId = $product['supplier_id'] ?? null;
                                            $supplierStatuses = $productStatuses['lpo'] ?? [];
                                            $currentSupplierStatus = 'pending';

                                            if ($currentSupplierId && is_array($supplierStatuses) && isset($supplierStatuses[(string)$currentSupplierId])) {
                                                $currentSupplierStatus = $supplierStatuses[(string)$currentSupplierId] ?? 'pending';
                                            }

                                            $statusOptions = [
                                                'pending' => 'Pending',
                                                'approved' => 'Approved',
                                                // in_transit is intentionally not exposed here for LPO supplier rows
                                                'outfordelivery' => 'Out for delivery',
                                                'delivered' => 'Delivered',
                                                'rejected' => 'Rejected',
                                            ];
                                        @endphp

                                        @if($currentSupplierId)
                                            <select class="form-select form-select-solid"
                                                    style="height: 40px; padding: 0.5rem 0.75rem; font-size: 0.875rem;"
                                                    wire:change="updateLpoSupplierStatus({{ $index }}, $event.target.value)">
                                                @foreach($statusOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($currentSupplierStatus === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text"
                                                   value="Select supplier first"
                                                   class="form-control form-control-solid"
                                                   readonly
                                                   disabled
                                                   style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                        @endif
                                    </td>
                                    <td style="padding: 1rem 0.75rem;">
                                        @php
                                            // Get unit type from product - display only, not stored
                                            $unitType = '';
                                            if ($selectedProduct) {
                                                $unitType = $selectedProduct->unit_type ?? '';
                                            }
                                        @endphp
                                        <input type="text" 
                                               value="{{ $unitType }}"
                                               class="form-control form-control-solid"
                                               readonly
                                               disabled
                                               style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                    </td>
                                    <td style="padding: 1rem 0.75rem;">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <button type="button" 
                                                    wire:click="decrementQuantity({{ $index }})"
                                                    class="btn btn-sm btn-light"
                                                    style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;">
                                                <i class="fa-solid fa-minus" style="font-size: 0.75rem;"></i>
                                            </button>
                                            @php
                                                $hasQuantityError = $errors->has('orderProducts.' . $index . '.quantity');
                                            @endphp
                                            <input type="number" 
                                                   wire:model.live="orderProducts.{{ $index }}.quantity"
                                                   step="1"
                                                   min="1"
                                                   class="form-control form-control-solid @error('orderProducts.' . $index . '.quantity') is-invalid @enderror"
                                                   style="width: 80px; height: 32px; text-align: center; padding: 0.25rem; border: 1px solid {{ $hasQuantityError ? '#ef4444' : '#e5e7eb' }};">
                                            <button type="button" 
                                                    wire:click="incrementQuantity({{ $index }})"
                                                    class="btn btn-sm btn-light"
                                                    style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;">
                                                <i class="fa-solid fa-plus" style="font-size: 0.75rem;"></i>
                                            </button>
                                        </div>
                                        @error('orderProducts.' . $index . '.quantity')
                                            <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444; font-weight: 500; text-align: center;">{{ $message }}</div>
                                        @enderror
                                    </td>
                                    @else
                                    <td style="padding: 1rem 0.75rem;">
                                        <input type="text" 
                                               value=""
                                               class="form-control form-control-solid"
                                               readonly
                                               disabled
                                               style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                    </td>
                                    {{-- Supplier (custom products: show disabled field to keep column alignment) --}}
                                    <td style="padding: 1rem 0.75rem;">
                                        <input type="text" 
                                               value=""
                                               class="form-control form-control-solid"
                                               readonly
                                               disabled
                                               style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                    </td>
                                    <td style="padding: 1rem 0.75rem;">
                                        <input type="text" 
                                               value="Custom Product"
                                               class="form-control form-control-solid"
                                               readonly
                                               disabled
                                               style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                    </td>
                                    <td style="padding: 1rem 0.75rem;">
                                        <input type="text" 
                                               value=""
                                               class="form-control form-control-solid"
                                               readonly
                                               disabled
                                               style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                    </td>
                                    @endif
                                    <td style="padding: 1rem 0.75rem; text-align: center;">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            @if(!$isEditMode)
                                                <button type="button" 
                                                        wire:click="removeProductRow({{ $index }})"
                                                        class="btn btn-sm btn-icon btn-light-danger"
                                                        title="Delete Row"
                                                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No products in this LPO order.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @error('orderProducts') 
                    <div class="text-danger mt-2" style="font-size: 0.875rem;">{{ $message }}</div> 
                @enderror
            </div>
        </div>
        @endif

        <div class="row">
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
                        {{ $isEditMode ? 'Update' : 'Add LPO' }}
                    </span>
                </button>
            </div>
        </div>
        
        <!-- In Transit Details Modal -->
        @if($showInTransitModal)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5);" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;">
                    <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                        <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                            <i class="fa-solid fa-truck text-primary me-2"></i>
                            Add Driver & Vehicle Details
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeInTransitModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <p class="text-gray-700 mb-4" style="font-size: 0.9375rem;">
                            Please provide driver and vehicle details for this order.
                        </p>
                        <div class="mb-3">
                            <label for="temp_driver_name" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Driver Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text"
                                id="temp_driver_name"
                                wire:model="temp_driver_name"
                                class="form-control form-control-solid" 
                                placeholder="Enter driver name"
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem;"
                                required>
                            @error('temp_driver_name')
                                <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="temp_vehicle_number" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Vehicle Number <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text"
                                id="temp_vehicle_number"
                                wire:model="temp_vehicle_number"
                                class="form-control form-control-solid" 
                                placeholder="Enter vehicle number"
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem;"
                                required>
                            @error('temp_vehicle_number')
                                <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff; border-radius: 0 0 0.75rem 0.75rem;">
                        <button type="button" class="btn btn-light-secondary" wire:click="closeInTransitModal" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="saveInTransitDetails" wire:loading.attr="disabled" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem; background: #1e3a8a; border: none;">
                            <span wire:loading.remove wire:target="saveInTransitDetails">
                                <i class="fa-solid fa-check me-2"></i>Save Details
                            </span>
                            <span wire:loading wire:target="saveInTransitDetails">
                                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('styles')
<style>
.order-form-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    line-height: 1;
    vertical-align: middle;
}

.order-form-icon.info {
    color: #6b7280;
}

.order-form-icon.warning {
    color: #f59e0b;
}

.order-form-icon.success {
    color: #059669;
}

.order-form-icon.danger {
    color: #ef4444;
}

.order-action-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
    line-height: 1;
}

.order-action-icon-success {
    color: #10b981;
}

.order-action-icon-success:hover {
    color: #059669;
    transform: translateY(-1px);
}

.order-action-icon-danger {
    color: #ef4444;
}

.order-action-icon-danger:hover {
    color: #dc2626;
    transform: translateY(-1px);
}

.order-table {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06);
    background: #ffffff;
}

.order-table thead {
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.order-table thead th {
    padding: 1rem 0.875rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.025em;
    text-transform: uppercase;
}

.order-table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
}

.order-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.15s ease;
}

.order-table tbody tr:hover {
    background-color: #fafafa;
}

.order-product-image {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 0.625rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.order-product-image-placeholder {
    width: 56px;
    height: 56px;
    background: #f9fafb;
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e5e7eb;
}

.order-product-image-placeholder i {
    font-size: 1.375rem;
    color: #9ca3af;
}

.order-form-label {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    letter-spacing: -0.01em;
}

.order-info-text {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    margin-top: 0.5rem;
}

.order-info-text.info {
    color: #6b7280;
}

.order-info-text.warning {
    color: #f59e0b;
}

.order-info-text i {
    font-size: 0.875rem;
}

[x-cloak] {
    display: none !important;
}

.order-table-container {
    position: relative;
    overflow: visible !important;
}

.order-table-container table {
    overflow: visible !important;
}

.order-table-container table td {
    overflow: visible !important;
}

.order-table-container table tbody tr {
    overflow: visible !important;
}

.product-dropdown-menu {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

</style>
@endpush

@push('footer')
<script>
// Select2 functionality removed - using Livewire dropdowns instead
</script>
@endpush
