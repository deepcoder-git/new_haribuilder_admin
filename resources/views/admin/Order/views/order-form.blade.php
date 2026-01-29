<div>
    <!-- Rejection Details Modal -->
    @if($showRejectionDetailsModal)
    <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5); z-index: 1050;" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;">
                <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                    <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                        <i class="fa-solid fa-ban text-danger me-2"></i>
                        Rejection Details
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeRejectionDetailsModal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    @if(session()->has('rejection_success'))
                        <div class="alert alert-success mb-3" style="font-size: 0.875rem; padding: 0.75rem; border-radius: 0.5rem;">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            {{ session('rejection_success') }}
                        </div>
                    @endif
                    @if(session()->has('rejection_error'))
                        <div class="alert alert-danger mb-3" style="font-size: 0.875rem; padding: 0.75rem; border-radius: 0.5rem;">
                            <i class="fa-solid fa-exclamation-circle me-2"></i>
                            {{ session('rejection_error') }}
                        </div>
                    @endif
                    
                    @if(!empty($rejectionDetailsProductStatuses))
                        @foreach($rejectionDetailsProductStatuses as $typeKey => $label)
                            @php
                                // Determine the actual type and supplier ID (for LPO)
                                $type = $typeKey;
                                $supplierId = null;
                                $isLpo = false;
                                
                                if (str_starts_with($typeKey, 'lpo_')) {
                                    $type = 'lpo';
                                    $supplierId = str_replace('lpo_', '', $typeKey);
                                    $isLpo = true;
                                }
                                
                                // Get rejection note for this product type
                                $rejectionNote = '';
                                $errorKey = '';
                                if ($isLpo && $supplierId !== null) {
                                    $rejectionNote = $productRejectionNotes['lpo'][$supplierId] ?? '';
                                    $errorKey = "productRejectionNotes.lpo.{$supplierId}";
                                } else {
                                    $rejectionNote = $productRejectionNotes[$type] ?? '';
                                    $errorKey = "productRejectionNotes.{$type}";
                                }
                            @endphp
                            <div class="mb-4">
                                <label class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                    <i class="fa-solid fa-times-circle text-danger me-2" style="font-size: 0.875rem;"></i>
                                    {{ $label }} - Rejection Reason <span class="text-danger">*</span>
                                </label>
                                <textarea 
                                    @if($isLpo && $supplierId !== null)
                                        wire:model="productRejectionNotes.lpo.{{ $supplierId }}"
                                    @else
                                        wire:model="productRejectionNotes.{{ $type }}"
                                    @endif
                                    class="form-control @if($errors->has('productRejectionNotes.' . ($isLpo ? 'lpo.' . $supplierId : $type))) is-invalid @endif" 
                                    rows="4" 
                                    placeholder="Enter rejection reason for {{ $label }}..."
                                    style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem; resize: vertical; min-height: 100px;"
                                ></textarea>
                                @error('productRejectionNotes.' . ($isLpo ? 'lpo.' . $supplierId : $type))
                                    <div class="invalid-feedback d-block" style="font-size: 0.875rem; margin-top: 0.25rem; color: #dc3545;">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        @endforeach
                    @else
                        <div class="mb-3">
                            <label class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Rejection Reason <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                wire:model="rejected_note" 
                                class="form-control @error('rejected_note') is-invalid @enderror" 
                                rows="5" 
                                placeholder="Enter rejection reason..."
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem; resize: vertical; min-height: 120px;"
                            ></textarea>
                            @error('rejected_note')
                                <div class="invalid-feedback d-block" style="font-size: 0.875rem; margin-top: 0.25rem;">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    @endif
                </div>
                <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff; border-radius: 0 0 0.75rem 0.75rem;">
                    <button type="button" class="btn btn-secondary" wire:click="closeRejectionDetailsModal" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                        Close
                    </button>
                    <button type="button" class="btn btn-primary" wire:click="saveRejectionDetails" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                        <span>
                            <i class="fa-solid fa-save me-2"></i>Save
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Order' : 'Add Order' }}
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
                    <button type="button" 
                            wire:click="addCustomProductRow"
                            class="btn btn-sm btn-outline-primary"
                            style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-plus"></i>
                        <span>Add Custom Product</span>
                    </button>
                </div>
                
                @if($isEditMode && !empty($groupedProducts))
                    {{-- Display grouped products in edit mode --}}
                    <div class="order-edit-mode">
                    @php
                        $groupLabels = [
                            'hardware' => 'Hardware',
                            'warehouse' => 'Workshop (Custom)',
                            'lpo' => 'LPO'
                        ];
                        $groupColors = [
                            'hardware' => '#dbeafe',  // Light blue
                            'warehouse' => '#d1fae5',  // Light green
                            'lpo' => '#e9d5ff'         // Light purple
                        ];
                    @endphp
                    @foreach(['hardware', 'warehouse', 'lpo'] as $groupType)
                        @if(!empty($groupedProducts[$groupType]))
                            <div class="mb-4" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: visible; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff; position: relative;">
                                @php
                                    // Get all status labels from enum
                                    $allStatusLabels = \App\Utility\Enums\ProductStatusEnum::getAllLabels();
                                    // Hide legacy 'completed' state from UI (use 'delivered' instead)
                                    unset($allStatusLabels['completed']);
                                    
                                    // For hardware type, exclude only 'in_transit' (all other statuses allowed)
                                    if ($groupType === 'hardware') {
                                        $excludedStatuses = \App\Utility\Enums\ProductStatusEnum::getHardwareExcludedStatuses();
                                        $productStatusLabels = array_filter($allStatusLabels, function($key) use ($excludedStatuses) {
                                            return !in_array($key, $excludedStatuses);
                                        }, ARRAY_FILTER_USE_KEY);
                                    } else {
                                        // For other types (warehouse, lpo, custom), show all statuses
                                        $productStatusLabels = $allStatusLabels;
                                    }
                                    // Handle LPO which is an array (supplier-wise), other types are strings
                                    // Map 'warehouse' to 'workshop' for productStatuses lookup (database uses 'workshop')
                                    $statusKey = $groupType === 'warehouse' ? 'workshop' : $groupType;
                                    $rawStatus = $productStatuses[$statusKey] ?? 'pending';
                                    if ($groupType === 'lpo' && is_array($rawStatus)) {
                                        // For LPO, calculate combined status from all supplier statuses
                                        $uniqueStatuses = array_unique(array_values($rawStatus));
                                        if (in_array('rejected', $uniqueStatuses, true)) {
                                            $currentGroupStatus = 'rejected';
                                        } elseif (in_array('pending', $uniqueStatuses, true)) {
                                            $currentGroupStatus = 'pending';
                                        } elseif (in_array('approved', $uniqueStatuses, true)) {
                                            $currentGroupStatus = 'approved';
                                        } else {
                                            $currentGroupStatus = 'pending';
                                        }
                                    } else {
                                        $currentGroupStatus = is_string($rawStatus) ? $rawStatus : 'pending';
                                    }
                                    // Get colors, text colors, and icons from enum
                                    $productStatusColors = \App\Utility\Enums\ProductStatusEnum::getAllColors();
                                    $productStatusTextColors = \App\Utility\Enums\ProductStatusEnum::getAllTextColors();
                                    $statusIcons = \App\Utility\Enums\ProductStatusEnum::getAllIcons();
                                @endphp
                                <div style="background: {{ $groupColors[$groupType] }}; padding: 0.75rem 1rem; color: #1f2937; font-weight: 600; font-size: 0.9375rem; border-radius: 0.75rem 0.75rem 0 0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid rgba(0,0,0,0.1);">
                                    <span style="display: flex; align-items: center; gap: 0.5rem; color: #1f2937;">
                                        @php
                                            $groupIconColors = [
                                                'hardware' => '#3b82f6',  // Blue
                                                'warehouse' => '#10b981', // Green
                                                'lpo' => '#8b5cf6'        // Purple
                                            ];
                                        @endphp
                                        <i class="fa-solid fa-boxes" style="font-size: 1rem; color: {{ $groupIconColors[$groupType] ?? '#6b7280' }};"></i>
                                        <span style="color: #1f2937; font-weight: 600;">{{ $groupLabels[$groupType] }}</span>
                                    </span>
                                    @if($groupType !== 'lpo')
                                        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                            <label style="margin: 0; font-size: 0.8125rem; font-weight: 600; color: #374151; white-space: nowrap;">
                                                Order Status:
                                            </label>
                                            @if(!empty($productStatusSuccess[$groupType] ?? null))
                                                <div class="alert alert-success mb-0 d-flex align-items-center justify-content-between gap-2" style="font-size: 0.75rem; padding: 0.375rem 0.75rem; border-radius: 0.375rem; margin: 0;">
                                                    <span>
                                                        <i class="fa-solid fa-check-circle me-1"></i>
                                                        {{ $productStatusSuccess[$groupType] }}
                                                    </span>
                                                    <button type="button"
                                                            class="btn btn-sm btn-link p-0"
                                                            wire:click="clearProductStatusMessage('{{ $groupType }}')"
                                                            style="line-height: 1; text-decoration: none;">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </div>
                                            @endif
                                            @if(!empty($productStatusErrors[$groupType] ?? null))
                                                <div class="alert alert-danger mb-0 d-flex align-items-center justify-content-between gap-2" style="font-size: 0.75rem; padding: 0.375rem 0.75rem; border-radius: 0.375rem; margin: 0;">
                                                    <span>
                                                        <i class="fa-solid fa-exclamation-circle me-1"></i>
                                                        {{ $productStatusErrors[$groupType] }}
                                                    </span>
                                                    <button type="button"
                                                            class="btn btn-sm btn-link p-0"
                                                            wire:click="clearProductStatusMessage('{{ $groupType }}')"
                                                            style="line-height: 1; text-decoration: none; color: inherit;">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </div>
                                            @endif
                                            <div class="position-relative" style="display: inline-block;">
                                                @php
                                                    $currentTextColor = $productStatusTextColors[$currentGroupStatus] ?? '#374151';
                                                    $borderColor = $currentTextColor . '33';
                                                @endphp
                                                @if($currentGroupStatus === 'rejected')
                                                <div wire:click.prevent="openRejectionDetailsModal"
                                                     class="d-inline-flex align-items-center gap-2"
                                                     style="background-color: {{ $productStatusColors[$currentGroupStatus] ?? '#fee2e2' }}; color: {{ $productStatusTextColors[$currentGroupStatus] ?? '#991b1b' }}; border: 2px solid {{ $borderColor }}; min-width: 180px; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 0.875rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"
                                                     onmouseover="this.style.backgroundColor='#fef2f2'; this.style.borderColor='#fca5a5';"
                                                     onmouseout="this.style.backgroundColor='{{ $productStatusColors[$currentGroupStatus] ?? '#fee2e2' }}'; this.style.borderColor='{{ $borderColor }}';"
                                                     title="Click to view rejection details">
                                                    <i class="fa-solid {{ $statusIcons[$currentGroupStatus] ?? 'fa-ban' }}" style="font-size: 0.875rem;"></i>
                                                    <span>{{ $productStatusLabels[$currentGroupStatus] ?? 'Rejected' }}</span>
                                                    <i class="fa-solid fa-info-circle ms-auto" style="font-size: 0.75rem;"></i>
                                                </div>
                                                @else
                                                <select wire:change="updateProductStatus('{{ $groupType }}', $event.target.value)"
                                                        class="form-select form-select-sm product-status-select"
                                                        id="product-status-{{ $groupType }}"
                                                        data-group-type="{{ $groupType }}"
                                                        data-current-status="{{ $currentGroupStatus }}"
                                                        data-pending-status=""
                                                        style="background-color: {{ $productStatusColors[$currentGroupStatus] ?? '#f3f4f6' }}; color: {{ $currentTextColor }}; border: 2px solid {{ $borderColor }}; min-width: 180px; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 2.75rem 0.5rem 0.875rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1); appearance: none; -webkit-appearance: none; -moz-appearance: none;"
                                                        onchange="handleProductStatusChange(this, '{{ $groupType }}')">
                                                    @foreach($productStatusLabels as $statusValue => $statusLabel)
                                                        <option value="{{ $statusValue }}" 
                                                                data-color="{{ $productStatusColors[$statusValue] }}"
                                                                data-text-color="{{ $productStatusTextColors[$statusValue] ?? '#374151' }}"
                                                                data-icon="{{ $statusIcons[$statusValue] }}"
                                                                {{ $currentGroupStatus === $statusValue ? 'selected' : '' }}
                                                                style="background: white; color: #374151; padding: 0.75rem; font-weight: 500;">
                                                            {{ $statusLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <i class="fa-solid {{ $statusIcons[$currentGroupStatus] ?? 'fa-clock' }} position-absolute status-icon-{{ $groupType }}" 
                                                   style="right: 0.875rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: {{ $productStatusTextColors[$currentGroupStatus] ?? '#374151' }}; font-size: 0.875rem; z-index: 10;"></i>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                @if($currentGroupStatus === 'rejected' && $rejected_note)
                                <div class="px-3 py-2" style="background-color: #fef2f2; border-bottom: 1px solid #fecaca;">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="fa-solid fa-ban text-danger mt-1" style="font-size: 0.875rem;"></i>
                                        <div style="flex: 1;">
                                            <label class="form-label fw-semibold mb-1" style="font-size: 0.8125rem; color: #991b1b; margin: 0;">
                                                Rejection Reason:
                                            </label>
                                            <p class="mb-0" style="font-size: 0.8125rem; color: #374151; white-space: pre-wrap; word-wrap: break-word; line-height: 1.4;">{{ $rejected_note }}</p>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                <div class="table-responsive" style="overflow: visible;">
                                    <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                                        <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                            <tr>
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 350px; width: 45%;">Product</th>
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 120px;">Store Type</th>
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 100px;">Category</th>
                                                @if($groupType === 'lpo')
                                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 150px;">Supplier</th>
                                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 140px;">Status</th>
                                                @endif
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 70px;">Unit</th>
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                                <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($groupedProducts[$groupType] as $groupProduct)
                                                @php
                                                    $index = $groupProduct['index'] ?? 0;
                                                    $isCustom = $groupProduct['is_custom'] ?? 0;
                                                    $productId = $groupProduct['product_id'] ?? '';
                                                    // Fix: Ensure we find the product by converting to string for comparison
                                                    $selectedProduct = !$isCustom && !empty($productId) ? $products->first(function($p) use ($productId) {
                                                        return (string)$p->id === (string)$productId;
                                                    }) : null;
                                                    $product = $groupProduct; // Use groupProduct as product for consistency

                                                    // Highlight custom products in Warehouse section as a grey sub-section
                                                    $isWarehouseCustomRow = ($groupType === 'warehouse' && $isCustom);
                                                @endphp
                                                @if($isWarehouseCustomRow)
                                                {{-- Full-width sub header row for Warehouse custom product --}}
                                                <tr style="background-color: #dbeafe;">
                                                    <td colspan="7" style="padding: 0.6rem 0.9rem; background-color: #dbeafe; border-top: 1px solid #bfdbfe; border-bottom: 1px solid #bfdbfe;">
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <i class="fa-solid fa-box-open" style="font-size: 0.85rem; color: #1d4ed8;"></i>
                                                            <span style="font-size: 0.8125rem; font-weight: 700; color: #111827;">
                                                                Workshop Custom Product
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endif
                                                <tr wire:key="product-row-{{ $index }}" style="vertical-align: middle; border-bottom: 1px solid #e5e7eb; transition: background-color 0.15s ease; @if($isWarehouseCustomRow) background-color: #eff6ff; @endif">
                                                    <td style="padding: 1rem 0.75rem; text-align: center; vertical-align: middle;">
                                                        <div style="width: 50px; height: 50px; background: {{ $isWarehouseCustomRow ? '#dbeafe' : '#f9fafb' }}; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 1px solid #bfdbfe;">
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
                                                                    <img src="{{ $firstImageUrl }}" alt="Custom Product" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">
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
                                                                    <img src="{{ $imageUrl }}" alt="{{ $selectedProduct->product_name ?? '' }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">
                                                                @else
                                                                    <i class="fa-solid fa-image text-gray-400" style="font-size: 1.25rem;"></i>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td style="padding: 1rem 0.75rem; vertical-align: middle; position: relative; overflow: visible;">
                                                        @if($isCustom)
                                                            <input type="text" wire:model="orderProducts.{{ $index }}.custom_note" class="form-control form-control-solid @error('orderProducts.'.$index.'.custom_note') is-invalid @enderror" placeholder="Enter custom product name..." style="font-size: 0.9375rem; padding: 0.625rem 0.875rem; border: 1px solid #bfdbfe; border-radius: 0.5rem; width: 100%; height: 40px; line-height: 1.5; background-color: {{ $isWarehouseCustomRow ? '#eff6ff' : 'white' }};">
                                                            @error("orderProducts.{$index}.custom_note") 
                                                                <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444;">{{ $message }}</div> 
                                                            @enderror
                                                                
                                                                {{-- Connected Products Sub-case UI --}}
                                                                @php
                                                                    // Get product_ids for wire:key to force refresh
                                                                    $productIdsForKey = $orderProducts[$index]['product_ids'] ?? [];
                                                                    $productIdsKey = is_array($productIdsForKey) ? implode(',', $productIdsForKey) : '';
                                                                    $connectedProductsKey = md5($productIdsKey . '-' . $index);
                                                                @endphp
                                                                <div wire:key="connected-products-{{ $index }}-{{ $connectedProductsKey }}">
                                                                @php
                                                                    try {
                                                                        $connectedProducts = $this->getConnectedProductsForCustomProduct($index);
                                                                    } catch (\Exception $e) {
                                                                        $connectedProducts = [];
                                                                    }
                                                                    $hasConnectedProducts = !empty($connectedProducts);
                                                                    $isExpanded = $expandedCustomProducts[$index] ?? false;
                                                                    $connectedCount = count($connectedProducts);
                                                                @endphp
                                                                <div style="margin-top: 0.75rem; border-top: 1px solid #bfdbfe; padding-top: 0.75rem;">
                                                                    <button type="button" 
                                                                            wire:click="toggleCustomProductExpanded({{ $index }})"
                                                                            class="btn btn-sm btn-link p-0 d-flex align-items-center gap-2"
                                                                            style="text-decoration: none; color: #10b981; font-weight: 600; font-size: 0.875rem; border: none; background: none; cursor: pointer;"
                                                                            onmouseover="this.style.color='#059669'" 
                                                                            onmouseout="this.style.color='#10b981'">
                                                                        <i class="fa-solid fa-chevron-{{ $isExpanded ? 'down' : 'right' }}" style="font-size: 0.75rem; transition: transform 0.2s;"></i>
                                                                        <span>
                                                                            <i class="fa-solid fa-link me-1" style="font-size: 0.75rem;"></i>
                                                                            Connected Workshop Products
                                                                        </span>
                                                                        @if($connectedCount > 0)
                                                                            <span class="badge" style="background: #10b981; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-weight: 600;">
                                                                                {{ $connectedCount }}
                                                                            </span>
                                                                        @else
                                                                            <span class="badge" style="background: #9ca3af; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.375rem;">
                                                                                None
                                                                            </span>
                                                                        @endif
                                                                    </button>
                                                                    
                                                                    @if($isExpanded)
                                                                        <div style="margin-top: 0.75rem; background: {{ $isWarehouseCustomRow ? '#eff6ff' : '#f3f4f6' }}; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.75rem;">
                                                                            @if($hasConnectedProducts)
                                                                                <div style="margin-bottom: 0.5rem; padding: 0.35rem 0.75rem; border-radius: 0.5rem; background-color: #dbeafe; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; gap: 0.5rem;">
                                                                                    <i class="fa-solid fa-warehouse" style="color: #1d4ed8; font-size: 0.8rem;"></i>
                                                                                    <span style="font-size: 0.8125rem; font-weight: 700; color: #111827;">
                                                                                    Workshop Store Products Connected to This Custom Product
                                                                                    </span>
                                                                                </div>
                                                                                <div class="table-responsive" style="border-radius: 0.375rem; overflow: hidden;">
                                                                                    <table class="table table-sm table-bordered mb-0" style="background: white; margin: 0;">
                                                                                        <thead style="background: #f3f4f6;">
                                                                                            <tr>
                                                                                                <th style="padding: 0.5rem; font-size: 0.75rem; font-weight: 600; text-align: center; width: 50px; border-bottom: 2px solid #e5e7eb;">Image</th>
                                                                                                <th style="padding: 0.5rem; font-size: 0.75rem; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Product</th>
                                                                                                <th style="padding: 0.5rem; font-size: 0.75rem; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Category</th>
                                                                                                <th style="padding: 0.5rem; font-size: 0.75rem; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Unit</th>
                                                                                                <th style="padding: 0.5rem; font-size: 0.75rem; font-weight: 600; border-bottom: 2px solid #e5e7eb; width: 90px;">Qty</th>
                                                                                                <th style="padding: 0.5rem; font-size: 0.75rem; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Materials</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            @foreach($connectedProducts as $connectedProduct)
                                                                                                <tr style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s;" 
                                                                                                    onmouseover="this.style.backgroundColor='#f9fafb'" 
                                                                                                    onmouseout="this.style.backgroundColor='white'">
                                                                                                    <td style="padding: 0.5rem; text-align: center; vertical-align: middle;">
                                                                                                        <div style="width: 40px; height: 40px; background: #f9fafb; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 1px solid #e5e7eb; overflow: hidden;">
                                                                                                            @if($connectedProduct['image_url'] ?? null)
                                                                                                                <img src="{{ $connectedProduct['image_url'] }}" 
                                                                                                                     alt="{{ $connectedProduct['name'] }}"
                                                                                                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.375rem;">
                                                                                                            @else
                                                                                                                <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                                                                                            @endif
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td style="padding: 0.5rem; font-size: 0.8125rem; color: #1f2937; font-weight: 500; vertical-align: middle;">
                                                                                                        {{ $connectedProduct['name'] }}
                                                                                                    </td>
                                                                                                    <td style="padding: 0.5rem; font-size: 0.8125rem; color: #6b7280; vertical-align: middle;">
                                                                                                        <span style="background: #e5e7eb; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem;">
                                                                                                            {{ $connectedProduct['category'] }}
                                                                                                        </span>
                                                                                                    </td>
                                                                                                    <td style="padding: 0.5rem; font-size: 0.8125rem; color: #6b7280; vertical-align: middle;">
                                                                                                        {{ $connectedProduct['unit'] }}
                                                                                                    </td>
                                                                                                    <td style="padding: 0.5rem; font-size: 0.8125rem; color: #111827; vertical-align: middle; text-align: center;">
                                                                                                        @php
                                                                                                            $qty = $connectedProduct['quantity'] ?? null;
                                                                                                        @endphp
                                                                                                        <span style="font-weight: 600;">
                                                                                                            {{ $qty !== null ? formatQty($qty) : '—' }}
                                                                                                        </span>
                                                                                                    </td>
                                                                                                    <td style="padding: 0.5rem; font-size: 0.75rem; color: #4b5563; vertical-align: middle;">
                                                                                                        @if(!empty($connectedProduct['materials_summary']))
                                                                                                            <span title="{{ $connectedProduct['materials_summary'] }}">
                                                                                                                {{ \Illuminate\Support\Str::limit($connectedProduct['materials_summary'], 80) }}
                                                                                                            </span>
                                                                                                        @else
                                                                                                            <span class="text-muted">—</span>
                                                                                                        @endif
                                                                                                    </td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            @else
                                                                                <div style="text-align: center; padding: 1rem; color: #6b7280; font-size: 0.875rem;">
                                                                                    <i class="fa-solid fa-info-circle mb-2" style="font-size: 1.25rem; color: #9ca3af;"></i>
                                                                                    <div style="margin-top: 0.5rem;">
                                                                                        No warehouse products connected yet.
                                                                                    </div>
                                                                                    <div style="margin-top: 0.25rem; font-size: 0.8125rem; color: #9ca3af;">
                                                                                        Click the <i class="fa-solid fa-pencil" style="font-size: 0.75rem;"></i> Edit button above to add connected products.
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                                                                <div style="min-height: 42px; position: relative; z-index: auto;">
                                                                    <div class="position-relative" style="width: 100%; z-index: auto; overflow: visible;">
                                                                        @php
                                                                            $currentProductId = isset($product['product_id']) ? (string)$product['product_id'] : '';
                                                                            // Fix: Ensure proper type conversion for product lookup
                                                                            $selectedProduct = null;
                                                                            if (!empty($currentProductId)) {
                                                                                $selectedProduct = $products->first(function($p) use ($currentProductId) {
                                                                                    return (string)$p->id === (string)$currentProductId;
                                                                                });
                                                                            }
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
                                                                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('orderProducts.' . $index . '.product_id') is-invalid @enderror"
                                                                                style="height: 40px; text-align: left; background: white; border: 1px solid {{ $hasProductError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 1rem; min-width: 250px; width: 100%;">
                                                                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                                                                {{ $selectedProductName }}
                                                                            </span>
                                                                            <i class="fa-solid fa-chevron-{{ isset($productDropdownOpen[$index]) && $productDropdownOpen[$index] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                                                                        </button>
                                                                        
                                                                        @if(isset($productDropdownOpen[$index]) && $productDropdownOpen[$index])
                                                                            <div class="position-fixed bg-white border rounded shadow-lg product-dropdown-overlay" 
                                                                                 style="z-index: 999999; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; width: 300px; min-width: 300px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);"
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
                                                                                            <div>Searching...</div>
                                                                                        </div>
                                                                                    @elseif(empty($productSearchResults[$index] ?? []))
                                                                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                                            <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                                            <div>No products found</div>
                                                                                        </div>
                                                                                    @else
                                                                                        @foreach($productSearchResults[$index] ?? [] as $result)
                                                                                            <div wire:click="selectProduct({{ $index }}, {{ $result['id'] ?? 0 }})"
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
                                                                        $isLPO = false; // LPO removed
                                                                        // Show a more user-friendly type label, especially for Material as Product cases
                                                                        $typeLabel = '';
                                                                        $isProductFlag = (int)($selectedProduct->is_product ?? 0);
                                                                        if ($isProductFlag === 1) {
                                                                            $typeLabel = 'Material As Product';
                                                                        } elseif ($isProductFlag === 2) {
                                                                            $typeLabel = 'Material + Product';
                                                                        } elseif ($selectedProduct->type) {
                                                                            $typeLabel = $selectedProduct->type->getName();
                                                                        }
                                                                    @endphp
                                                                    <div style="line-height: 1.4; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                                                        @if($typeLabel)
                                                                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                                                                <i class="fa-solid fa-tag" style="font-size: 0.75rem; color: #6366f1;"></i>
                                                                                <small class="text-muted" style="font-size: 0.8125rem; color: #6b7280;">Type: <strong style="color: #6366f1; font-weight: 600;">{{ $typeLabel }}</strong></small>
                                                                            </div>
                                                                        @endif
                                                                        @if(!$isLPO)
                                                                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                                                                <i class="fa-solid fa-box" style="font-size: 0.75rem; color: #059669;"></i>
                                                                                <small class="text-muted" style="font-size: 0.8125rem; color: #6b7280;">Available Stock: <strong style="color: #059669; font-weight: 600;">{{ formatQty($this->getCurrentStockForProduct($product['product_id'], $site_id)) }}</strong></small>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td style="padding: 1rem 0.75rem;">
                                                        @if(!$isCustom)
                                                            @if($selectedProduct)
                                                                @php
                                                                    $categoryName = '';
                                                                    if ($selectedProduct->category_id && isset($selectedProduct->category)) {
                                                                        $categoryName = $selectedProduct->category->name ?? '';
                                                                    }
                                                                @endphp
                                                                <input type="text" value="{{ $categoryName }}" class="form-control form-control-solid" readonly disabled style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                                            @else
                                                                <input type="text" value="" class="form-control form-control-solid" readonly disabled placeholder="Select product first" style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                                            @endif
                                                        @else
                                                            <input type="text" value="Custom Product" class="form-control form-control-solid" readonly disabled style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                                        @endif
                                                    </td>
                                                    <td style="padding: 1rem 0.75rem;">
                                                        @if(!$isCustom)
                                                            @if($selectedProduct)
                                                                @php
                                                                    $storeName = '';
                                                                    if ($selectedProduct->store) {
                                                                        $storeName = $selectedProduct->store->getName();
                                                                    }
                                                                @endphp
                                                                <input type="text" value="{{ $storeName }}" class="form-control form-control-solid" readonly disabled style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                                            @else
                                                                <input type="text" value="" class="form-control form-control-solid" readonly disabled placeholder="Select product first" style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                                            @endif
                                                        @else
                                                            <input type="text" value="Custom Product" class="form-control form-control-solid" readonly disabled style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                                        @endif
                                                    </td>
                                                    @if($groupType === 'lpo')
                                                    <td style="padding: 1rem 0.75rem;">
                                                        @if(!$isCustom)
                                                            @php
                                                                $currentSupplierId = $product['supplier_id'] ?? null;
                                                                $selectedSupplier = null;
                                                                $supplierName = 'Select Supplier';
                                                                
                                                                if ($currentSupplierId) {
                                                                    // First try to find in search results
                                                                    if (isset($supplierSearchResults[$index]) && !empty($supplierSearchResults[$index])) {
                                                                        $selectedSupplier = collect($supplierSearchResults[$index])->firstWhere('id', (int)$currentSupplierId);
                                                                    }
                                                                    
                                                                    // If not found in search results, try to load from database
                                                                    if (!$selectedSupplier) {
                                                                        $supplier = \App\Models\Supplier::find((int)$currentSupplierId);
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
                                                                             index: {{ (int)($index ?? 0) }},
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
                                                        @else
                                                            <input type="text" value="N/A" class="form-control form-control-solid" readonly disabled style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                                        @endif
                                                    </td>
                                                    <td style="padding: 1rem 0.75rem;">
                                                        @php
                                                            // Supplier-wise LPO status (pending/approved/outfordelivery/delivered/rejected)
                                                            $supplierStatusKey = 'pending';
                                                            if (!empty($currentSupplierId) && !empty($productStatuses['lpo']) && is_array($productStatuses['lpo'])) {
                                                                $supplierStatusKey = $productStatuses['lpo'][(string)$currentSupplierId] ?? 'pending';
                                                            }
                                                        @endphp
                                                        <select 
                                                            class="form-select form-select-sm"
                                                            wire:change="updateLpoSupplierStatus({{ $index }}, $event.target.value)"
                                                            style="min-width: 120px; font-size: 0.85rem; border-radius: 0.5rem; height: 40px;">
                                                            @foreach($productStatusLabels as $statusValue => $statusLabel)
                                                                <option 
                                                                    value="{{ $statusValue }}"
                                                                    {{ $supplierStatusKey === $statusValue ? 'selected' : '' }}>
                                                                    {{ $statusLabel }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    @endif
                                                    <td style="padding: 1rem 0.75rem;">
                                                        @if(!$isCustom)
                                                            @if($selectedProduct)
                                                                @php
                                                                    $unitType = $selectedProduct->unit_type ?? '';
                                                                @endphp
                                                                <input type="text" value="{{ $unitType }}" class="form-control form-control-solid" readonly disabled style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                                            @else
                                                                <input type="text" value="" class="form-control form-control-solid" readonly disabled placeholder="Select product first" style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                                            @endif
                                                        @else
                                                            @php
                                                                $customImages = $product['custom_images'] ?? [];
                                                                if (!is_array($customImages)) {
                                                                    $customImages = $customImages ? [$customImages] : [];
                                                                }
                                                                $hasImages = !empty($customImages);
                                                            @endphp
                                                            <div style="display: flex; gap: 0.375rem; align-items: center; flex-wrap: wrap;">
                                                                <label style="margin: 0; cursor: pointer; display: inline-block; flex-shrink: 0;">
                                                                    <input type="file" wire:model="orderProducts.{{ $index }}.custom_images" accept="image/jpeg,image/jpg,image/png" multiple class="d-none" id="custom-image-input-{{ $index }}">
                                                                    <button type="button" onclick="document.getElementById('custom-image-input-{{ $index }}').click();" class="btn btn-sm btn-light" style="height: 32px; padding: 0.375rem 0.625rem; display: flex; align-items: center; justify-content: center; gap: 0.25rem; border: 1px solid #d1d5db; border-radius: 0.375rem; white-space: nowrap;" title="Upload Images">
                                                                        <i class="fa-solid fa-image" style="font-size: 0.75rem;"></i>
                                                                        <span style="font-size: 0.75rem;">Choose</span>
                                                                    </button>
                                                                </label>
                                                                @if($hasImages)
                                                                    @foreach($customImages as $imgIndex => $customImage)
                                                                        @if(is_string($customImage))
                                                                            <div style="position: relative; display: inline-block; flex-shrink: 0;">
                                                                                <a href="{{ \Storage::url($customImage) }}" target="_blank" style="display: block;">
                                                                                    <img src="{{ \Storage::url($customImage) }}" alt="Image {{ $imgIndex + 1 }}" style="width: 28px; height: 28px; object-fit: cover; border-radius: 0.25rem; border: 1px solid #e5e7eb; cursor: pointer;">
                                                                                </a>
                                                                                <button type="button" wire:click="removeCustomImage({{ $index }}, {{ $imgIndex }})" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.65rem; padding: 0; line-height: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">×</button>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                @endif
                                                            </div>
                                                            @error("orderProducts.{$index}.custom_images") 
                                                                <div class="text-danger small mt-1" style="font-size: 0.7rem; line-height: 1.3; color: #ef4444;">{{ $message }}</div> 
                                                            @enderror
                                                            @error("orderProducts.{$index}.custom_images.*") 
                                                                <div class="text-danger small mt-1" style="font-size: 0.7rem; line-height: 1.3; color: #ef4444;">{{ $message }}</div> 
                                                            @enderror
                                                        @endif
                                                    </td>
                                                    <td style="padding: 1rem 0.75rem;">
                                                        @if($isCustom)
                                                            {{-- Custom product row does not use quantity --}}
                                                            <div class="text-center text-gray-400" style="font-size: 0.75rem;">
                                                                —
                                                            </div>
                                                        @else
                                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                                <button type="button" wire:click="decrementQuantity({{ $index }})" class="btn btn-sm btn-light" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;"><i class="fa-solid fa-minus" style="font-size: 0.75rem;"></i></button>
                                                                @php
                                                                    $hasQuantityError = $errors->has('orderProducts.' . $index . '.quantity');
                                                                    $borderColor = $hasQuantityError ? '#ef4444' : '#e5e7eb';
                                                                @endphp
                                                                <input type="number" wire:model.live="orderProducts.{{ $index }}.quantity" step="1" min="1" class="form-control form-control-solid @error('orderProducts.' . $index . '.quantity') is-invalid @enderror" style="width: 80px; height: 32px; text-align: center; padding: 0.25rem; border: 1px solid {{ $borderColor }};">
                                                                <button type="button" wire:click="incrementQuantity({{ $index }})" class="btn btn-sm btn-light" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;"><i class="fa-solid fa-plus" style="font-size: 0.75rem;"></i></button>
                                                            </div>
                                                            @error('orderProducts.' . $index . '.quantity')
                                                                <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444; font-weight: 500; text-align: center;">{{ $message }}</div>
                                                            @enderror
                                                        @endif
                                                    </td>
                                                    <td style="padding: 1rem 0.75rem; text-align: center;">
                                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                                            @if($isCustom)
                                                                {{-- Edit button for custom products --}}
                                                                <button type="button" 
                                                                        wire:click="openCustomProductModal({{ $index }})"
                                                                        class="btn btn-sm btn-icon btn-light-info"
                                                                        title="Edit Custom Product"
                                                                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                                    <i class="fa-solid fa-pencil" style="font-size: 0.875rem;"></i>
                                                                </button>
                                                                @if(!$isEditMode || $status === 'pending')
                                                                    <button type="button" 
                                                                            wire:click="removeProductRow({{ $index }})"
                                                                            wire:target="removeProductRow"
                                                                            onclick="if(!confirm('Are you sure you want to delete this product?')) { event.stopImmediatePropagation(); return false; }"
                                                                            class="btn btn-sm btn-icon btn-light-danger"
                                                                            title="Delete Custom Product"
                                                                            style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                                        <span>
                                                                            <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                                                        </span>
                                                                    </button>
                                                                @endif
                                                            @else
                                                                @if(!$isEditMode || $status === 'pending')
                                                                    <button type="button" 
                                                                            wire:click="addProductRowToGroup('{{ $groupType }}')"
                                                                            wire:target="addProductRowToGroup"
                                                                            class="btn btn-sm btn-icon btn-light-primary"
                                                                            title="Add Product to {{ $groupLabels[$groupType] }}"
                                                                            style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                                        <span>
                                                                            <i class="fa-solid fa-plus" style="font-size: 0.875rem;"></i>
                                                                        </span>
                                                                    </button>
                                                                    <button type="button" 
                                                                            wire:click="removeProductRow({{ $index }})"
                                                                            wire:target="removeProductRow"
                                                                            onclick="if(!confirm('Are you sure you want to delete this product?')) { event.stopImmediatePropagation(); return false; }"
                                                                            class="btn btn-sm btn-icon btn-light-danger"
                                                                            title="Delete Row"
                                                                            style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                                        <span>
                                                                            <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                                                        </span>
                                                                    </button>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    </div>
                @else
                    {{-- Display normal list for create mode --}}
                    <div class="table-responsive order-table-container" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: visible; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff;">
                        <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                            <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                <tr>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 350px; width: 45%;">Product</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 120px;">Store Type</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 100px;">Category</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 70px;">Unit</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orderProducts as $index => $product)
                                @php
                                    $isCustom = $product['is_custom'] ?? 0;
                                    // Fix: Ensure proper type conversion for product lookup
                                    $productId = $product['product_id'] ?? '';
                                    $selectedProduct = null;
                                    if (!$isCustom && !empty($productId)) {
                                        $selectedProduct = $products->first(function($p) use ($productId) {
                                            return (string)$p->id === (string)$productId;
                                        });
                                    }
                                @endphp
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
                                    <td style="padding: 1rem 0.75rem; vertical-align: middle;">
                                        @if($isCustom)
                                            <input type="text" wire:model="orderProducts.{{ $index }}.custom_note" class="form-control form-control-solid @error('orderProducts.'.$index.'.custom_note') is-invalid @enderror" placeholder="Enter custom product name..." style="font-size: 0.9375rem; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; width: 100%; height: 40px; line-height: 1.5;">
                                            @error("orderProducts.{$index}.custom_note") 
                                                <div class="text-danger small mt-1" style="font-size: 0.8125rem; line-height: 1.4; color: #ef4444;">{{ $message }}</div> 
                                            @enderror
                                        @else
                                            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                                                <div style="min-height: 42px; position: relative;">
                                                    <div class="position-relative" style="width: 100%;">
                                                                        @php
                                                                            $currentProductId = isset($product['product_id']) ? (string)$product['product_id'] : '';
                                                                            // Fix: Ensure proper type conversion for product lookup
                                                                            $selectedProduct = null;
                                                                            if (!empty($currentProductId)) {
                                                                                $selectedProduct = $products->first(function($p) use ($currentProductId) {
                                                                                    return (string)$p->id === (string)$currentProductId;
                                                                                });
                                                                            }
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
                                                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('orderProducts.' . $index . '.product_id') is-invalid @enderror"
                                                                style="height: 40px; text-align: left; background: white; border: 1px solid {{ $hasProductError ? '#ef4444' : '#e5e7eb' }}; border-radius: 0.5rem; padding: 0.5rem 1rem; min-width: 250px; width: 100%;">
                                                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                                                {{ $selectedProductName }}
                                                            </span>
                                                            <i class="fa-solid fa-chevron-{{ isset($productDropdownOpen[$index]) && $productDropdownOpen[$index] ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                                                        </button>
                                                        
                                                        @if(isset($productDropdownOpen[$index]) && $productDropdownOpen[$index])
                                                        <div class="position-fixed bg-white border rounded shadow-lg product-dropdown-overlay" 
                                                             style="z-index: 999999; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; width: 300px; min-width: 300px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);"
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
                                                                        <div>Searching...</div>
                                                                    </div>
                                                                @elseif(empty($productSearchResults[$index] ?? []))
                                                                    <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                                                        <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                                                        <div>No products found</div>
                                                                    </div>
                                                                @else
                                                                    @foreach($productSearchResults[$index] ?? [] as $result)
                                                                        <div wire:click="selectProduct({{ $index }}, {{ $result['id'] ?? 0 }})"
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
                                    <td style="padding: 1rem 0.75rem;">
                                        @if(!$isCustom)
                                            @if($selectedProduct)
                                                @php
                                                    // Get store type from product - display only, not stored
                                                    $storeName = '';
                                                    if ($selectedProduct->store) {
                                                        $storeName = $selectedProduct->store->getName();
                                                    }
                                                @endphp
                                                <input type="text" 
                                                       value="{{ $storeName }}"
                                                       class="form-control form-control-solid"
                                                       readonly
                                                       disabled
                                                       style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                            @else
                                                <input type="text" 
                                                       value=""
                                                       class="form-control form-control-solid"
                                                       readonly
                                                       disabled
                                                       placeholder="Select product first"
                                                       style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                            @endif
                                        @else
                                            <input type="text" 
                                                   value="Custom Product"
                                                   class="form-control form-control-solid"
                                                   readonly
                                                   disabled
                                                   style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                        @endif
                                    </td>
                                    <td style="padding: 1rem 0.75rem;">
                                        @if(!$isCustom)
                                            @if($selectedProduct)
                                                @php
                                                    // Get category name from product - display only, not stored
                                                    $categoryName = '';
                                                    // Get category name - products collection has categories loaded
                                                    if ($selectedProduct->category_id && isset($selectedProduct->category)) {
                                                        $categoryName = $selectedProduct->category->name ?? '';
                                                    }
                                                @endphp
                                                <input type="text" 
                                                       value="{{ $categoryName }}"
                                                       class="form-control form-control-solid"
                                                       readonly
                                                       disabled
                                                       style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                            @else
                                                <input type="text" 
                                                       value=""
                                                       class="form-control form-control-solid"
                                                       readonly
                                                       disabled
                                                       placeholder="Select product first"
                                                       style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                            @endif
                                        @else
                                            <input type="text" 
                                                   value="Custom Product"
                                                   class="form-control form-control-solid"
                                                   readonly
                                                   disabled
                                                   style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                        @endif
                                    </td>
                                   
                                    <td style="padding: 1rem 0.75rem;">
                                        @if(!$isCustom)
                                            @if($selectedProduct)
                                                @php
                                                    // Get unit type from product - display only, not stored
                                                    $unitType = $selectedProduct->unit_type ?? '';
                                                @endphp
                                                <input type="text" 
                                                       value="{{ $unitType }}"
                                                       class="form-control form-control-solid"
                                                       readonly
                                                       disabled
                                                       style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                            @else
                                                <input type="text" 
                                                       value=""
                                                       class="form-control form-control-solid"
                                                       readonly
                                                       disabled
                                                       placeholder="Select product first"
                                                       style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb; color: #9ca3af;">
                                            @endif
                                        @else
                                            @php
                                                $customImages = $product['custom_images'] ?? [];
                                                if (!is_array($customImages)) {
                                                    $customImages = $customImages ? [$customImages] : [];
                                                }
                                                $hasImages = !empty($customImages);
                                            @endphp
                                            <div style="display: flex; gap: 0.375rem; align-items: center; flex-wrap: wrap;">
                                                <label style="margin: 0; cursor: pointer; display: inline-block; flex-shrink: 0;">
                                                    <input type="file" wire:model="orderProducts.{{ $index }}.custom_images" accept="image/jpeg,image/jpg,image/png" multiple class="d-none" id="custom-image-input-create-{{ $index }}">
                                                    <button type="button" onclick="document.getElementById('custom-image-input-create-{{ $index }}').click();" class="btn btn-sm btn-light" style="height: 32px; padding: 0.375rem 0.625rem; display: flex; align-items: center; justify-content: center; gap: 0.25rem; border: 1px solid #d1d5db; border-radius: 0.375rem; white-space: nowrap;" title="Upload Images">
                                                        <i class="fa-solid fa-image" style="font-size: 0.75rem;"></i>
                                                        <span style="font-size: 0.75rem;">Choose</span>
                                                    </button>
                                                </label>
                                                @if($hasImages)
                                                    @foreach($customImages as $imgIndex => $customImage)
                                                        @if(is_string($customImage))
                                                            <div style="position: relative; display: inline-block; flex-shrink: 0;">
                                                                <a href="{{ \Storage::url($customImage) }}" target="_blank" style="display: block;">
                                                                    <img src="{{ \Storage::url($customImage) }}" alt="Image {{ $imgIndex + 1 }}" style="width: 28px; height: 28px; object-fit: cover; border-radius: 0.25rem; border: 1px solid #e5e7eb; cursor: pointer;">
                                                                </a>
                                                                <button type="button" wire:click="removeCustomImage({{ $index }}, {{ $imgIndex }})" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.65rem; padding: 0; line-height: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">×</button>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </div>
                                            @error("orderProducts.{$index}.custom_images") 
                                                <div class="text-danger small mt-1" style="font-size: 0.7rem; line-height: 1.3; color: #ef4444;">{{ $message }}</div> 
                                            @enderror
                                            @error("orderProducts.{$index}.custom_images.*") 
                                                <div class="text-danger small mt-1" style="font-size: 0.7rem; line-height: 1.3; color: #ef4444;">{{ $message }}</div> 
                                            @enderror
                                        @endif
                                    </td>
                                    <td style="padding: 1rem 0.75rem;">
                                        @if($isCustom)
                                            {{-- Custom product row does not use quantity --}}
                                            <div class="text-center text-gray-400" style="font-size: 0.75rem;">
                                                —
                                            </div>
                                        @else
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                <button type="button" 
                                                        wire:click="decrementQuantity({{ $index }})"
                                                        class="btn btn-sm btn-light"
                                                        style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;">
                                                    <i class="fa-solid fa-minus" style="font-size: 0.75rem;"></i>
                                                </button>
                                                @php
                                                    $hasQuantityError = $errors->has('orderProducts.' . $index . '.quantity');
                                                    $borderColor = $hasQuantityError ? '#ef4444' : '#e5e7eb';
                                                @endphp
                                                <input type="number" 
                                                       wire:model.live="orderProducts.{{ $index }}.quantity"
                                                       step="1"
                                                       min="1"
                                                       class="form-control form-control-solid @error('orderProducts.' . $index . '.quantity') is-invalid @enderror"
                                                       style="width: 80px; height: 32px; text-align: center; padding: 0.25rem; border: 1px solid {{ $borderColor }};">
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
                                        @endif
                                    </td>
                                    <td style="padding: 1rem 0.75rem; text-align: center;">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            @if($loop->last && (!$isEditMode || ($isEditMode && $status === 'pending')))
                                                <button type="button" 
                                                        wire:click="addProductRow"
                                                        wire:target="addProductRow"
                                                        class="btn btn-sm btn-icon btn-light-primary"
                                                        title="Add Row"
                                                        style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                    <span>
                                                        <i class="fa-solid fa-plus" style="font-size: 0.875rem;"></i>
                                                    </span>
                                                </button>
                                            @endif
                                            @if(!$isEditMode || ($status ?? 'pending') === 'pending')
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
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        No items added. Click "+" to add items.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif
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
                        style="height: 44px; border-radius: 0.5rem; min-width: 100px;">
                    Cancel
                </button>
                <button type="button" 
                        wire:click="save" 
                        class="btn btn-primary fw-semibold px-4 d-flex align-items-center justify-content-center" 
                        style="background: #1e3a8a; border: none; height: 44px; border-radius: 0.5rem; min-width: 120px; color: #ffffff;">
                    <span class="d-flex align-items-center">
                        <i class="fa-solid fa-{{ $isEditMode ? 'check' : 'plus' }} me-2"></i>
                        {{ $isEditMode ? 'Update' : 'Add Order' }}
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
                        <button type="button" class="btn btn-primary" wire:click="saveInTransitDetails" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem; background: #1e3a8a; border: none;">
                            <span>
                                <i class="fa-solid fa-check me-2"></i>Save Details
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Product Status Out For Delivery Details Modal -->
        @if($showProductOutForDeliveryModal)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5); z-index: 999998;" wire:ignore.self wire:click.self="closeProductOutForDeliveryModal">
            <div class="modal-dialog modal-dialog-centered" role="document" wire:click.stop>
                <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;" wire:click.stop>
                    <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                        <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                            <i class="fa-solid fa-truck-fast text-primary me-2"></i>
                            Add Driver & Vehicle Details for Out for Delivery
                            @if($productOutForDeliveryType)
                                <span class="badge bg-primary ms-2" style="text-transform: capitalize;">{{ $productOutForDeliveryType }}</span>
                            @endif
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeProductOutForDeliveryModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <p class="text-gray-700 mb-4" style="font-size: 0.9375rem;">
                            Please provide driver and vehicle details for <strong style="text-transform: capitalize;">{{ $productOutForDeliveryType ?? 'this product type' }}</strong> products going out for delivery.
                        </p>
                        <div class="mb-3">
                            <label for="productOutForDeliveryDriverName" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Driver Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text"
                                id="productOutForDeliveryDriverName"
                                wire:model="productOutForDeliveryDriverName"
                                wire:keydown.enter.prevent="saveProductOutForDeliveryDetails"
                                class="form-control form-control-solid @error('productOutForDeliveryDriverName') is-invalid @enderror" 
                                placeholder="Enter driver name"
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem;"
                                required
                                autofocus>
                            @error('productOutForDeliveryDriverName')
                                <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="productOutForDeliveryVehicleNumber" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Vehicle Number <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text"
                                id="productOutForDeliveryVehicleNumber"
                                wire:model="productOutForDeliveryVehicleNumber"
                                wire:keydown.enter.prevent="saveProductOutForDeliveryDetails"
                                class="form-control form-control-solid @error('productOutForDeliveryVehicleNumber') is-invalid @enderror" 
                                placeholder="Enter vehicle number"
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem;"
                                required>
                            @error('productOutForDeliveryVehicleNumber')
                                <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                            @enderror
                        </div>
                        @if(session()->has('product_status_error'))
                            <div class="alert alert-danger mb-3" style="font-size: 0.875rem; padding: 0.75rem; border-radius: 0.5rem;">
                                <i class="fa-solid fa-exclamation-circle me-2"></i>
                                {{ session('product_status_error') }}
                            </div>
                        @endif
                        @if(session()->has('product_status_updated'))
                            <div class="alert alert-success mb-3" style="font-size: 0.875rem; padding: 0.75rem; border-radius: 0.5rem;">
                                <i class="fa-solid fa-check-circle me-2"></i>
                                {{ session('product_status_updated') }}
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff; border-radius: 0 0 0.75rem 0.75rem;">
                        <button type="button" 
                                class="btn btn-light-secondary" 
                                wire:click="closeProductOutForDeliveryModal" 
                                style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                            <span>
                                Cancel
                            </span>
                        </button>
                        <button type="button" 
                                class="btn btn-primary" 
                                wire:click="saveProductOutForDeliveryDetails" 
                                wire:target="saveProductOutForDeliveryDetails"
                                style="border-radius: 0.5rem; padding: 0.625rem 1.25rem; background: #1e3a8a; border: none; min-width: 140px;">
                            <span>
                                <i class="fa-solid fa-check me-2"></i>Save Details
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Product Status In Transit Details Modal -->
        @if($showProductInTransitModal)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5); z-index: 999998;" wire:ignore.self wire:click.self="closeProductInTransitModal">
            <div class="modal-dialog modal-dialog-centered" role="document" wire:click.stop>
                <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;" wire:click.stop>
                    <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                        <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                            <i class="fa-solid fa-truck text-primary me-2"></i>
                            Add Driver & Vehicle Details
                            @if($productInTransitType)
                                <span class="badge bg-info ms-2" style="text-transform: capitalize;">{{ $productInTransitType }}</span>
                            @endif
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeProductInTransitModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <p class="text-gray-700 mb-4" style="font-size: 0.9375rem;">
                            Please provide driver and vehicle details for <strong style="text-transform: capitalize;">{{ $productInTransitType ?? 'this product type' }}</strong> products.
                        </p>
                        <div class="mb-3">
                            <label for="productTempDriverName" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Driver Name <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text"
                                id="productTempDriverName"
                                wire:model="productTempDriverName"
                                wire:keydown.enter.prevent="saveProductInTransitDetails"
                                class="form-control form-control-solid @error('productTempDriverName') is-invalid @enderror" 
                                placeholder="Enter driver name"
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem;"
                                required
                                autofocus>
                            @error('productTempDriverName')
                                <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="productTempVehicleNumber" class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Vehicle Number <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text"
                                id="productTempVehicleNumber"
                                wire:model="productTempVehicleNumber"
                                wire:keydown.enter.prevent="saveProductInTransitDetails"
                                class="form-control form-control-solid @error('productTempVehicleNumber') is-invalid @enderror" 
                                placeholder="Enter vehicle number"
                                style="border-radius: 0.5rem; border: 1px solid #e5e7eb; padding: 0.75rem; font-size: 0.9375rem;"
                                required>
                            @error('productTempVehicleNumber')
                                <div class="text-danger mt-1" style="font-size: 0.875rem;">{{ $message }}</div>
                            @enderror
                        </div>
                        @if(session()->has('product_status_error'))
                            <div class="alert alert-danger mb-3" style="font-size: 0.875rem; padding: 0.75rem; border-radius: 0.5rem;">
                                <i class="fa-solid fa-exclamation-circle me-2"></i>
                                {{ session('product_status_error') }}
                            </div>
                        @endif
                        @if(session()->has('product_status_updated'))
                            <div class="alert alert-success mb-3" style="font-size: 0.875rem; padding: 0.75rem; border-radius: 0.5rem;">
                                <i class="fa-solid fa-check-circle me-2"></i>
                                {{ session('product_status_updated') }}
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff; border-radius: 0 0 0.75rem 0.75rem;">
                        <button type="button" 
                                class="btn btn-light-secondary" 
                                wire:click="closeProductInTransitModal" 
                                style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                            <span>
                                Cancel
                            </span>
                        </button>
                        <button type="button" 
                                class="btn btn-primary" 
                                wire:click="saveProductInTransitDetails" 
                                wire:target="saveProductInTransitDetails"
                                style="border-radius: 0.5rem; padding: 0.625rem 1.25rem; background: #1e3a8a; border: none; min-width: 140px;">
                            <span>
                                <i class="fa-solid fa-check me-2"></i>Save Details
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Custom Product Edit Modal -->
        @if($showCustomProductModal)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background-color: rgba(0, 0, 0, 0.5); z-index: 999999;" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content shadow-lg" style="border-radius: 0.75rem; border: none;">
                    <div class="modal-header border-bottom" style="padding: 1.25rem 1.5rem; background: #fff;">
                        <h5 class="modal-title fw-bold" style="font-size: 1.25rem; color: #1e293b;">
                            <i class="fa-solid fa-edit text-success me-2"></i>
                            Edit Custom Product - Connected Products
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeCustomProductModal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
                        <div class="mb-4">
                            <label class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                Select Warehouse Products
                            </label>
                            <div class="position-relative">
                                <button type="button"
                                        wire:click="toggleCustomProductPopupDropdown"
                                        class="form-control form-control-solid d-flex align-items-center justify-content-between"
                                        style="height: 40px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem;">
                                    <span>Select products...</span>
                                    <i class="fa-solid fa-chevron-{{ $customProductPopupDropdownOpen ? 'up' : 'down' }} ms-2" style="font-size: 0.75rem; color: #6b7280;"></i>
                                </button>
                                
                                @if($customProductPopupDropdownOpen)
                                    <div class="position-absolute bg-white border rounded shadow-lg" 
                                         style="z-index: 10000; margin-top: 0.25rem; max-height: 300px; width: 100%; display: flex; flex-direction: column; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);"
                                         wire:click.stop
                                         x-data="{ 
                                             closeDropdown() {
                                                 $wire.call('toggleCustomProductPopupDropdown');
                                             }
                                         }"
                                         x-on:click.outside="closeDropdown()">
                                        <div style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb;">
                                            <input type="text"
                                                   wire:model.live.debounce.300ms="customProductPopupSearchTerm"
                                                   placeholder="Search products..."
                                                   class="form-control form-control-solid"
                                                   style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb;"
                                                   autofocus>
                                        </div>
                                        <div style="overflow-y: auto; max-height: 250px;"
                                             x-data="{
                                                 handleScroll(event) {
                                                     const el = event.target;
                                                     if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                                         if (typeof $wire !== 'undefined') {
                                                             $wire.call('loadMoreCustomProductPopup');
                                                         }
                                                     }
                                                 }
                                             }"
                                             x-on:scroll="handleScroll($event)">
                                            @if($customProductPopupLoading && empty($customProductPopupResults))
                                                <div class="text-center py-4 text-muted">
                                                    <div>Searching...</div>
                                                </div>
                                            @elseif(empty($customProductPopupResults))
                                                <div class="text-center py-4 text-muted">
                                                    <div>No products found</div>
                                                    @if(!$isEditMode || !$editingId)
                                                        <div style="font-size: 0.8125rem; margin-top: 0.25rem; color: #9ca3af;">
                                                            Add warehouse products to the order first
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                @foreach($customProductPopupResults as $result)
                                                    <div wire:click="selectProductInCustomPopup({{ $result['id'] }})"
                                                         wire:key="popup-product-{{ $result['id'] }}"
                                                         class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                                         style="cursor: pointer; transition: background-color 0.15s;"
                                                         onmouseover="this.style.backgroundColor='#f3f4f6'"
                                                         onmouseout="this.style.backgroundColor='transparent'">
                                                        @if(!empty($result['image_url']))
                                                            <img src="{{ $result['image_url'] }}" 
                                                                 alt="{{ $result['text'] }}"
                                                                 style="width: 32px; height: 32px; object-fit: cover; border-radius: 0.25rem; border: 1px solid #e5e7eb;">
                                                        @else
                                                            <div style="width: 32px; height: 32px; background: #f3f4f6; border-radius: 0.25rem; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;">
                                                                <i class="fa-solid fa-image text-gray-400" style="font-size: 0.75rem;"></i>
                                                            </div>
                                                        @endif
                                                        <div style="flex: 1;">
                                                            <div style="font-weight: 500; color: #1f2937;">{{ $result['text'] }}</div>
                                                            <div style="font-size: 0.875rem; color: #6b7280;">
                                                                {{ $result['category_name'] }} 
                                                                @if(!empty($result['unit_type']))
                                                                    • {{ $result['unit_type'] }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                @if($customProductPopupHasMore)
                                                    <div class="text-center py-2">
                                                        <button type="button" 
                                                                wire:click="loadMoreCustomProductPopup"
                                                                class="btn btn-sm btn-link text-primary"
                                                                style="font-size: 0.8125rem;">
                                                            Load more...
                                                        </button>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if(!empty($customProductPopupProducts))
                            <div class="mb-4">
                                <label class="form-label fw-semibold mb-2" style="color: #374151; font-size: 0.9375rem;">
                                    Selected Products
                                </label>
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead style="background: #f9fafb;">
                                            <tr>
                                                <th style="padding: 0.75rem; font-size: 0.875rem;">Product</th>
                                                <th style="padding: 0.75rem; font-size: 0.875rem;">Category</th>
                                                <th style="padding: 0.75rem; font-size: 0.875rem;">Unit</th>
                                                <th style="padding: 0.75rem; font-size: 0.875rem; width: 120px;">Quantity</th>
                                                <th style="padding: 0.75rem; font-size: 0.875rem; width: 60px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($customProductPopupProducts as $popupIndex => $popupProduct)
                                                <tr>
                                                    <td style="padding: 0.75rem;">{{ $popupProduct['name'] }}</td>
                                                    <td style="padding: 0.75rem;">{{ $popupProduct['category'] }}</td>
                                                    <td style="padding: 0.75rem;">{{ $popupProduct['unit'] }}</td>
                                                    <td style="padding: 0.75rem;">
                                                        <div class="d-flex align-items-center gap-1">
                                                            <button type="button" 
                                                                    wire:click="updateCustomPopupProductQuantity({{ $popupIndex }}, {{ (int)($popupProduct['quantity'] ?? 1) - 1 }})"
                                                                    class="btn btn-sm btn-light"
                                                                    style="width: 30px; height: 30px; padding: 0;">
                                                                <i class="fa-solid fa-minus" style="font-size: 0.75rem;"></i>
                                                            </button>
                                                            <input type="number" 
                                                                   wire:model.live="customProductPopupProducts.{{ $popupIndex }}.quantity"
                                                                   min="1"
                                                                   class="form-control form-control-sm"
                                                                   style="width: 60px; text-align: center; padding: 0.25rem;">
                                                            <button type="button" 
                                                                    wire:click="updateCustomPopupProductQuantity({{ $popupIndex }}, {{ (int)($popupProduct['quantity'] ?? 1) + 1 }})"
                                                                    class="btn btn-sm btn-light"
                                                                    style="width: 30px; height: 30px; padding: 0;">
                                                                <i class="fa-solid fa-plus" style="font-size: 0.75rem;"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 0.75rem; text-align: center;">
                                                        <button type="button" 
                                                                wire:click="removeProductFromCustomPopup({{ $popupIndex }})"
                                                                wire:target="removeProductFromCustomPopup"
                                                                onclick="if(!confirm('Are you sure you want to delete this product?')) { event.stopImmediatePropagation(); return false; }"
                                                                class="btn btn-sm btn-icon btn-light-danger"
                                                                title="Remove">
                                                            <span>
                                                                <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                                            </span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Materials Section --}}
                        @php
                            // Show materials section if:
                            // 1. We're in edit mode, OR
                            // 2. The custom product has an ID (exists in database), OR
                            // 3. There are already materials loaded in the popup
                            $showMaterialsSection = $isEditMode || 
                                !empty($orderProducts[$editingCustomProductIndex ?? -1]['custom_product_id'] ?? null) ||
                                !empty($customProductPopupMaterials);
                        @endphp
                        @if($showMaterialsSection)
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-semibold mb-0" style="color: #374151; font-size: 0.9375rem;">
                                        Materials
                                    </label>
                                    <div class="d-flex gap-2 align-items-center">
                                        @if(!empty($customProductPopupMaterials))
                                            <button type="button" 
                                                    wire:click="recalculateAllMaterials"
                                                    class="btn btn-sm btn-primary"
                                                    title="Update All Quantities">
                                                <i class="fa-solid fa-sync-alt me-1"></i>Update QTY
                                            </button>
                                        @endif
                                        @php
                                            $availableMaterials = $this->getAvailableMaterialsForCustomPopup();
                                        @endphp
                                        @if(!empty($availableMaterials))
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light-primary" type="button" id="addMaterialDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fa-solid fa-plus me-1"></i>Add Material
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="addMaterialDropdown" style="max-height: 300px; overflow-y: auto;">
                                                    @foreach($availableMaterials as $material)
                                                        <li>
                                                            <a class="dropdown-item" href="#" wire:click.prevent="addMaterialToCustomPopup({{ $material['id'] }})">
                                                                <div class="d-flex flex-column">
                                                                    <span class="fw-semibold">{{ $material['text'] }}</span>
                                                                    <small class="text-muted">{{ $material['category'] }} • {{ $material['unit'] }}</small>
                                                                </div>
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                
                                @if(!empty($customProductPopupMaterials))
                                    <div class="table-responsive">
                                        <table class="table table-bordered mb-0">
                                            <thead style="background: #f9fafb;">
                                                <tr>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem;">Material</th>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem;">Category</th>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem;">Unit</th>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem; width: 100px;">Pcs</th>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem;">Measurements</th>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem; width: 100px;">Qty</th>
                                                    <th style="padding: 0.75rem; font-size: 0.875rem; width: 60px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($customProductPopupMaterials as $materialIndex => $material)
                                                    <tr wire:key="material-{{ $materialIndex }}-{{ $material['material_id'] ?? $materialIndex }}">
                                                        <td style="padding: 0.75rem;">{{ $material['name'] }}</td>
                                                        <td style="padding: 0.75rem;">{{ $material['category'] }}</td>
                                                        <td style="padding: 0.75rem;">{{ $material['unit'] }}</td>
                                                        <td style="padding: 0.75rem;">
                                                            <input type="number" 
                                                                   wire:model.live.debounce.150ms="customProductPopupMaterials.{{ $materialIndex }}.actual_pcs"
                                                                   wire:key="pcs-{{ $materialIndex }}"
                                                                   min="1"
                                                                   class="form-control form-control-sm"
                                                                   style="width: 70px; text-align: center;">
                                                        </td>
                                                        <td style="padding: 0.75rem;">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                @if(!empty($material['measurements']))
                                                                    @foreach($material['measurements'] as $measurementIndex => $measurement)
                                                                        <div class="d-flex align-items-center gap-1" wire:key="measurement-{{ $materialIndex }}-{{ $measurementIndex }}">
                                                                            <input type="number" 
                                                                                   wire:model.live.debounce.150ms="customProductPopupMaterials.{{ $materialIndex }}.measurements.{{ $measurementIndex }}"
                                                                                   wire:key="measurement-input-{{ $materialIndex }}-{{ $measurementIndex }}"
                                                                                   step="0.01"
                                                                                   min="0"
                                                                                   class="form-control form-control-sm"
                                                                                   style="width: 80px; text-align: center;">
                                                                            @if(count($material['measurements']) > 1)
                                                                                <button type="button" 
                                                                                        wire:click="removeMaterialMeasurement({{ $materialIndex }}, {{ $measurementIndex }})"
                                                                                        class="btn btn-sm btn-icon btn-light-danger"
                                                                                        style="width: 24px; height: 24px; padding: 0;"
                                                                                        title="Remove measurement">
                                                                                    <i class="fa-solid fa-times" style="font-size: 0.625rem;"></i>
                                                                                </button>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                @endif
                                                                <button type="button" 
                                                                        wire:click="addMaterialMeasurement({{ $materialIndex }})"
                                                                        class="btn btn-sm btn-icon btn-light-primary"
                                                                        style="width: 28px; height: 28px; padding: 0;"
                                                                        title="Add measurement">
                                                                    <i class="fa-solid fa-plus" style="font-size: 0.75rem;"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td style="padding: 0.75rem; text-align: center;">
                                                            <div class="d-flex flex-column align-items-center gap-1">
                                                                <div style="font-weight: 600; color: #2563eb; font-size: 1rem;">
                                                                    {{ formatQty($material['calculated_quantity'] ?? 0) }}
                                                                </div>
                                                                <div class="text-muted" style="font-size: 0.7rem; font-weight: 400;">
                                                                    ({{ implode(' + ', array_filter(array_map(function($m) { return is_numeric($m) ? formatQty($m) : ''; }, $material['measurements'] ?? []))) ?: '0' }}) × {{ $material['actual_pcs'] ?? 1 }}
                                                                </div>
                                                                <button type="button" 
                                                                        wire:click="recalculateMaterialQuantity({{ $materialIndex }})"
                                                                        class="btn btn-sm btn-icon btn-light-primary mt-1"
                                                                        style="width: 28px; height: 28px; padding: 0;"
                                                                        title="Update Quantity">
                                                                    <i class="fa-solid fa-sync-alt" style="font-size: 0.75rem;"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td style="padding: 0.75rem; text-align: center;">
                                                            <button type="button" 
                                                                    wire:click="removeMaterialFromCustomPopup({{ $materialIndex }})"
                                                                    class="btn btn-sm btn-icon btn-light-danger"
                                                                    title="Remove">
                                                                <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="alert alert-info mb-0" style="font-size: 0.875rem;">
                                        <i class="fa-solid fa-info-circle me-2"></i>
                                        No materials added. Select products first to see connected materials, or add materials manually.
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer border-top" style="padding: 1.25rem 1.5rem; background: #fff;">
                        <button type="button" class="btn btn-light-secondary" wire:click="closeCustomProductModal" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                            Cancel
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="saveCustomProductFromPopup" style="border-radius: 0.5rem; padding: 0.625rem 1.25rem;">
                            <span>
                                <i class="fa-solid fa-check me-2"></i>Save
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
/* Fix dropdown visibility in edit mode grouped products */
.order-edit-mode .table-responsive {
    overflow: visible !important;
}
.order-edit-mode .table-responsive table {
    overflow: visible !important;
}
.order-edit-mode .table-responsive tbody td {
    overflow: visible !important;
    position: relative;
}

