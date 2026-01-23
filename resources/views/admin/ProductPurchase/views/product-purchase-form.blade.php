<div class="product-purchase-form">
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 pb-4 pt-4 bg-white" style="border-radius: 0.75rem 0.75rem 0 0;">
            <div class="text-center">
                <h4 class="card-title fw-bold mb-0" style="color: #1e3a8a; font-size: 1.5rem;">
                    {{ $isEditMode ? 'Edit Purchase' : 'Add Purchase' }}
                </h4>
            </div>
        </div>
        <div class="card-body px-4 py-4">
            <!-- Purchase Details Section -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Supplier <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative">
                        @php
                            $selectedSupplier = $suppliers->firstWhere('id', $supplier_id);
                            $selectedSupplierName = $selectedSupplier ? $selectedSupplier->name : 'Select Supplier';
                        @endphp
                        <button type="button"
                                wire:click="toggleSupplierDropdown"
                                wire:loading.attr="disabled"
                                class="form-control form-control-solid d-flex align-items-center justify-content-between @error('supplier_id') is-invalid @enderror"
                                style="height: 44px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; width: 100%;">
                            <span class="text-truncate" style="flex: 1; overflow: hidden; text-overflow: ellipsis;">
                                {{ $selectedSupplierName }}
                            </span>
                            <i class="fa-solid fa-chevron-{{ $supplierDropdownOpen ? 'up' : 'down' }} ms-2" style="flex-shrink: 0; font-size: 0.75rem; color: #6b7280;"></i>
                        </button>
                        
                        @if($supplierDropdownOpen)
                            <div class="position-absolute bg-white border rounded shadow-lg" 
                                 style="top: 100%; left: 0; right: 0; z-index: 1000; margin-top: 0.25rem; max-height: 300px; display: flex; flex-direction: column; min-width: 100%; width: 100%;"
                                 wire:click.stop
                                 x-data="{ 
                                     isOpen: true,
                                     closeDropdown() {
                                         if (typeof $wire !== 'undefined') {
                                             $wire.call('closeSupplierDropdown');
                                         }
                                         this.isOpen = false;
                                     },
                                     handleScroll(event) {
                                         const el = event.target;
                                         if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
                                             if (typeof $wire !== 'undefined') {
                                                 $wire.call('loadMoreSuppliers');
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
                                               wire:model="supplierSearch"
                                               wire:keyup.debounce.300ms="handleSupplierSearch($event.target.value, 'supplierSearch')"
                                               wire:key="supplier-search"
                                               placeholder="Search suppliers..."
                                               class="form-control form-control-solid"
                                               style="padding-left: 2.5rem; height: 38px; border: 1px solid #e5e7eb; border-radius: 0.375rem;"
                                               autofocus
                                               wire:ignore.self>
                                    </div>
                                </div>
                                <div style="overflow-y: auto; max-height: 250px; flex: 1;"
                                     x-on:scroll="handleScroll($event)">
                                    @if($supplierLoading && empty($supplierSearchResults))
                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-spinner fa-spin mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                            <div>Searching...</div>
                                        </div>
                                    @elseif(empty($supplierSearchResults))
                                        <div class="text-center py-4 text-muted" style="font-size: 0.875rem;">
                                            <i class="fa-solid fa-search mb-2" style="font-size: 1.5rem; opacity: 0.5;"></i>
                                            <div>No suppliers found</div>
                                        </div>
                                    @else
                                        @foreach($supplierSearchResults as $result)
                                            <div wire:click="selectSupplier({{ $result['id'] ?? 'null' }})"
                                                 class="d-flex align-items-center gap-2 px-3 py-2 cursor-pointer"
                                                 style="border-bottom: 1px solid #f3f4f6; transition: background-color 0.15s; cursor: pointer; {{ $supplier_id == $result['id'] ? 'background-color: #f0f9ff;' : '' }}"
                                                 onmouseover="this.style.backgroundColor='#f9fafb'"
                                                 onmouseout="this.style.backgroundColor='{{ $supplier_id == $result['id'] ? '#f0f9ff' : 'white' }}'">
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
                                        
                                        @if($supplierLoading && $supplierHasMore)
                                            <div class="text-center py-2"
                                                 style="border-top: 1px solid #e5e7eb; background: #f9fafb; font-size: 0.875rem; color: #6b7280;">
                                                <i class="fa-solid fa-spinner fa-spin me-1"></i>
                                                Loading more...
                                            </div>
                                        @elseif($supplierHasMore)
                                            <div wire:click="loadMoreSuppliers"
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
                    @error('supplier_id') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Invoice No <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           wire:model.blur="invoice_no"
                           class="form-control form-control-solid @error('invoice_no') is-invalid @enderror"
                           placeholder="Enter invoice number"
                           style="height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                    @error('invoice_no') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Purchase Date <span class="text-danger">*</span>
                    </label>
                    <div class="position-relative" x-data="{
                        dateValue: @entangle('purchase_date'),
                        tempDate: '',
                        dateInput: null,
                        init() {
                            if (this.dateValue && this.dateValue.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                                const [d, m, y] = this.dateValue.split('/');
                                this.tempDate = `${y}-${m}-${d}`;
                            }
                            this.$nextTick(() => {
                                this.dateInput = this.$refs.datePicker;
                            });
                        },
                        openDatePicker() {
                            if (this.dateInput) {
                                this.dateInput.showPicker();
                            }
                        },
                        updateDate() {
                            if (this.tempDate) {
                                const date = new Date(this.tempDate);
                                const day = String(date.getDate()).padStart(2, '0');
                                const month = String(date.getMonth() + 1).padStart(2, '0');
                                const year = date.getFullYear();
                                this.dateValue = `${day}/${month}/${year}`;
                            }
                        }
                    }">
                        <input type="text" 
                               x-model="dateValue"
                               @click="openDatePicker()"
                               readonly
                               placeholder="dd/mm/yyyy"
                               class="form-control form-control-solid @error('purchase_date') is-invalid @enderror"
                               style="cursor: pointer; padding-right: 3.5rem; height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb;"/>
                        <i class="fa-solid fa-calendar-days position-absolute end-0 top-50 translate-middle-y me-3" 
                           @click="openDatePicker()"
                           style="color: #1e3a8a; cursor: pointer; z-index: 10;"></i>
                        <input type="date" 
                               x-ref="datePicker"
                               x-model="tempDate"
                               @change="updateDate()"
                               style="position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none;"/>
                    </div>
                    @error('purchase_date') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea wire:model.blur="notes"
                              class="form-control form-control-solid @error('notes') is-invalid @enderror"
                              rows="1"
                              placeholder="Enter notes"
                              style="border-radius: 0.5rem; border: 1px solid #e5e7eb; resize: vertical; min-height: 44px;"></textarea>
                    @error('notes') 
                        <div class="text-danger small mt-1 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-1" style="font-size: 0.75rem;"></i>
                            {{ $message }}
                        </div> 
                    @enderror
                </div>
            </div>

            <!-- Products & Materials Section -->
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="form-label mb-0 fw-semibold" style="font-size: 1.0625rem; color: #1f2937; letter-spacing: -0.01em;">
                            Products & Materials <span class="text-danger">*</span>
                        </label>
                    </div>
                    <div class="table-responsive product-purchase-table-container" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: visible; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff;">
                        <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                            <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                <tr>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 250px; width: 30%;">Product</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 150px;">Category</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 100px;">Unit</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Cost</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Total</th>
                                    <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchaseItems as $index => $item)
                                    @php
                                        $selectedProduct = $products->firstWhere('id', $item['product_id'] ?? null);
                                        // Get category name and unit from product - display only, not stored
                                        $categoryName = '';
                                        $unitType = '';
                                        if ($selectedProduct) {
                                            $unitType = $selectedProduct->unit_type ?? '';
                                            // Get category name - products collection has categories loaded
                                            if ($selectedProduct->category_id && isset($selectedProduct->category)) {
                                                $categoryName = $selectedProduct->category->name ?? '';
                                            }
                                        }
                                    @endphp
                                    <tr wire:key="item-row-{{ $index }}">
                                        <td style="padding: 1rem 0.75rem; text-align: center; vertical-align: middle;">
                                            <div style="width: 50px; height: 50px; background: #f9fafb; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 1px solid #e5e7eb;">
                                                @if($selectedProduct && $selectedProduct->first_image_url)
                                                    <img src="{{ $selectedProduct->first_image_url }}" 
                                                         alt="{{ $selectedProduct->product_name }}"
                                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">
                                                @else
                                                    <i class="fa-solid fa-image text-gray-400" style="font-size: 1.25rem;"></i>
                                                @endif
                                            </div>
                                        </td>
                                        <td style="padding: 1rem 0.75rem; min-width: 250px; position: relative;">
                                            <div class="position-relative" style="width: 100%;">
                                                @php
                                                    $selectedProduct = $products->firstWhere('id', $item['product_id'] ?? null);
                                                    $selectedProductName = $selectedProduct ? $selectedProduct->product_name : 'Select Product';
                                                    @endphp
                                                <button type="button"
                                                        wire:click="toggleProductDropdown({{ $index }})"
                                                        wire:loading.attr="disabled"
                                                        class="form-control form-control-solid d-flex align-items-center justify-content-between @error('purchaseItems.' . $index . '.product_id') is-invalid @enderror"
                                                        style="height: 40px; text-align: left; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 1rem; min-width: 250px; width: 100%;">
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
                                                             index: {{ $index ?? 0 }},
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
                                            @error('purchaseItems.' . $index . '.product_id')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td style="padding: 1rem 0.75rem;">
                                            <input type="text" 
                                                   value="{{ $categoryName }}"
                                                   class="form-control form-control-solid"
                                                   readonly
                                                   disabled
                                                   style="height: 40px; background-color: #f9fafb; border: 1px solid #e5e7eb;">
                                        </td>
                                        <td style="padding: 1rem 0.75rem;">
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
                                                <input type="number" 
                                                       wire:model.live="purchaseItems.{{ $index }}.quantity"
                                                       step="1"
                                                       min="1"
                                                       class="form-control form-control-solid @error('purchaseItems.' . $index . '.quantity') is-invalid @enderror"
                                                       style="width: 80px; height: 32px; text-align: center; padding: 0.25rem;">
                                                <button type="button" 
                                                        wire:click="incrementQuantity({{ $index }})"
                                                        class="btn btn-sm btn-light"
                                                        style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 1px solid #e5e7eb;">
                                                    <i class="fa-solid fa-plus" style="font-size: 0.75rem;"></i>
                                                </button>
                                            </div>
                                            @error('purchaseItems.' . $index . '.quantity')
                                                <div class="text-danger small mt-1 text-center">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td style="padding: 1rem 0.75rem;">
                                            <input type="number" 
                                                   wire:model.live="purchaseItems.{{ $index }}.unit_price"
                                                   step="1"
                                                   min="0"
                                                   class="form-control form-control-solid @error('purchaseItems.' . $index . '.unit_price') is-invalid @enderror"
                                                   placeholder="0"
                                                   style="height: 40px; text-align: center;">
                                            @error('purchaseItems.' . $index . '.unit_price')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td style="padding: 1rem 0.75rem;">
                                            <input type="text" 
                                                   value="{{ number_format((int)($item['total_price'] ?? 0), 0) }}"
                                                   class="form-control form-control-solid"
                                                   readonly
                                                   disabled
                                                   style="height: 40px; text-align: center; background-color: #f9fafb; border: 1px solid #e5e7eb; font-weight: 600;">
                                        </td>
                                        <td style="padding: 1rem 0.75rem; text-align: center;">
                                            <div class="d-flex align-items-center justify-content-center gap-1">
                                                @if($loop->last && !$isEditMode)
                                                    <button type="button" 
                                                            wire:click="addItemRow"
                                                            wire:loading.attr="disabled"
                                                            wire:target="addItemRow"
                                                            class="btn btn-sm btn-icon btn-light-primary"
                                                            title="Add Row"
                                                            style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;">
                                                        <span wire:loading.remove wire:target="addItemRow">
                                                            <i class="fa-solid fa-plus" style="font-size: 0.875rem;"></i>
                                                        </span>
                                                        <span wire:loading wire:target="addItemRow" class="spinner-border spinner-border-sm" style="width: 0.875rem; height: 0.875rem;"></span>
                                                    </button>
                                                @endif
                                                @if(!$isEditMode )
                                                    <button type="button" 
                                                            wire:click="removeItemRow({{ $index }})"
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
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            No items added. Click "+" to add items.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" 
                        wire:click="cancel"
                        class="btn btn-light-secondary"
                        style="height: 44px; border-radius: 0.5rem; padding: 0 1.5rem;">
                    Cancel
                </button>
                <button type="button" 
                        wire:click="save"
                        wire:loading.attr="disabled"
                        class="btn btn-primary"
                        style="height: 44px; border-radius: 0.5rem; padding: 0 1.5rem; background: #1e3a8a; border: none;">
                    <span wire:loading.remove wire:target="save">
                        <i class="fa-solid fa-plus me-2"></i>
                        {{ $isEditMode ? 'Update Purchase' : 'Add Purchase' }}
                    </span>
                    <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-2"></span>
                </button>
            </div>
        </div>
    </div>

    @push('footer')
    <style>
        .product-purchase-form tbody tr {
            transition: opacity 0.1s ease;
        }
        .product-purchase-table-container {
            position: relative;
            overflow: visible !important;
        }
        .product-purchase-form {
            min-height: 200px;
        }
        .product-purchase-form table {
            overflow: visible !important;
        }
        .product-purchase-form table td {
            overflow: visible !important;
        }
        .product-purchase-form table tbody tr {
            overflow: visible !important;
        }
        .product-dropdown-menu {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        [x-cloak] {
            display: none !important;
        }
    </style>
    @endpush
</div>
