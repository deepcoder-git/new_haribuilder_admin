<?php

declare(strict_types=1);

namespace App\Models;

use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Supplier;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_manager_id',
        'store_manager_role',
        'store',
        'transport_manager_id',
        'site_id',
        'sale_date',
        'expected_delivery_date',
        'product_id',
        'quantity',
        'amount',
        'status',
        'approved_by',
        'approved_at',
        'priority',
        'note',
        'rejected_note',
        'product_rejection_notes',
        'customer_image',
        'drop_location',
        'document_details',
        'is_completed',
        'completed_at',
        'completed_by',
        'is_lpo',
        'is_custom_product',
        'supplier_id',
        'product_status',
        'product_driver_details'
    ];

    protected $casts = [
        'status' => OrderStatusEnum::class,
        'sale_date' => 'date',
        'expected_delivery_date' => 'date',
        'quantity' => 'integer',
        'amount' => 'integer',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_lpo' => 'boolean',
        'is_custom_product' => 'boolean',
        'product_status' => 'array',
        'supplier_id' => 'array',
        'product_driver_details' => 'array',
        'product_rejection_notes' => 'array'
    ];

    public function siteManager()
    {
        return $this->belongsTo(\App\Models\Moderator::class, 'site_manager_id');
    }

    public function transportManager()
    {
        return $this->belongsTo(\App\Models\Moderator::class, 'transport_manager_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_products')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }


    public function customProducts()
    {
        return $this->hasMany(OrderCustomProduct::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(Moderator::class, 'completed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Moderator::class, 'approved_by');
    }

    /**
     * Get the parent order (if this is a child order)
     * Self-referential relationship
     */
    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /**
     * Get related/child orders (orders that have this order as parent)
     * Self-referential relationship
     */
    public function relatedOrders()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    /**
     * Get supplier for a specific product (LPO products only)
     * Since supplier_id is now JSON mapping product_id => supplier_id
     */
    public function getSupplierForProduct(int $productId): ?Supplier
    {
        $supplierMapping = $this->supplier_id ?? [];
        if (!is_array($supplierMapping)) {
            return null;
        }
        
        $supplierId = $supplierMapping[(string)$productId] ?? null;
        if (!$supplierId) {
            return null;
        }
        
        return Supplier::find($supplierId);
    }

    /**
     * Get all suppliers associated with this order
     * Returns a collection of Supplier models based on supplier_id JSON mapping
     * Since supplier_id is cast as 'array' and stored as JSON (product_id => supplier_id mapping)
     * This method extracts all unique supplier IDs and returns the Supplier models
     */
    public function supplier()
    {
        $supplierMapping = $this->supplier_id ?? [];
        
        // Handle case where supplier_id might be null or empty
        if (empty($supplierMapping) || !is_array($supplierMapping)) {
            return collect([]);
        }
        
        // Get all unique supplier IDs from the mapping
        // supplier_id is stored as JSON: {product_id: supplier_id, ...}
        $supplierIds = array_values(array_unique(array_filter($supplierMapping, function($id) {
            return !empty($id) && is_numeric($id) && $id > 0;
        })));
        
        if (empty($supplierIds)) {
            return collect([]);
        }
        
        // Return collection of Supplier models
        return Supplier::whereIn('id', $supplierIds)->get();
    }

    public static function syncMixedOrderCompletion(Order $order): void
    {
        $order->loadMissing(['deliveries']);

        $delivery = $order->deliveries()->first();
        $deliveryStatus = $delivery?->status ?? null;
        $orderStatusValue = $order->status?->value ?? 'pending';

        if ($deliveryStatus !== 'delivered' && $orderStatusValue !== 'delivered' && $orderStatusValue !== 'completed') {
            return;
        }

        if ($order->is_completed && ($orderStatusValue === 'delivered' || $orderStatusValue === 'completed')) {
            return;
        }

        $now = now();
        $completedBy = Auth::guard('moderator')->id() ?? Auth::id();

        // All delivered orders now use unified 'delivery' status (no separate 'completed' status)
        $orderStatus = OrderStatusEnum::Delivery->value;

        $order->update([
            'status' => $orderStatus,
            'is_completed' => true,
            'completed_at' => $order->completed_at ?? $now,
            'completed_by' => $order->completed_by ?? $completedBy,
        ]);
    }

    /**
     * Sync parent/child order statuses
     * Note: parent_order_id has been removed - this method now does nothing
     * Kept for backward compatibility to prevent errors
     * Orders are now managed independently based on is_lpo flag
     * 
     * @param Order $order
     * @return void
     */
    public static function syncParentChildOrderStatuses(Order $order): void
    {
        // No longer syncing - orders are managed independently
        // LPO orders are identified by is_lpo flag, not parent_order_id
        $order->syncOrderStatusFromProductStatuses();
        return;
    }

    public static function countPendingOrdersForSiteManager($siteManagerId, $siteId = null)
    {
        $query = self::where('site_manager_id', $siteManagerId)->where('delivery_status', 'pending');

        if ($siteId !== null) {
            $query = $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    public static function countApprovedOrdersForSiteManager($siteManagerId, $siteId = null)
    {
        $query = self::where('site_manager_id', $siteManagerId)->where('delivery_status', 'approved');

        if ($siteId !== null) {
            $query = $query->where('site_id', $siteId);
        }
        
        return $query->count();
    }

    public static function countDeliveredOrdersForSiteManager($siteManagerId, $siteId = null)
    {
        $query = self::where('site_manager_id', $siteManagerId)->where('delivery_status', 'delivered');

        if ($siteId !== null) {
            $query = $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    public static function countRejectedOrdersForSiteManager($siteManagerId, $siteId = null)
    {
        $query = self::where('site_manager_id', $siteManagerId)->where('delivery_status', 'rejected');

        if ($siteId !== null) {
            $query = $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    public static function countDelayedOrdersForSiteManager($siteManagerId, $siteId = null)
    {
        $query = self::where('site_manager_id', $siteManagerId)
                    ->where('sale_date', '<', Carbon::now())
                    ->whereNotNull('transport_manager_id')
                    ->whereIn('delivery_status', ['approved', 'in_transit']);

        if ($siteId !== null) {
            $query = $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    /**
     * Get store manager based on store_manager_role
     * This is a dynamic relationship that finds the manager by role
     */
    public function storeManager()
    {
        if (!$this->store_manager_role) {
            return null;
        }

        // Find the first active moderator with the specified role
        return Moderator::where('role', $this->store_manager_role)
            ->where('status', 'active')
            ->first();
    }

    public function scopePendingOrders($query)
    {
        return $query->where('delivery_status', 'pending');
    }

    /**
     * Initialize product_status with default values
     * LPO is managed supplier-wise (object with supplier IDs as keys)
     */
    public function initializeProductStatus(): array
    {
        return [
            'hardware' => 'pending',
            'warehouse' => 'pending',
            'lpo' => [], // Supplier-wise: {supplier_id: status}
            'custom' => 'pending',
        ];
    }

    /**
     * Get product status for a specific product type.
     * For LPO, can optionally get status for a specific supplier.
     * 
     * @param string|StoreEnum|null $type The store type (string value, StoreEnum object, or null)
     * @param int|null $supplierId For LPO products, get status for specific supplier
     * @return string|array|null Returns the status or null if not found. For LPO without supplier, returns array of supplier statuses
     */
    public function getProductStatus(string|StoreEnum|null $type = null, ?int $supplierId = null): string|array|null
    {
        $productStatus = $this->product_status ?? [];

        // When type is null, fall back based on order-level store or defaults.
        if ($type === null) {
            // Prefer mapping using order->store when available
            if ($this->store instanceof StoreEnum) {
                $storeValue = $this->store->value;
            } elseif (!empty($this->store)) {
                $storeValue = (string) $this->store;
            } else {
                $storeValue = null;
            }

            if ($storeValue === StoreEnum::HardwareStore->value) {
                return $productStatus['hardware'] ?? null;
            }

            if ($storeValue === StoreEnum::WarehouseStore->value) {
                return $productStatus['warehouse'] ?? null;
            }

            if ($storeValue === StoreEnum::LPO->value) {
                // For LPO, return supplier-wise statuses or specific supplier status
                $lpoStatuses = $productStatus['lpo'] ?? [];
                
                // Handle legacy format where LPO might be a string instead of array
                if (is_string($lpoStatuses)) {
                    // Legacy format: convert string to array format
                    // If supplierId is provided, return the string status, otherwise return null
                    if ($supplierId !== null) {
                        return $lpoStatuses; // Return the legacy string status for the supplier
                    }
                    return null; // Can't map string status to multiple suppliers without supplier mapping
                }
                
                if ($supplierId !== null && is_array($lpoStatuses)) {
                    return $lpoStatuses[(string)$supplierId] ?? null;
                }
                return is_array($lpoStatuses) ? $lpoStatuses : null;
            }

            // If we still don't know, prefer hardware, then warehouse, then lpo
            return $productStatus['hardware']
                ?? $productStatus['warehouse']
                ?? (is_array($productStatus['lpo'] ?? null) ? $productStatus['lpo'] : null)
                ?? null;
        }

        // Convert StoreEnum to string value if needed
        $typeValue = $type instanceof StoreEnum ? $type->value : $type;
        
        if ($typeValue === StoreEnum::HardwareStore->value) {
            return $productStatus['hardware'] ?? null;
        } elseif ($typeValue === StoreEnum::WarehouseStore->value) {
            return $productStatus['warehouse'] ?? null;
        } elseif ($typeValue === StoreEnum::LPO->value) {
            // For LPO, return supplier-wise statuses or specific supplier status
            $lpoStatuses = $productStatus['lpo'] ?? [];
            
            // Handle legacy format where LPO might be a string instead of array
            if (is_string($lpoStatuses)) {
                // Legacy format: convert string to array format
                // If supplierId is provided, return the string status, otherwise return empty array
                if ($supplierId !== null) {
                    return $lpoStatuses; // Return the legacy string status for the supplier
                }
                return []; // Can't map string status to multiple suppliers without supplier mapping
            }
            
            if ($supplierId !== null && is_array($lpoStatuses)) {
                return $lpoStatuses[(string)$supplierId] ?? null;
            }
            return is_array($lpoStatuses) ? $lpoStatuses : [];
        }

        // For custom products or unknown types, return warehouse status
        return $productStatus['warehouse'] ?? null;
    }

    /**
     * Set product status for a specific product type
     * For LPO, set status for a specific supplier
     */
    public function setProductStatus(string $type, string $status, ?int $supplierId = null): void
    {
        $statuses = $this->product_status ?? $this->initializeProductStatus();
        
        if ($type === 'lpo' && $supplierId !== null) {
            // LPO is supplier-wise: set status for specific supplier
            if (!is_array($statuses['lpo'] ?? null)) {
                $statuses['lpo'] = [];
            }
            $statuses['lpo'][(string)$supplierId] = $status;
        } else {
            // Hardware, warehouse, custom: simple string status
            $statuses[$type] = $status;
        }
        
        $this->product_status = $statuses;
    }

    /**
     * Update product status for a specific product type
     */
    public function updateProductStatus(string $type, string $status): bool
    {
        $this->setProductStatus($type, $status);
        return $this->save();
    }

    /**
     * Initialize product_rejection_notes with default structure
     * LPO is managed supplier-wise (object with supplier IDs as keys)
     */
    public function initializeProductRejectionNotes(): array
    {
        return [
            'hardware' => null,
            'warehouse' => null,
            'lpo' => [], // Supplier-wise: {supplier_id: note}
            'custom' => null,
        ];
    }

    /**
     * Get rejection note for a specific product type.
     * For LPO, can optionally get note for a specific supplier.
     * 
     * @param string $type The product type (hardware, warehouse, lpo, custom)
     * @param int|null $supplierId For LPO products, get note for specific supplier
     * @return string|null Returns the rejection note or null if not found
     */
    public function getProductRejectionNote(string $type, ?int $supplierId = null): ?string
    {
        $rejectionNotes = $this->product_rejection_notes ?? $this->initializeProductRejectionNotes();
        
        if ($type === 'lpo' && $supplierId !== null) {
            // LPO is supplier-wise: get note for specific supplier
            $lpoNotes = $rejectionNotes['lpo'] ?? [];
            if (is_array($lpoNotes)) {
                return $lpoNotes[(string)$supplierId] ?? null;
            }
            return null;
        } else {
            // Hardware, warehouse, custom: simple string note
            return $rejectionNotes[$type] ?? null;
        }
    }

    /**
     * Set rejection note for a specific product type
     * For LPO, set note for a specific supplier
     * 
     * @param string $type The product type (hardware, warehouse, lpo, custom)
     * @param string|null $note The rejection note (null to clear)
     * @param int|null $supplierId For LPO products, set note for specific supplier
     */
    public function setProductRejectionNote(string $type, ?string $note, ?int $supplierId = null): void
    {
        $notes = $this->product_rejection_notes ?? $this->initializeProductRejectionNotes();
        
        if ($type === 'lpo' && $supplierId !== null) {
            // LPO is supplier-wise: set note for specific supplier
            if (!is_array($notes['lpo'] ?? null)) {
                $notes['lpo'] = [];
            }
            if ($note === null || trim($note) === '') {
                // Remove note if null or empty
                unset($notes['lpo'][(string)$supplierId]);
            } else {
                $notes['lpo'][(string)$supplierId] = trim($note);
            }
        } else {
            // Hardware, warehouse, custom: simple string note
            if ($note === null || trim($note) === '') {
                $notes[$type] = null;
            } else {
                $notes[$type] = trim($note);
            }
        }
        
        $this->product_rejection_notes = $notes;
    }

    /**
     * Update rejection note for a specific product type
     */
    public function updateProductRejectionNote(string $type, ?string $note, ?int $supplierId = null): bool
    {
        $this->setProductRejectionNote($type, $note, $supplierId);
        return $this->save();
    }

    /**
     * Calculate and update order status based on hardware, warehouse, and LPO product statuses.
     *
     * Uses product_status JSON column. If a product type is null, it doesn't exist in the order.
     *
     * Rules (per user spec):
     * - Single product type (Hardware / Warehouse / LPO):
     *   → Parent order status = that type's status directly (mapped to OrderStatusEnum)
     *
     * - Multiple product types:
     *   1) Parent = Pending
     *      - If ANY product type status is pending
     *
     *   2) Parent = Approved
     *      - If ALL product type statuses are approved
     *      - OR if at least one is rejected and the rest are approved
     *
     *   3) Parent = Delivery (Delivered)
     *      - If ALL product type statuses are delivered
     *      - OR if at least one is rejected and the rest are delivered
     *
     *   4) Parent = Rejected
     *      - If ALL product type statuses are rejected
     *
     *   5) Parent = Out of Delivery
     *      - If ALL product type statuses are out for delivery
     *
     *   - Fallback for mixed edge cases (no pending) → Approved
     *
     * @return string The calculated order status (OrderStatusEnum value)
     */
    public function calculateOrderStatusFromProductStatuses(): string
    {
        $productStatus = $this->product_status ?? [];
        
        // Get statuses, null means product type doesn't exist
        // Check for null, empty string, or string 'null'
        $hardware = isset($productStatus['hardware']) && $productStatus['hardware'] !== null && $productStatus['hardware'] !== '' && $productStatus['hardware'] !== 'null'
            ? $productStatus['hardware']
            : null;
        $warehouse = isset($productStatus['warehouse']) && $productStatus['warehouse'] !== null && $productStatus['warehouse'] !== '' && $productStatus['warehouse'] !== 'null'
            ? $productStatus['warehouse']
            : null;
        $custom = isset($productStatus['custom']) && $productStatus['custom'] !== null && $productStatus['custom'] !== '' && $productStatus['custom'] !== 'null'
            ? $productStatus['custom']
            : null;

        // Treat custom products as warehouse for status calculation when warehouse status is missing
        if ($warehouse === null && $custom !== null) {
            $warehouse = $custom;
        }
        
        // LPO is now supplier-wise (object), calculate combined status
        $lpoStatuses = $productStatus['lpo'] ?? [];
        $lpo = null;
        if (is_array($lpoStatuses) && !empty($lpoStatuses)) {
            // Calculate LPO status from all supplier statuses
            // Priority: rejected > pending > approved > outfordelivery > delivered
            $uniqueStatuses = array_unique(array_values($lpoStatuses));
            if (in_array('rejected', $uniqueStatuses, true)) {
                $lpo = 'rejected';
            } elseif (in_array('pending', $uniqueStatuses, true)) {
                $lpo = 'pending';
            } elseif (in_array('approved', $uniqueStatuses, true)) {
                $lpo = 'approved';
            } elseif (in_array('outfordelivery', $uniqueStatuses, true) || in_array('out_of_delivery', $uniqueStatuses, true) || in_array('outofdelivery', $uniqueStatuses, true)) {
                $lpo = 'outfordelivery';
            } elseif (in_array('delivered', $uniqueStatuses, true)) {
                $lpo = 'delivered';
            }
        }
        
        // Filter out null values to get existing product types
        $existingTypes = array_filter([
            'hardware' => $hardware,
            'warehouse' => $warehouse,
            'lpo' => $lpo,
        ], fn ($value) => $value !== null);
        
        $typeCount = count($existingTypes);
        
        // Single product type: use that type's status directly (map to enum value)
        if ($typeCount === 1) {
            $status = reset($existingTypes);
            return $this->mapProductStatusToOrderStatus($status);
        }
        
        // Multiple product types
        if ($typeCount > 1) {
            // Normalize all statuses to lowercase strings and standardize variations
            $statuses = array_map(
                static function ($s) {
                    $normalized = is_string($s) ? strtolower($s) : (string) $s;
                    // Normalize out for delivery variations to 'outfordelivery'
                    if (in_array($normalized, ['out_of_delivery', 'outofdelivery'], true)) {
                        return 'outfordelivery';
                    }
                    return $normalized;
                },
                array_values($existingTypes)
            );
            
            $hasPending         = in_array('pending', $statuses, true);
            $hasApproved        = in_array('approved', $statuses, true);
            $hasRejected        = in_array('rejected', $statuses, true);
            $hasDelivered       = in_array('delivered', $statuses, true);
            $hasOutForDelivery  = in_array('outfordelivery', $statuses, true);
            
            // 1) Parent = Pending -> if ANY status is pending
            if ($hasPending) {
                return \App\Utility\Enums\OrderStatusEnum::Pending->value;
            }
            
            // Helper sets for easier checks
            $allOutForDelivery = !empty($statuses)
                && count(array_diff($statuses, ['outfordelivery'])) === 0;
            
            $allDeliveredOrRejected = !empty($statuses)
                && count(array_diff($statuses, ['delivered', 'rejected'])) === 0;
            
            $allApprovedOrRejected = !empty($statuses)
                && count(array_diff($statuses, ['approved', 'rejected'])) === 0;
            
            $allRejectedOnly = !empty($statuses)
                && count(array_diff($statuses, ['rejected'])) === 0;
            
            // 5) Parent = Out of delivery -> all are out for delivery
            if ($allOutForDelivery) {
                return \App\Utility\Enums\OrderStatusEnum::OutOfDelivery->value;
            }
            
            // 3) Parent = Delivery (Delivered)
            //    - all delivered
            //    - OR any rejected and rest delivered
            if ($allDeliveredOrRejected && $hasDelivered) {
                return \App\Utility\Enums\OrderStatusEnum::Delivery->value;
            }
            
            // 2) Parent = Approved
            //    - all approved
            //    - OR any rejected and rest approved
            if ($allApprovedOrRejected) {
                if ($hasApproved && !$hasRejected) {
                    // all approved
                    return \App\Utility\Enums\OrderStatusEnum::Approved->value;
                }
                
                if ($hasApproved && $hasRejected) {
                    // mix of approved + rejected
                    return \App\Utility\Enums\OrderStatusEnum::Approved->value;
                }
            }
            
            // 4) Parent = Rejected -> all are rejected
            if ($allRejectedOnly) {
                return \App\Utility\Enums\OrderStatusEnum::Rejected->value;
            }
            
            // Fallback for mixed edge cases with no pending:
            // Treat as Approved (already past pending stage)
            return \App\Utility\Enums\OrderStatusEnum::Approved->value;
        }
        
        // Default fallback: if no specific status found, return Pending
        return \App\Utility\Enums\OrderStatusEnum::Pending->value;
    }
    
    /**
     * Map product status to order status
     * 
     * @param string $productStatus
     * @return string
     */
    private function mapProductStatusToOrderStatus(string $productStatus): string
    {
        $normalized = strtolower($productStatus);
        
        return match ($normalized) {
            'approved' => \App\Utility\Enums\OrderStatusEnum::Approved->value,
            'rejected' => \App\Utility\Enums\OrderStatusEnum::Rejected->value,
            'pending'  => \App\Utility\Enums\OrderStatusEnum::Pending->value,
            'delivered' => \App\Utility\Enums\OrderStatusEnum::Delivery->value,
            'outfordelivery', 'out_of_delivery', 'outofdelivery' => \App\Utility\Enums\OrderStatusEnum::OutOfDelivery->value,
            'in_transit' => \App\Utility\Enums\OrderStatusEnum::InTransit->value,
            default => \App\Utility\Enums\OrderStatusEnum::Pending->value,
        };
    }

    /**
     * Update order status based on product statuses and save
     * 
     * @return bool True if update was successful
     */
    public function syncOrderStatusFromProductStatuses(): bool
    {
        $calculatedStatus = $this->calculateOrderStatusFromProductStatuses();
        
        $updateData = [
            'status' => $calculatedStatus,
        ];
        
        // Also update delivery_status to match if it's pending, approved, or rejected
        if (in_array($calculatedStatus, ['pending', 'approved', 'rejected'], true)) {
            $updateData['delivery_status'] = $calculatedStatus;
        }
        
        return $this->update($updateData);
    }

}