/* Ensure dropdown appears above all rows */
.order-edit-mode .table-responsive tbody tr {
    position: relative;
}
.order-edit-mode .table-responsive tbody td:nth-child(2) {
    position: relative;
    z-index: 1;
}
.order-edit-mode .table-responsive tbody td:nth-child(2) .position-relative {
    overflow: visible;
    position: relative;
    z-index: 2;
}
.order-edit-mode .table-responsive tbody td .position-absolute.shadow-lg,
.product-dropdown-overlay {
    max-width: calc(100vw - 40px);
    word-wrap: break-word;
    z-index: 999999 !important;
    position: fixed !important;
}

/* Ensure dropdown overlays all table rows */
.order-edit-mode .table-responsive tbody tr {
    position: relative;
    z-index: 1;
}
.order-edit-mode .table-responsive tbody tr:hover {
    z-index: 1;
}
.order-edit-mode .table-responsive tbody td:nth-child(2) .position-relative {
    z-index: auto;
    position: relative;
}
.order-edit-mode .table-responsive tbody td:nth-child(2) .position-relative:hover {
    z-index: auto;
}

/* Ensure dropdown is always visible above all content */
.product-dropdown-overlay {
    transform: none !important;
    pointer-events: auto !important;
}

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
    position: relative;
    overflow: visible;
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

