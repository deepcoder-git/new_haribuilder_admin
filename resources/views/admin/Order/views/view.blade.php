<div>
    <div class="card">
        <x-view-header 
            :moduleName="$moduleName ?? 'Order'" 
            :moduleIcon="$moduleIcon ?? 'file-invoice'" 
            :indexRoute="$indexRoute ?? 'admin.orders.index'"
            :editRoute="$editRoute ?? 'admin.orders.edit'"
            :editId="$editId ?? $order->id ?? null"
        />
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-5">
                    @php
                        // All orders use ORD prefix
                        $displayPrefix = 'ORD';
                        $displayId = $order->id;
                        $displayRoute = route('admin.orders.view', $displayId);
                    @endphp
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Order ID</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">
                            <a href="{{ $displayRoute }}" 
                               class="badge badge-light-dark" 
                               title="View {{ $displayPrefix }}{{ $displayId }}"
                               style="min-width: 70px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; cursor: pointer;">
                                {{ $displayPrefix }}{{ $displayId }}
                            </a>
                        </span>
                    </div>
                    @if($order->site)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $order->site->name ?? 'N/A' }}</span>
                    </div>
                    @endif
                    @if($order->siteManager)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Site Supervisor</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $order->siteManager->name ?? 'N/A' }}</span>
                    </div>
                    @endif
                    @if($order->transportManager)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Transport Manager</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $order->transportManager->name ?? 'N/A' }}</span>
                    </div>
                    @endif
                    @php
                        // Extract driver details from product_driver_details JSON column grouped by product type
                        // driver_name and vehicle_number columns were migrated to product_driver_details JSON
                        $driverDetails = $order->product_driver_details ?? [];
                        if (!is_array($driverDetails)) {
                            $driverDetails = [];
                        }
                        $productTypeLabels = [
                            'hardware' => 'Hardware',
                            'warehouse' => 'Warehouse',
                            'lpo' => 'LPO',
                            'custom' => 'Custom'
                            ];
                            $hasAnyDriverDetails = false;
                            foreach (['hardware', 'warehouse', 'lpo', 'custom'] as $type) {
                            if (isset($driverDetails[$type]['out_for_delivery']['driver_name']) || isset($driverDetails[$type]['out_for_delivery']['vehicle_number'])) {
                                $hasAnyDriverDetails = true;
                                break;
                            }
                        }
                    @endphp
                    @if($hasAnyDriverDetails)
                        <div class="mb-3" style="border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-top: 1rem;">
                            <div class="mb-2">
                                <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Driver Details</span>
                                <span class="text-gray-600" style="margin: 0 8px;">:</span>
                            </div>
                            <div style="margin-left: 168px;">
                                @foreach(['hardware', 'warehouse', 'lpo', 'custom'] as $type)
                                    @php
                                        $typeDriverName = $driverDetails[$type]['out_for_delivery']['driver_name'] ?? null;
                                        $typeVehicleNumber = $driverDetails[$type]['out_for_delivery']['vehicle_number'] ?? null;
                                        $hasTypeDriverDetails = $typeDriverName || $typeVehicleNumber;
                                    @endphp
                                    @if($hasTypeDriverDetails)
                                        <div class="mb-2" style="background: #f9fafb; padding: 0.75rem; border-radius: 0.5rem; border-left: 3px solid {{ $type === 'hardware' ? '#3b82f6' : ($type === 'warehouse' ? '#10b981' : ($type === 'lpo' ? '#8b5cf6' : '#f59e0b')) }}; margin-bottom: 0.75rem;">
                                            <div class="fw-semibold mb-1" style="font-size: 0.875rem; color: #374151; margin-bottom: 0.5rem;">
                                                <i class="fa-solid fa-box me-1" style="font-size: 0.75rem;"></i>
                                                {{ $productTypeLabels[$type] ?? ucfirst($type) }}
                                            </div>
                                            @if($typeDriverName)
                                                <div class="mb-1" style="line-height: 1.6; font-size: 0.875rem;">
                                                    <span class="text-gray-600" style="font-weight: 500;">Driver Name:</span>
                                                    <span class="text-gray-800 ms-1 fw-semibold">{{ $typeDriverName }}</span>
                                                </div>
                                            @endif
                                            @if($typeVehicleNumber)
                                                <div style="line-height: 1.6; font-size: 0.875rem;">
                                                    <span class="text-gray-600" style="font-weight: 500;">Vehicle Number:</span>
                                                    <span class="text-gray-800 ms-1 fw-semibold">{{ $typeVehicleNumber }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mb-2" style="line-height: 1.8;">
                            <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Driver Details</span>
                            <span class="text-gray-600" style="margin: 0 8px;">:</span>
                            <span class="text-gray-500 fs-6">Not assigned</span>
                        </div>
                    @endif
                    @if($order->priority)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Priority</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ ucfirst($order->priority ?? 'N/A') }}</span>
                    </div>
                    @endif
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Status</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="ms-2">
                            @php
                                $orderStatus = $order->status;
                                if (!$orderStatus instanceof \App\Utility\Enums\OrderStatusEnum) {
                                    $statusValue = $orderStatus ?? 'pending';
                                    $orderStatus = \App\Utility\Enums\OrderStatusEnum::tryFrom($statusValue) ?? \App\Utility\Enums\OrderStatusEnum::Pending;
                                }
                                $badgeClass = match($orderStatus) {
                                    \App\Utility\Enums\OrderStatusEnum::Pending => 'badge-light-warning',
                                    \App\Utility\Enums\OrderStatusEnum::Approved => 'badge-light-success',
                                    \App\Utility\Enums\OrderStatusEnum::InTransit => 'badge-light-primary',
                                    \App\Utility\Enums\OrderStatusEnum::Delivery => 'badge-light-info',
                                    \App\Utility\Enums\OrderStatusEnum::Cancelled => 'badge-light-danger',
                                    \App\Utility\Enums\OrderStatusEnum::Rejected => 'badge-light-danger',
                                    \App\Utility\Enums\OrderStatusEnum::OutOfDelivery => 'badge-light-primary',
                                    default => 'badge-light-secondary',
                                };
                                $statusLabel = $orderStatus->getName();
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </span>
                    </div>

                    @if($order->drop_location)
                    <div class="mb-2" style="line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left;">Drop Location</span>
                        <span class="text-gray-600" style="margin: 0 8px;">:</span>
                        <span class="text-gray-800 fw-bold fs-6">{{ $order->drop_location }}</span>
                    </div>
                    @endif
                    @if($order->note)
                    <div class="mb-2" style="display: flex; align-items: flex-start; line-height: 1.8;">
                        <span class="text-gray-600 fw-semibold fs-6" style="display: inline-block; width: 160px; text-align: left; flex-shrink: 0;">Note</span>
                        <span class="text-gray-600" style="margin: 0 8px; flex-shrink: 0;">:</span>
                        <span class="text-gray-800 fw-bold fs-6" style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6; flex: 1; min-width: 0;">{{ $order->note }}</span>
                    </div>
                    @endif
                    {{-- approved_by / approved_at / document_details columns were removed from orders --}}
                    {{-- deliveries table removed --}}
                </div>
                <div class="col-md-7">
                    @php
                        $hardwareProducts = $order->products ? $order->products->filter(fn($p) => $p->store?->value === 'hardware_store') : collect();
                        $warehouseProducts = $order->products ? $order->products->filter(fn($p) => $p->store?->value === 'workshop_store') : collect();
                        $lpoProducts = $order->products ? $order->products->filter(fn($p) => $p->store?->value === 'lpo') : collect();
                        $customProducts = $order->customProducts ?? collect();
                        
                        // Calculate warehouse row count including connected products and materials
                        $warehouseRowCount = $warehouseProducts->count();
                        foreach ($customProducts as $customProduct) {
                            $warehouseRowCount += 1; // Custom product row
                            
                            // Count connected products
                            $connectedProductIds = $customProduct->product_ids ?? [];
                            if (is_array($connectedProductIds) && !empty($connectedProductIds)) {
                                $warehouseRowCount += count($connectedProductIds);
                            }
                            
                            // Count materials from product_details.materials
                            $productDetails = $customProduct->product_details ?? [];
                            $adminMaterials = $productDetails['materials'] ?? [];
                            if (is_array($adminMaterials)) {
                                $warehouseRowCount += count($adminMaterials);
                            }
                            
                            // Count materials from connected products (product_materials)
                            if (!empty($connectedProductIds)) {
                                $connectedProducts = \App\Models\Product::with('materials')->whereIn('id', $connectedProductIds)->get();
                                foreach ($connectedProducts as $connectedProduct) {
                                    if ($connectedProduct->relationLoaded('materials')) {
                                        $warehouseRowCount += $connectedProduct->materials->count();
                                    }
                                }
                            }
                        }
                        
                        $hasAnyProducts = $hardwareProducts->count() > 0 || $warehouseRowCount > 0 || $lpoProducts->count() > 0;
                    @endphp

                    @if($hasAnyProducts)
                    <div class="mb-4">
                        <h5 class="mb-3 text-gray-800 fw-bold">Materials</h5>
                        
                        @if($hardwareProducts->count() > 0)
                        <div class="mb-4" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff;">
                            <div style="background: #dbeafe; padding: 0.75rem 1rem; color: #1f2937; font-weight: 600; font-size: 0.9375rem; border-radius: 0.75rem 0.75rem 0 0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid rgba(0,0,0,0.1);">
                                <span style="display: flex; align-items: center; gap: 0.5rem; color: #1f2937;">
                                    <i class="fa-solid fa-boxes" style="font-size: 1rem; color: #3b82f6;"></i>
                                    <span style="color: #1f2937; font-weight: 600;">Hardware</span>
                                </span>
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                    <label style="margin: 0; font-size: 0.8125rem; font-weight: 600; color: #374151; white-space: nowrap;">
                                        Order Status:
                                    </label>
                                    @php
                                        // Get product_status array directly to check 'hardware' key
                                        $productStatusArray = $order->product_status ?? [];
                                        $hardwareStatus = $productStatusArray['hardware'] ?? null;
                                        
                                        // Use hardware status if available, otherwise default to pending
                                        if ($hardwareStatus !== null && $hardwareStatus !== '' && $hardwareStatus !== 'null') {
                                            $statusValue = $hardwareStatus;
                                        } else {
                                            $statusValue = 'pending';
                                        }
                                        
                                        $productStatusColors = \App\Utility\Enums\ProductStatusEnum::getAllColors();
                                        $productStatusTextColors = \App\Utility\Enums\ProductStatusEnum::getAllTextColors();
                                        $statusIcons = \App\Utility\Enums\ProductStatusEnum::getAllIcons();
                                        $productStatusLabels = \App\Utility\Enums\ProductStatusEnum::getAllLabels();
                                        $currentTextColor = $productStatusTextColors[$statusValue] ?? '#374151';
                                        $borderColor = $currentTextColor . '33';
                                    @endphp
                                    <div class="d-inline-flex align-items-center gap-2"
                                         style="background-color: {{ $productStatusColors[$statusValue] ?? '#f3f4f6' }}; color: {{ $currentTextColor }}; border: 2px solid {{ $borderColor }}; min-width: 180px; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 0.875rem; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <i class="fa-solid {{ $statusIcons[$statusValue] ?? 'fa-clock' }}" style="font-size: 0.875rem;"></i>
                                        <span>{{ $productStatusLabels[$statusValue] ?? ucfirst($statusValue) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                                    <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                        <tr>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 250px; width: 35%;">Material</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 120px;">Category</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 70px;">Unit</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Store</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($hardwareProducts as $index => $product)
                                        <tr style="vertical-align: middle;">
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                @php
                                                    $firstImage = $product->productImages->first();
                                                    $imageUrl = $firstImage ? $firstImage->image_url : ($product->image ? \Storage::url($product->image) : null);
                                                @endphp
                                                @if($imageUrl)
                                                    <img src="{{ $imageUrl }}" 
                                                         alt="{{ $product->product_name ?? $product->name ?? 'Material' }}"
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0 auto;"
                                                         class="product-image-zoom"
                                                         data-image-url="{{ $imageUrl }}"
                                                         data-product-name="{{ $product->product_name ?? $product->name ?? 'Material' }}">
                                                @else
                                                    <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                                        <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.4;">
                                                    {{ $product->product_name ?? $product->name ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; color: #374151;">{{ $product->category->name ?? '-' }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; color: #374151;">{{ $product->unit_type ?? '-' }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">{{ formatQty($product->pivot->quantity ?? 0) }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                <span class="badge" style="background: #dc3545; color: #fff; font-size: 0.75rem; padding: 0.35rem 0.65rem;">Hardware</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        @if($warehouseRowCount > 0)
                        <div class="mb-4" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff;">
                            <div style="background: #d1fae5; padding: 0.75rem 1rem; color: #1f2937; font-weight: 600; font-size: 0.9375rem; border-radius: 0.75rem 0.75rem 0 0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid rgba(0,0,0,0.1);">
                                <span style="display: flex; align-items: center; gap: 0.5rem; color: #1f2937;">
                                    <i class="fa-solid fa-boxes" style="font-size: 1rem; color: #10b981;"></i>
                                    <span style="color: #1f2937; font-weight: 600;">Workshop (Custom)</span>
                                </span>
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                    <label style="margin: 0; font-size: 0.8125rem; font-weight: 600; color: #374151; white-space: nowrap;">
                                        Order Status:
                                    </label>
                                    @php
                                        // Get product_status array directly to check both 'workshop' and 'custom' keys
                                        $productStatusArray = $order->product_status ?? [];
                                        $workshopStatus = $productStatusArray['workshop'] ?? null;
                                        $customStatus = $productStatusArray['custom'] ?? null;
                                        
                                        // Use workshop status if available, otherwise fall back to custom status
                                        // This matches the logic in calculateOrderStatusFromProductStatuses()
                                        if ($workshopStatus !== null && $workshopStatus !== '' && $workshopStatus !== 'null') {
                                            $statusValue = $workshopStatus;
                                        } elseif ($customStatus !== null && $customStatus !== '' && $customStatus !== 'null') {
                                            $statusValue = $customStatus;
                                        } else {
                                            $statusValue = 'pending';
                                        }
                                        
                                        $productStatusColors = \App\Utility\Enums\ProductStatusEnum::getAllColors();
                                        $productStatusTextColors = \App\Utility\Enums\ProductStatusEnum::getAllTextColors();
                                        $statusIcons = \App\Utility\Enums\ProductStatusEnum::getAllIcons();
                                        $productStatusLabels = \App\Utility\Enums\ProductStatusEnum::getAllLabels();
                                        $currentTextColor = $productStatusTextColors[$statusValue] ?? '#374151';
                                        $borderColor = $currentTextColor . '33';
                                    @endphp
                                    <div class="d-inline-flex align-items-center gap-2"
                                         style="background-color: {{ $productStatusColors[$statusValue] ?? '#f3f4f6' }}; color: {{ $currentTextColor }}; border: 2px solid {{ $borderColor }}; min-width: 180px; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 0.875rem; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <i class="fa-solid {{ $statusIcons[$statusValue] ?? 'fa-clock' }}" style="font-size: 0.875rem;"></i>
                                        <span>{{ $productStatusLabels[$statusValue] ?? ucfirst($statusValue) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                                    <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                        <tr>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 250px; width: 35%;">Material</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 120px;">Category</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 70px;">Unit</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Store</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $isFirstWarehouse = true; @endphp
                                        @foreach($warehouseProducts as $index => $product)
                                        <tr style="vertical-align: middle;">
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                @php
                                                    $firstImage = $product->productImages->first();
                                                    $imageUrl = $firstImage ? $firstImage->image_url : ($product->image ? \Storage::url($product->image) : null);
                                                @endphp
                                                @if($imageUrl)
                                                    <img src="{{ $imageUrl }}" 
                                                         alt="{{ $product->product_name ?? $product->name ?? 'Material' }}"
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0 auto;"
                                                         class="product-image-zoom"
                                                         data-image-url="{{ $imageUrl }}"
                                                         data-product-name="{{ $product->product_name ?? $product->name ?? 'Material' }}">
                                                @else
                                                    <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                                        <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.4;">
                                                    {{ $product->product_name ?? $product->name ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; color: #374151;">{{ $product->category->name ?? '-' }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; color: #374151;">{{ $product->unit_type ?? '-' }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">{{ formatQty($product->pivot->quantity ?? 0) }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                <span class="badge" style="background: #0d6efd; color: #fff; font-size: 0.75rem; padding: 0.35rem 0.65rem;">Workshop</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                        @foreach($customProducts as $index => $customProduct)
                                        @php
                                            // Get connected products from product_ids
                                            $connectedProductIds = $customProduct->product_ids ?? [];
                                            if (!is_array($connectedProductIds)) {
                                                $connectedProductIds = [];
                                            }
                                            $connectedProducts = !empty($connectedProductIds) 
                                                ? \App\Models\Product::with(['category', 'productImages', 'materials.category', 'materials.productImages'])->whereIn('id', $connectedProductIds)->get() 
                                                : collect();
                                            
                                            // Get materials from product_details.materials (admin-added materials)
                                            $productDetails = $customProduct->product_details ?? [];
                                            $adminMaterials = $productDetails['materials'] ?? [];
                                            
                                            // Get materials from connected products (product_materials table)
                                            $connectedMaterials = collect();
                                            foreach ($connectedProducts as $connectedProduct) {
                                                if ($connectedProduct->relationLoaded('materials')) {
                                                    foreach ($connectedProduct->materials as $material) {
                                                        $connectedMaterials->push([
                                                            'material_id' => $material->id,
                                                            'material_name' => $material->product_name,
                                                            'category' => $material->category->name ?? '',
                                                            'unit' => $material->unit_type ?? '',
                                            'quantity' => (float)($material->pivot->quantity ?? 0),
                                            'source' => 'product_materials'
                                        ]);
                                    }
                                }
                            }
                            
                            // Merge both sources of materials (same as API)
                            $allMaterials = $connectedMaterials->merge(collect($adminMaterials))->values();
                            
                            // Calculate total rows for this custom product (1 for custom product + connected products + materials)
                            $customProductRows = 1 + $connectedProducts->count() + $allMaterials->count();
                        @endphp
                        
                        {{-- Custom Product Row --}}
                        <tr style="vertical-align: middle;">
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                @php
                                    $customImages = $customProduct->custom_images ?? [];
                                    $firstCustomImage = !empty($customImages) ? $customImages[0] : null;
                                    $customImageUrl = $firstCustomImage ? \Storage::url($firstCustomImage) : null;
                                @endphp
                                @if($customImageUrl)
                                    <img src="{{ $customImageUrl }}" 
                                         alt="Custom Material"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0 auto;"
                                         class="product-image-zoom"
                                         data-image-url="{{ $customImageUrl }}"
                                         data-product-name="Custom Material">
                                @else
                                    <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                        <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.4;">
                                    {{ $customProduct->custom_note ?? 'Custom Material' }}
                                </div>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <span style="font-size: 0.875rem; color: #374151;">-</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <span style="font-size: 0.875rem; color: #374151;">{{ $customProduct->unit->name ?? '-' }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">{{ formatQty($customProduct->quantity ?? 0) }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                <span class="badge" style="background: #0d6efd; color: #fff; font-size: 0.75rem; padding: 0.35rem 0.65rem;">Workshop</span>
                            </td>
                        </tr>
                        
                        {{-- Connected Products (from product_ids) --}}
                        @foreach($connectedProducts as $connectedProduct)
                        <tr style="vertical-align: middle; background: #f9fafb;">
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                @php
                                    $firstImage = $connectedProduct->productImages->first();
                                    $imageUrl = $firstImage ? $firstImage->image_url : ($connectedProduct->image ? \Storage::url($connectedProduct->image) : null);
                                @endphp
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}" 
                                         alt="{{ $connectedProduct->product_name }}"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0 auto;"
                                         class="product-image-zoom"
                                         data-image-url="{{ $imageUrl }}"
                                         data-product-name="{{ $connectedProduct->product_name }}">
                                @else
                                    <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                        <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.4; padding-left: 2rem;">
                                    <i class="fa-solid fa-link me-1" style="font-size: 0.7rem; color: #6b7280;"></i>{{ $connectedProduct->product_name }}
                                    <span class="badge badge-light-info ms-1" style="font-size: 0.65rem;">Connected</span>
                                </div>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <span style="font-size: 0.875rem; color: #374151;">{{ $connectedProduct->category->name ?? '-' }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <span style="font-size: 0.875rem; color: #374151;">{{ $connectedProduct->unit_type ?? '-' }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">-</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                <span class="badge" style="background: #0d6efd; color: #fff; font-size: 0.75rem; padding: 0.35rem 0.65rem;">Workshop</span>
                            </td>
                        </tr>
                        @endforeach
                        
                        {{-- Materials (from product_details.materials and product_materials) --}}
                        @foreach($allMaterials as $material)
                        @php
                        $materialId = $material['material_id'] ?? null;
                        $materialName = $material['material_name'] ?? $material['name'] ?? 'N/A';

                        if ($materialName === 'N/A' && $materialId) {
                            $materialProduct = \App\Models\Product::find($materialId);
                            $materialName = $materialProduct ? ($materialProduct->product_name ?? $materialProduct->name ?? 'N/A') : 'N/A';
                        }
                       
                            $materialCategory = $material['category'] ?? '';
                            $materialUnit = $material['unit'] ?? $material['unit_type'] ?? '-';
                            
                            // Get quantity - from admin materials (calculated_quantity) or connected materials (quantity)
                            $materialQty = $material['calculated_quantity'] ?? $material['cal_qty'] ?? $material['quantity'] ?? 0;
                            
                            // Get measurements if available
                            $measurements = $material['measurements'] ?? [];
                            $actualPcs = $material['actual_pcs'] ?? 1;
                            
                            // Format measurements display
                            $measurementsDisplay = '';
                            if (!empty($measurements) && is_array($measurements)) {
                                $measurementsDisplay = implode(' + ', array_filter(array_map(function($m) { 
                                    return is_numeric($m) ? number_format((float)$m, 2) : ''; 
                                }, $measurements)));
                            }
                        @endphp
                        <tr style="vertical-align: middle; background: #fef3f2;">
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                @if($materialId)
                                    @php
                                        $materialProduct = \App\Models\Product::with('productImages')->find($materialId);
                                        $materialImageUrl = null;
                                        if ($materialProduct) {
                                            $firstMaterialImage = $materialProduct->productImages->first();
                                            $materialImageUrl = $firstMaterialImage ? $firstMaterialImage->image_url : ($materialProduct->image ? \Storage::url($materialProduct->image) : null);
                                        }
                                    @endphp
                                    @if($materialImageUrl)
                                        <img src="{{ $materialImageUrl }}" 
                                             alt="{{ $materialName }}"
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0 auto;"
                                             class="product-image-zoom"
                                             data-image-url="{{ $materialImageUrl }}"
                                             data-product-name="{{ $materialName }}">
                                    @else
                                        <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                            <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                        </div>
                                    @endif
                                @else
                                    <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                        <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.4; padding-left: 3rem;">
                                    <i class="fa-solid fa-box me-1" style="font-size: 0.7rem; color: #ef4444;"></i>{{ $materialName }}
                                    <span class="badge badge-light-danger ms-1" style="font-size: 0.65rem;">Material</span>
                                    @if($measurementsDisplay)
                                        <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                            ({{ $measurementsDisplay }}) × {{ $actualPcs }} pcs
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <span style="font-size: 0.875rem; color: #374151;">{{ $materialCategory ?? '-' }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                <span style="font-size: 0.875rem; color: #374151;">{{ $materialUnit }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">{{ formatQty($materialQty) }}</span>
                            </td>
                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                <span class="badge" style="background: #0d6efd; color: #fff; font-size: 0.75rem; padding: 0.35rem 0.65rem;">Workshop</span>
                            </td>
                        </tr>
                        @endforeach
                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        @if($lpoProducts->count() > 0)
                        <div class="mb-4" style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 1px 3px rgba(0,0,0,0.06); background: #ffffff;">
                            <div style="background: #e9d5ff; padding: 0.75rem 1rem; color: #1f2937; font-weight: 600; font-size: 0.9375rem; border-radius: 0.75rem 0.75rem 0 0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 2px solid rgba(0,0,0,0.1);">
                                <span style="display: flex; align-items: center; gap: 0.5rem; color: #1f2937;">
                                    <i class="fa-solid fa-boxes" style="font-size: 1rem; color: #8b5cf6;"></i>
                                    <span style="color: #1f2937; font-weight: 600;">LPO</span>
                                </span>
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                    <label style="margin: 0; font-size: 0.8125rem; font-weight: 600; color: #374151; white-space: nowrap;">
                                        Order Status:
                                    </label>
                                    @php
                                        // Get product_status array directly to check 'lpo' key
                                        $productStatusArray = $order->product_status ?? [];
                                        $lpoStatuses = $productStatusArray['lpo'] ?? [];
                                        
                                        // For LPO, get the status from the first supplier or use a default
                                        $statusValue = 'pending';
                                        if (is_array($lpoStatuses) && !empty($lpoStatuses)) {
                                            // Get the first status from the array
                                            $statusValue = reset($lpoStatuses);
                                        } elseif (is_string($lpoStatuses) && $lpoStatuses !== '' && $lpoStatuses !== 'null') {
                                            $statusValue = $lpoStatuses;
                                        }
                                        
                                        $productStatusColors = \App\Utility\Enums\ProductStatusEnum::getAllColors();
                                        $productStatusTextColors = \App\Utility\Enums\ProductStatusEnum::getAllTextColors();
                                        $statusIcons = \App\Utility\Enums\ProductStatusEnum::getAllIcons();
                                        $productStatusLabels = \App\Utility\Enums\ProductStatusEnum::getAllLabels();
                                        $currentTextColor = $productStatusTextColors[$statusValue] ?? '#374151';
                                        $borderColor = $currentTextColor . '33';
                                    @endphp
                                    <div class="d-inline-flex align-items-center gap-2"
                                         style="background-color: {{ $productStatusColors[$statusValue] ?? '#f3f4f6' }}; color: {{ $currentTextColor }}; border: 2px solid {{ $borderColor }}; min-width: 180px; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 0.875rem; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <i class="fa-solid {{ $statusIcons[$statusValue] ?? 'fa-clock' }}" style="font-size: 0.875rem;"></i>
                                        <span>{{ $productStatusLabels[$statusValue] ?? ucfirst($statusValue) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-bordered mb-0" style="margin-bottom: 0; background: #ffffff;">
                                    <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                        <tr>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Image</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; min-width: 250px; width: 35%;">Material</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 120px;">Category</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: left; color: #374151; text-transform: uppercase; width: 70px;">Unit</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 120px;">Quantity</th>
                                            <th style="padding: 1rem 0.875rem; font-size: 0.8125rem; font-weight: 600; text-align: center; color: #374151; text-transform: uppercase; width: 80px;">Store</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lpoProducts as $index => $product)
                                        <tr style="vertical-align: middle;">
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                @php
                                                    $firstImage = $product->productImages->first();
                                                    $imageUrl = $firstImage ? $firstImage->image_url : ($product->image ? \Storage::url($product->image) : null);
                                                @endphp
                                                @if($imageUrl)
                                                    <img src="{{ $imageUrl }}" 
                                                         alt="{{ $product->product_name ?? $product->name ?? 'Material' }}"
                                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 0.375rem; cursor: pointer; display: block; margin: 0 auto;"
                                                         class="product-image-zoom"
                                                         data-image-url="{{ $imageUrl }}"
                                                         data-product-name="{{ $product->product_name ?? $product->name ?? 'Material' }}">
                                                @else
                                                    <div style="width: 50px; height: 50px; background: #f3f4f6; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                                        <i class="fa-solid fa-image text-gray-400" style="font-size: 1rem;"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <div style="font-size: 0.875rem; font-weight: 500; color: #374151; line-height: 1.4;">
                                                    {{ $product->product_name ?? $product->name ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; color: #374151;">{{ $product->category->name ?? '-' }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: left; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; color: #374151;">{{ $product->unit_type ?? '-' }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                <span style="font-size: 0.875rem; font-weight: 600; color: #1e40af;">{{ formatQty($product->pivot->quantity ?? 0) }}</span>
                                            </td>
                                            <td style="padding: 1rem 0.875rem; text-align: center; vertical-align: middle;">
                                                <span class="badge" style="background: #198754; color: #fff; font-size: 0.75rem; padding: 0.35rem 0.65rem;">LPO</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>No materials found for this order.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

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

@push('footer')
<style>
.product-image-zoom {
    transition: transform 0.2s ease;
}

.product-image-zoom:hover {
    transform: scale(1.1);
    cursor: pointer;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const img = e.target.closest('.product-image-zoom');
        if (img) {
            e.preventDefault();
            const imageUrl = img.getAttribute('data-image-url') || img.getAttribute('src');
            const productName = img.getAttribute('data-product-name') || 'Product Image';
            
            if (imageUrl && typeof bootstrap !== 'undefined') {
                document.getElementById('zoomedImage').src = imageUrl;
                document.getElementById('zoomedImage').alt = productName || 'Product Image';
                document.getElementById('imageZoomModalTitle').textContent = productName || 'Product Image';
                
                const modal = new bootstrap.Modal(document.getElementById('imageZoomModal'));
                modal.show();
            }
        }
    });
});
</script>
@endpush

