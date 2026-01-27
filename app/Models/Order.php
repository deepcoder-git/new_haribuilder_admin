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
        'transport_manager_id',
        'site_id',
        'expected_delivery_date',
        'status',
        'priority',
        'note',
        'rejected_note',
        'product_rejection_notes',
        'drop_location',
        'is_lpo',
        'is_custom_product',
        'supplier_id',
        'product_status',
        'product_driver_details'
    ];

    protected $casts = [
        'status' => OrderStatusEnum::class,
        'expected_delivery_date' => 'date',
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

    /**
     * Get store manager based on product types in order
     * Store manager role column removed - determine manager from product types
     */
    public function storeManager()
    {
        // Determine store manager based on product types
        $productStatus = $this->product_status ?? [];
        
        // Check for hardware products
        if (isset($productStatus['hardware']) && $productStatus['hardware'] !== null) {
            return Moderator::where('role', \App\Utility\Enums\RoleEnum::StoreManager->value)
                ->where('status', 'active')
                ->first();
        }
        
        // Check for workshop or custom products
        if ((isset($productStatus['workshop']) && $productStatus['workshop'] !== null) ||
            (isset($productStatus['custom']) && $productStatus['custom'] !== null)) {
            return Moderator::where('role', \App\Utility\Enums\RoleEnum::WorkshopStoreManager->value)
                ->where('status', 'active')
                ->first();
        }
        
        return null;
    }

    public function scopePendingOrders($query)
    {
        return $query->where('status', OrderStatusEnum::Pending->value);
    }

    /**
     * Count pending orders for a given Site Manager (optionally filtered by site)
     */
    public static function countPendingOrdersForSiteManager(int $siteManagerId, ?int $siteId = null): int
    {
        $query = self::query()
            ->where('site_manager_id', $siteManagerId)
            ->where('status', OrderStatusEnum::Pending->value);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    /**
     * Count approved orders for a given Site Manager (optionally filtered by site)
     */
    public static function countApprovedOrdersForSiteManager(int $siteManagerId, ?int $siteId = null): int
    {
        $query = self::query()
            ->where('site_manager_id', $siteManagerId)
            ->where('status', OrderStatusEnum::Approved->value);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    /**
     * Count delivered orders for a given Site Manager (optionally filtered by site)
     */
    public static function countDeliveredOrdersForSiteManager(int $siteManagerId, ?int $siteId = null): int
    {
        $query = self::query()
            ->where('site_manager_id', $siteManagerId)
            ->where('status', OrderStatusEnum::Delivery->value);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    /**
     * Count rejected orders for a given Site Manager (optionally filtered by site)
     */
    public static function countRejectedOrdersForSiteManager(int $siteManagerId, ?int $siteId = null): int
    {
        $query = self::query()
            ->where('site_manager_id', $siteManagerId)
            ->where('status', OrderStatusEnum::Rejected->value);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    /**
     * Count delayed orders for a given Site Manager (optionally filtered by site)
     * Delayed = expected_delivery_date is in the past and status is Approved or InTransit
     */
    public static function countDelayedOrdersForSiteManager(int $siteManagerId, ?int $siteId = null): int
    {
        $query = self::query()
            ->where('site_manager_id', $siteManagerId)
            ->whereDate('expected_delivery_date', '<', Carbon::now())
            ->whereIn('status', [
                OrderStatusEnum::Approved->value,
                OrderStatusEnum::InTransit->value,
            ]);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->count();
    }

    /**
     * Initialize product_status with default values
     * LPO is managed supplier-wise (object with supplier IDs as keys)
     */
    public function initializeProductStatus(): array
    {
        return [
            'hardware' => 'pending',
            'workshop' => 'pending',
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

        // When type is null, determine from product_status keys or product types in order
        if ($type === null) {
            // Check which product statuses exist in the order
            // Priority: hardware > workshop > lpo
            if (isset($productStatus['hardware']) && $productStatus['hardware'] !== null) {
                return $productStatus['hardware'];
            }

            if (isset($productStatus['workshop']) && $productStatus['workshop'] !== null) {
                return $productStatus['workshop'];
            }

            if (isset($productStatus['lpo']) && !empty($productStatus['lpo'])) {
                // For LPO, return supplier-wise statuses or specific supplier status
                $lpoStatuses = $productStatus['lpo'];
                
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

            // If we still don't know, prefer hardware, then workshop, then lpo
            return $productStatus['hardware']
                ?? $productStatus['workshop']
                ?? (is_array($productStatus['lpo'] ?? null) ? $productStatus['lpo'] : null)
                ?? null;
        }

        // Convert StoreEnum to string value if needed
        $typeValue = $type instanceof StoreEnum ? $type->value : $type;
        
        if ($typeValue === StoreEnum::HardwareStore->value) {
            return $productStatus['hardware'] ?? null;
        } elseif ($typeValue === StoreEnum::WarehouseStore->value) {
            return $productStatus['workshop'] ?? null;
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

        // For custom products or unknown types, return workshop status
        return $productStatus['workshop'] ?? null;
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
            // Hardware, workshop, custom: simple string status
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
            'workshop' => null,
            'lpo' => [], // Supplier-wise: {supplier_id: note}
            'custom' => null,
        ];
    }

    /**
     * Get rejection note for a specific product type.
     * For LPO, can optionally get note for a specific supplier.
     * 
     * @param string $type The product type (hardware, workshop, lpo, custom)
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
            // Hardware, workshop, custom: simple string note
            return $rejectionNotes[$type] ?? null;
        }
    }

    /**
     * Set rejection note for a specific product type
     * For LPO, set note for a specific supplier
     * 
     * @param string $type The product type (hardware, workshop, lpo, custom)
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
            // Hardware, workshop, custom: simple string note
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
     * Calculate and update order status based on hardware, workshop, and LPO product statuses.
     *
     * Uses product_status JSON column. If a product type is null, it doesn't exist in the order.
     *
     * Rules (per user spec):
     * - Single product type (Hardware / Workshop / LPO):
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
        $workshop = isset($productStatus['workshop']) && $productStatus['workshop'] !== null && $productStatus['workshop'] !== '' && $productStatus['workshop'] !== 'null'
            ? $productStatus['workshop']
            : null;
        $custom = isset($productStatus['custom']) && $productStatus['custom'] !== null && $productStatus['custom'] !== '' && $productStatus['custom'] !== 'null'
            ? $productStatus['custom']
            : null;

        // Treat custom products as workshop for status calculation when workshop status is missing
        if ($workshop === null && $custom !== null) {
            $workshop = $custom;
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
            'workshop' => $workshop,
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
        return $this->update($updateData);
    }

}