// Scroll to first validation error when form is submitted with errors
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', ({ el, component }) => {
        // Small delay to ensure DOM is updated
        setTimeout(() => {
            // Check if there are validation errors
            const errorAlert = document.querySelector('#validation-error-summary, .alert-danger');
            const errorFields = document.querySelectorAll('.is-invalid, [style*="border: 1px solid #ef4444"], [style*="border:1px solid #ef4444"]');
            
            if (errorAlert || errorFields.length > 0) {
                // Scroll to error summary if it exists
                if (errorAlert) {
                    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                    // Highlight the error alert briefly with animation
                    errorAlert.style.animation = 'errorPulse 1s ease-in-out';
                    setTimeout(() => {
                        errorAlert.style.animation = '';
                    }, 1000);
                } else if (errorFields.length > 0) {
                    // Scroll to first error field
                    errorFields[0].scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                    // Highlight the error field briefly
                    const errorField = errorFields[0];
                    errorField.style.transition = 'box-shadow 0.5s ease';
                    errorField.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.3)';
                    setTimeout(() => {
                        errorField.style.boxShadow = '';
                    }, 1500);
                }
            }
        }, 100);
    });
    
    // Also listen for wire:click on save button
    document.addEventListener('click', (e) => {
        if (e.target.closest('button[wire\\:click="save"]')) {
            // Wait a bit for validation to run
            setTimeout(() => {
                const errorAlert = document.querySelector('#validation-error-summary, .alert-danger');
                if (errorAlert) {
                    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
                    errorAlert.style.animation = 'errorPulse 1s ease-in-out';
                    setTimeout(() => {
                        errorAlert.style.animation = '';
                    }, 1000);
                }
            }, 300);
        }
    });
});

// Listen for Livewire validation errors
document.addEventListener('livewire:error', (event) => {
    // Scroll to top to show error summary
    setTimeout(() => {
        const errorAlert = document.querySelector('#validation-error-summary, .alert-danger');
        if (errorAlert) {
            errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
        }
    }, 100);
});

// Add CSS animation for error pulse and product status styling
const style = document.createElement('style');
style.textContent = `
    @keyframes errorPulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.1);
        }
        50% {
            transform: scale(1.01);
            box-shadow: 0 8px 12px -2px rgba(239, 68, 68, 0.3);
        }
    }
    
    /* Product Status Select Styling */
    select.product-status-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none;
    }
    
    select.product-status-select:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.15) !important;
    }
    
    select.product-status-select:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3) !important;
    }
    
    select.product-status-select option {
        padding: 0.75rem;
        font-weight: 500;
    }
`;
document.head.appendChild(style);

// Handle product status change - prevents immediate visual update for in_transit
function handleProductStatusChange(select, groupType) {
    const newValue = select.value;
    const currentStatus = select.getAttribute('data-current-status');
    
    // If changing to in_transit, don't update visual immediately
    // Wait for Livewire to confirm (either revert or update)
    if (newValue === 'in_transit' && currentStatus !== 'in_transit') {
        // Store the pending status - don't update visual yet
        select.setAttribute('data-pending-status', newValue);
        // Keep the current visual state
        return;
    }
    
    // For other statuses, update immediately
    updateStatusColor(select, groupType);
    select.setAttribute('data-current-status', newValue);
}

// Function to update status color and icon
function updateStatusColor(select, groupType) {
    const selectedOption = select.options[select.selectedIndex];
    const color = selectedOption ? selectedOption.getAttribute('data-color') : '#f3f4f6';
    const textColor = selectedOption ? selectedOption.getAttribute('data-text-color') : '#374151';
    const icon = selectedOption ? selectedOption.getAttribute('data-icon') : 'fa-clock';
    
    if (color) {
        select.style.backgroundColor = color;
        select.style.borderColor = textColor ? textColor + '33' : 'rgba(55, 65, 81, 0.2)';
    }
    
    if (textColor) {
        select.style.color = textColor;
    }
    
    // Update icon
    const iconElement = select.parentElement.querySelector('.status-icon-' + groupType);
    if (iconElement && icon) {
        iconElement.className = 'fa-solid ' + icon + ' position-absolute status-icon-' + groupType;
        iconElement.style.right = '0.875rem';
        iconElement.style.top = '50%';
        iconElement.style.transform = 'translateY(-50%)';
        iconElement.style.pointerEvents = 'none';
        iconElement.style.color = textColor || '#374151';
        iconElement.style.fontSize = '0.875rem';
        iconElement.style.zIndex = '10';
    }
}

// Update product status select background color and icon on change
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', ({ el, component }) => {
        // Update select background colors and icons based on selected value
        setTimeout(() => {
            document.querySelectorAll('select.product-status-select').forEach(select => {
                const selectedOption = select.options[select.selectedIndex];
                const color = selectedOption ? selectedOption.getAttribute('data-color') : '#f3f4f6';
                const textColor = selectedOption ? selectedOption.getAttribute('data-text-color') : '#374151';
                const icon = selectedOption ? selectedOption.getAttribute('data-icon') : 'fa-clock';
                
                if (color) {
                    select.style.backgroundColor = color;
                }
                
                if (textColor) {
                    select.style.color = textColor;
                    select.style.borderColor = textColor + '33';
                }
                
                // Update icon
                const groupType = select.getAttribute('data-group-type') || select.id.replace('product-status-', '');
                const iconElement = select.parentElement.querySelector('.status-icon-' + groupType);
                if (iconElement && icon) {
                    iconElement.className = 'fa-solid ' + icon + ' position-absolute status-icon-' + groupType;
                    iconElement.style.right = '0.875rem';
                    iconElement.style.top = '50%';
                    iconElement.style.transform = 'translateY(-50%)';
                    iconElement.style.pointerEvents = 'none';
                    iconElement.style.color = textColor || '#374151';
                    iconElement.style.fontSize = '0.875rem';
                    iconElement.style.zIndex = '10';
                }
            });
        }, 100);
    });
    
    // Also update on direct change (but handleProductStatusChange will handle in_transit specially)
    document.addEventListener('change', (e) => {
        if (e.target.matches('select.product-status-select')) {
            const groupType = e.target.id.replace('product-status-', '');
            const newValue = e.target.value;
            const currentStatus = e.target.getAttribute('data-current-status');
            
            // If changing to in_transit, don't update visual immediately
            if (newValue === 'in_transit' && currentStatus !== 'in_transit') {
                e.target.setAttribute('data-pending-status', newValue);
                return;
            }
            
            // For other statuses, update immediately
            updateStatusColor(e.target, groupType);
            e.target.setAttribute('data-current-status', newValue);
        }
    });
    
    // Initialize colors and icons on page load
    setTimeout(() => {
        document.querySelectorAll('select.product-status-select').forEach(select => {
            const selectedOption = select.options[select.selectedIndex];
            const color = selectedOption ? selectedOption.getAttribute('data-color') : '#f3f4f6';
            const textColor = selectedOption ? selectedOption.getAttribute('data-text-color') : '#374151';
            const icon = selectedOption ? selectedOption.getAttribute('data-icon') : 'fa-clock';
            
            if (color) {
                select.style.backgroundColor = color;
            }
            
            if (textColor) {
                select.style.color = textColor;
                select.style.borderColor = textColor + '33';
            }
            
            // Update icon
            const groupType = select.getAttribute('data-group-type') || select.id.replace('product-status-', '');
            const iconElement = select.parentElement.querySelector('.status-icon-' + groupType);
            if (iconElement && icon) {
                iconElement.className = 'fa-solid ' + icon + ' position-absolute status-icon-' + groupType;
                iconElement.style.right = '0.875rem';
                iconElement.style.top = '50%';
                iconElement.style.transform = 'translateY(-50%)';
                iconElement.style.pointerEvents = 'none';
                iconElement.style.color = textColor || '#374151';
                iconElement.style.fontSize = '0.875rem';
                iconElement.style.zIndex = '10';
            }
        });
    }, 200);
    
    // Livewire v3: register event listeners directly inside this init callback
    Livewire.on('revert-product-status-select', (...args) => {
        const payload = args[0] ?? {};
        const type = (payload && typeof payload === 'object' && 'type' in payload) ? payload.type : args[0];
        const status = (payload && typeof payload === 'object' && 'status' in payload) ? payload.status : args[1];
        if (!type || !status) return;
        const select = document.getElementById('product-status-' + type);
        if (select) {
            select.value = status;
            Array.from(select.options).forEach(option => {
                option.selected = (option.value === status);
            });
            updateStatusColor(select, type);
            select.setAttribute('data-current-status', status);
            select.removeAttribute('data-pending-status');
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    
    Livewire.on('update-product-status-select', (...args) => {
        const payload = args[0] ?? {};
        const type = (payload && typeof payload === 'object' && 'type' in payload) ? payload.type : args[0];
        const status = (payload && typeof payload === 'object' && 'status' in payload) ? payload.status : args[1];
        if (!type || !status) return;
        const select = document.getElementById('product-status-' + type);
        if (select) {
            select.value = status;
            updateStatusColor(select, type);
            select.setAttribute('data-current-status', status);
            select.removeAttribute('data-pending-status');
        }
    });
    
    // Sync select values after Livewire updates based on productStatuses
    Livewire.hook('morph.updated', ({ el, component }) => {
        setTimeout(() => {
            if (window.Livewire && window.Livewire.find) {
                try {
                    const livewireComponent = window.Livewire.find(component.id);
                    if (livewireComponent && livewireComponent.get) {
                        const productStatuses = livewireComponent.get('productStatuses');
                        if (productStatuses) {
                            Object.keys(productStatuses).forEach(type => {
                                const select = document.getElementById('product-status-' + type);
                                if (select) {
                                    const expectedValue = productStatuses[type];
                                    if (typeof expectedValue !== 'string') return;
                                    if (select.value !== expectedValue) {
                                        select.value = expectedValue;
                                        updateStatusColor(select, type);
                                        select.setAttribute('data-current-status', expectedValue);
                                    }
                                }
                            });
                        }
                    }
                } catch (e) {
                    // Ignore errors
                }
            }
        }, 150);
    });
});
</script>
@endpush
