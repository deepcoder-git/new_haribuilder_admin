<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Order;

use App\Models\Order;
use App\Models\OrderCustomProduct;
use App\Models\OrderCustomProductImage;
use App\Models\Moderator;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Site;
use App\Models\Supplier;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\TransportManagerAssignedNotification;
use App\Services\StockService;
use App\Src\Admin\Modules\Order\Requests\StoreOrderRequest;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\ProductTypeEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use App\Utility\Traits\ConvertsDecimalToInteger;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use Carbon\Carbon;
use Illuminate\Validation\Rules\In;

class OrderForm extends Component
{
    use WithFileUploads, ConvertsDecimalToInteger;

    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public ?string $status = 'pending';
    public ?string $site_manager_id = null;
    public ?string $transport_manager_id = null;
    public ?string $site_id = null;
    private string $previous_status = 'pending';
    public string $original_status = 'pending';
    public ?string $expected_delivery_date = null;
    public ?string $drop_location = null;
    public ?string $priority = null;
    public ?string $note = null;
    public ?string $rejected_note = null; // Rejection note for rejected orders
    public ?string $order_store = null; // Store the order's store for filtering products in edit mode
    
    public bool $showInTransitModal = false;
    public ?string $temp_driver_name = null;
    public ?string $temp_vehicle_number = null;
    
    // Product status in_transit modal state
    public bool $showProductInTransitModal = false;
    public ?string $productInTransitType = null; // 'hardware', 'workshop', 'lpo', 'custom'
    public ?string $productTempDriverName = null;
    public ?string $productTempVehicleNumber = null;
    public array $productDriverDetails = []; // Store driver details per product type
    public array $product_driver_details = []; // Public property for validation (maps to productDriverDetails)
    public ?string $productPreviousStatus = null; // Store previous status when modal opens
    
    // Product status out for delivery modal state
    public bool $showProductOutForDeliveryModal = false;
    public ?string $productOutForDeliveryType = null; // 'hardware', 'workshop', 'lpo', 'custom'
    public ?string $productOutForDeliveryDriverName = null;
    public ?string $productOutForDeliveryVehicleNumber = null;
    public ?string $productOutForDeliveryPreviousStatus = null; // Store previous status when modal opens
    
    // Custom product edit popup state
    public bool $showCustomProductModal = false;
    public ?int $editingCustomProductIndex = null;
    public array $customProductPopupProducts = []; // Products selected in popup
    public array $customProductPopupMaterials = []; // Materials for selected products
    public array $customProductPopupSearch = [];
    public array $customProductPopupResults = [];
    public bool $customProductPopupDropdownOpen = false;
    public string $customProductPopupSearchTerm = '';
    public int $customProductPopupPage = 1;
    public bool $customProductPopupHasMore = false;
    public bool $customProductPopupLoading = false;
    
    // Expanded custom products (to show/hide connected products)
    public array $expandedCustomProducts = [];
    
    // Rejection details modal
    public bool $showRejectionDetailsModal = false;
    public array $rejectionDetailsProductStatuses = [];
    public array $productRejectionNotes = []; // Store rejection notes per product type: ['hardware' => 'note', 'workshop' => 'note', 'lpo' => ['supplier_id' => 'note'], 'custom' => 'note']
    public ?string $currentRejectionType = null; // Current product type being edited in modal
    public ?string $pendingRejectionType = null; // When selecting "rejected" from dropdown: store pending type until modal Save
    public ?string $pendingRejectionPreviousStatus = null; // Previous status to revert if modal is closed/cancelled
    protected bool $suppressAutoOpenRejectionModal = false; // Internal: avoid reopening modal when we are already in a rejection flow
    
    public array $orderProducts = [];
    
    // Product search dropdown state for each row
    public array $productSearch = [];
    public array $productSearchResults = [];
    public array $productDropdownOpen = [];
    public array $productPage = [];
    public array $productHasMore = [];
    public array $productLoading = [];
    
    // Site search dropdown state
    public string $siteSearch = '';
    public array $siteSearchResults = [];
    public bool $siteDropdownOpen = false;
    public int $sitePage = 1;
    public bool $siteHasMore = false;
    public bool $siteLoading = false;
    
    // Site Manager search dropdown state
    public string $siteManagerSearch = '';
    public array $siteManagerSearchResults = [];
    public bool $siteManagerDropdownOpen = false;
    public int $siteManagerPage = 1;
    public bool $siteManagerHasMore = false;
    public bool $siteManagerLoading = false;
    
    // Status dropdown state
    public bool $statusDropdownOpen = false;
    
    // Product Status for each product group (edit mode only)
    public array $productStatuses = [
        'hardware' => 'pending',
        'workshop' => 'pending',
        'lpo' => 'pending',
    ];

    // Persistent (user-friendly) status messages per group.
    // We avoid session()->flash here because Livewire re-renders can consume flash quickly.
    public array $productStatusErrors = [];   // ['hardware' => '...', 'workshop' => '...']
    public array $productStatusSuccess = [];  // ['hardware' => '...', 'workshop' => '...']

    public function clearProductStatusMessage(string $type): void
    {
        unset($this->productStatusErrors[$type], $this->productStatusSuccess[$type]);
    }

    protected function setProductStatusError(string $type, string $message): void
    {
        $this->productStatusErrors[$type] = $message;
        unset($this->productStatusSuccess[$type]);
    }

    protected function setProductStatusSuccess(string $type, string $message): void
    {
        $this->productStatusSuccess[$type] = $message;
        unset($this->productStatusErrors[$type]);
    }

    // Supplier search dropdown state for each row (for LPO products)
    public array $supplierSearch = [];
    public array $supplierSearchResults = [];
    public array $supplierDropdownOpen = [];
    public array $supplierPage = [];
    public array $supplierHasMore = [];
    public array $supplierLoading = [];

    protected ?StockService $stockService = null;

    public function boot(): void
    {
        $this->stockService = app(StockService::class);
    }

    /**
     * Calculate display order status based on hardware, warehouse, and LPO product statuses.
     * Mirrors the API/mobile logic in OrderResource so admin panel shows the same status.
     */
    protected function calculateOrderStatusFromProductStatus(Order $order): string
    {
        $productStatus = $order->product_status ?? [];
        
        // Get statuses, null means product type doesn't exist
        $hardware = isset($productStatus['hardware']) && $productStatus['hardware'] !== null && $productStatus['hardware'] !== '' && $productStatus['hardware'] !== 'null'
            ? $productStatus['hardware']
            : null;
        $workshop = isset($productStatus['workshop']) && $productStatus['workshop'] !== null && $productStatus['workshop'] !== '' && $productStatus['workshop'] !== 'null'
            ? $productStatus['workshop']
            : null;
        $custom = isset($productStatus['custom']) && $productStatus['custom'] !== null && $productStatus['custom'] !== '' && $productStatus['custom'] !== 'null'
            ? $productStatus['custom']
            : null;

        // Map workshop bucket for combined status calculations
        $workshop = isset($productStatus['workshop']) && $productStatus['workshop'] !== null && $productStatus['workshop'] !== '' && $productStatus['workshop'] !== 'null'
            ? $productStatus['workshop']
            : null;

        // Treat custom products as workshop for status calculation when workshop status is missing
        if ($workshop === null && $custom !== null) {
            $workshop = $custom;
        }
        
        // LPO is supplier-wise (object), calculate combined status
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
        
        // Case 1: Hardware only - use hardware status directly
        if ($hardware !== null && $workshop === null && $lpo === null) {
            return $this->mapProductStatusToOrderStatus($hardware);
        }
        
        // Case 2: Workshop only - use workshop status directly
        if ($workshop !== null && $hardware === null && $lpo === null) {
            return $this->mapProductStatusToOrderStatus($workshop);
        }
        
        // Case 3: Hardware + Workshop combinations (manage all cases)
        if ($hardware !== null && $workshop !== null && $lpo === null) {
            // Both rejected → Rejected
            if ($hardware === 'rejected' || $workshop === 'rejected') {
                return 'rejected';
            }
            
            // Both approved → Approved
            if ($hardware === 'approved' && $workshop === 'approved') {
                return 'approved';
            }
            
            // hardware=approved, workshop=rejected → Approved (hardware approved takes priority)
            if ($hardware === 'approved' && $workshop === 'rejected') {
                return 'approved';
            }
            
            // hardware=pending, workshop=approved → Pending
            if ($hardware === 'pending' && $workshop === 'approved') {
                return 'pending';
            }
            
            // hardware=pending, workshop=pending → Pending
            if ($hardware === 'pending' && $workshop === 'pending') {
                return 'pending';
            }
            
            // hardware=pending, workshop=rejected → Pending
            if ($hardware === 'pending' && $workshop === 'rejected') {
                return 'pending';
            }
            
            // Default: if hardware is approved and workshop is pending → Approved
            if ($hardware === 'approved' && $workshop === 'pending') {
                return 'approved';
            }
            
            // Fallback
            return 'pending';
        }
        
        // Case 4: Hardware + Workshop + LPO combinations
        if ($hardware !== null && $workshop !== null && $lpo !== null) {
            // Rule 1: hardware=pending, workshop=approved, lpo=pending → "Pending"
            if ($hardware === 'pending' && $workshop === 'approved' && $lpo === 'pending') {
                return 'pending';
            }
            
            // Rule 2: hardware=approved, workshop=approved, lpo=rejected → "Approved"
            if ($hardware === 'approved' && $workshop === 'approved' && $lpo === 'rejected') {
                return 'approved';
            }
            
            // Rule 3: hardware=approved, workshop=rejected, lpo=approved → "Approved"
            if ($hardware === 'approved' && $workshop === 'rejected' && $lpo === 'approved') {
                return 'approved';
            }
            
            // If hardware is approved AND at least one of (workshop OR lpo) is approved → Approved
            if ($hardware === 'approved' && ($workshop === 'approved' || $lpo === 'approved')) {
                return 'approved';
            }
            
            // If any is rejected (and hardware rule doesn't apply) → Rejected
            if ($hardware === 'rejected' || $workshop === 'rejected' || $lpo === 'rejected') {
                return 'rejected';
            }
            
            // All pending → Pending
            if ($hardware === 'pending' && $workshop === 'pending' && $lpo === 'pending') {
                return 'pending';
            }
            
            // All approved → Approved
            if ($hardware === 'approved' && $workshop === 'approved' && $lpo === 'approved') {
                return 'approved';
            }
        }
        
        // Handle Hardware + LPO (without workshop)
        if ($hardware !== null && $lpo !== null && $workshop === null) {
            if ($hardware === 'rejected' || $lpo === 'rejected') {
                return 'rejected';
            }
            if ($hardware === 'pending') {
                return 'pending';
            }
            if ($hardware === 'approved' && $lpo === 'approved') {
                return 'approved';
            }
            if ($hardware === 'approved') {
                return 'approved';
            }
            return 'pending';
        }
        
        // Handle Workshop + LPO (without hardware)
        if ($workshop !== null && $lpo !== null && $hardware === null) {
            if ($workshop === 'rejected' || $lpo === 'rejected') {
                return 'rejected';
            }
            if ($workshop === 'pending' && $lpo === 'pending') {
                return 'pending';
            }
            if ($workshop === 'approved' && $lpo === 'approved') {
                return 'approved';
            }
            return 'approved';
        }
        
        // Handle LPO only
        if ($lpo !== null && $hardware === null && $workshop === null) {
            return $this->mapProductStatusToOrderStatusForDisplay($lpo);
        }
        
        // Default fallback: if no specific status found, return pending
        return 'pending';
    }
    
    /**
     * Map product-level status string to order-level status string for display.
     * Includes extra states used in admin/app: out of delivery, delivered, cancelled.
     *
     * NOTE: This is intentionally named mapProductStatusToOrderStatus so it can be reused
     * in the same way as in OrderResource / Order model.
     */
    protected function mapProductStatusToOrderStatus(string $productStatus): string
    {
        $normalized = strtolower($productStatus);
    
        return match ($normalized) {
            'approved'       => 'approved',
            'rejected'       => 'rejected',
            'pending'        => 'pending',
            'outfordelivery', 'out_of_delivery', 'outofdelivery' => 'outfordelivery',
            // orders.status uses 'delivery' for delivered orders
            'delivered'      => OrderStatusEnum::Delivery->value,
            'in_transit'     => 'in_transit',
            'cancelled'      => 'cancelled',
            default          => 'pending',
        };
    }

    /**
     * Backwards compatible alias specifically used in the LPO-only branch.
     * Currently just delegates to mapProductStatusToOrderStatus.
     */
    protected function mapProductStatusToOrderStatusForDisplay(string $productStatus): string
    {
        return $this->mapProductStatusToOrderStatus($productStatus);
    }

    public function mount(?int $id = null): void
    {
        $this->normalizeProductsArray();
        
        if ($id) {
            $order = Order::with(['site', 'products.productImages', 'siteManager', 'transportManager'])->findOrFail($id);
            
            // Prevent editing delivered orders - redirect to view instead (orders.is_completed removed)
            $statusValue = $order->status?->value ?? ($order->status ?? OrderStatusEnum::Pending->value);
            if ((string)$statusValue === OrderStatusEnum::Delivery->value) {
                $this->redirect(route('admin.orders.view', $id));
                return;
            }
            
            $this->isEditMode = true;
            $this->editingId = $id;
            $this->setFormData($order);
        } else {
            $this->priority = PriorityEnum::High->value;
            // Ensure default row is present when creating new order
            $this->normalizeProductsArray();
        }
    }

    protected function normalizeProductsArray(): void
    {
        if (!is_array($this->orderProducts)) {
            $this->orderProducts = [];
        }
        
        // Always ensure at least one default row when creating a new order (not in edit mode)
        if (!$this->isEditMode && empty($this->orderProducts)) {
            $this->orderProducts = [['product_id' => '', 'quantity' => '', 'is_custom' => 0, 'custom_note' => '', 'custom_images' => [], 'product_type' => 'hardware', 'product_ids' => [], 'supplier_id' => null]];
            // Initialize dropdown state for new row
            $this->productDropdownOpen[0] = false;
            $this->productSearch[0] = '';
            $this->productSearchResults[0] = [];
            $this->productPage[0] = 1;
            $this->productHasMore[0] = false;
            $this->productLoading[0] = false;
            $this->supplierDropdownOpen[0] = false;
            $this->supplierSearch[0] = '';
            $this->supplierSearchResults[0] = [];
            $this->supplierPage[0] = 1;
            $this->supplierHasMore[0] = false;
            $this->supplierLoading[0] = false;
        }
        
        $normalized = [];
        $hasEmptyRow = false; // Track if we've already added an empty row
        $pendingStatus = $this->status === 'pending'; // Allow editing if status is pending
        
        foreach ($this->orderProducts as $index => $product) {
            if (!is_array($product)) {
                $normalized[] = ['product_id' => '', 'quantity' => '', 'is_custom' => 0, 'custom_note' => '', 'custom_images' => [], 'product_type' => 'hardware', 'product_ids' => [], 'custom_product_id' => null, 'supplier_id' => null];
                $hasEmptyRow = true;
            } else {
                $customImages = $product['custom_images'] ?? [];
                if (!is_array($customImages)) {
                    $customImages = $customImages ? [$customImages] : [];
                }
                
                // Preserve product_ids if they exist
                $productIds = $product['product_ids'] ?? [];
                if (!is_array($productIds)) {
                    if (is_string($productIds)) {
                        $decoded = json_decode($productIds, true);
                        $productIds = is_array($decoded) ? $decoded : [];
                    } elseif (is_numeric($productIds)) {
                        $productIds = [(int)$productIds];
                    } else {
                        $productIds = [];
                    }
                }
                
                $isCustom = $this->normalizeBoolean($product['is_custom'] ?? 0);
                $productId = $product['product_id'] ?? '';
                $customNote = trim($product['custom_note'] ?? '');
                
                // Skip completely empty rows (no product_id, no custom_note, no custom_images, no custom_product_id)
                $hasCustomProductId = !empty($product['custom_product_id'] ?? null);
                $isEmptyRow = (!$isCustom && empty($productId) && !$hasCustomProductId) || 
                             ($isCustom && empty($customNote) && empty($customImages) && !$hasCustomProductId);
                
                if ($isEmptyRow) {
                    // In create mode OR edit mode with pending status: keep all empty rows to allow users to add multiple rows
                    // In edit mode with non-pending status: skip empty rows (order is locked)
                    if (!$this->isEditMode || $pendingStatus) {
                        $normalized[] = [
                            'product_id' => $productId,
                            'quantity' => $product['quantity'] ?? '',
                            'is_custom' => $isCustom ? 1 : 0,
                            'custom_note' => $product['custom_note'] ?? '',
                            'custom_images' => $customImages,
                            'product_type' => $product['product_type'] ?? 'hardware',
                            'product_ids' => $productIds,
                            'custom_product_id' => $product['custom_product_id'] ?? null,
                            'supplier_id' => $product['supplier_id'] ?? null,
                        ];
                        $hasEmptyRow = true; // Track that we have at least one empty row
                    }
                    // Skip empty rows in edit mode with non-pending status
                    continue;
                }
                
                $normalized[] = [
                    'product_id' => $productId,
                    'quantity' => $product['quantity'] ?? '',
                    'is_custom' => $isCustom ? 1 : 0,
                    'custom_note' => $product['custom_note'] ?? '',
                    'custom_images' => $customImages,
                    'product_type' => $product['product_type'] ?? 'hardware',
                    'product_ids' => $productIds, // Preserve product_ids
                    'custom_product_id' => $product['custom_product_id'] ?? null, // Preserve custom_product_id
                    'supplier_id' => $product['supplier_id'] ?? null, // Preserve supplier_id
                ];
            }
        }
        
        $this->orderProducts = array_values($normalized);
        
        $pendingStatus = $this->status === 'pending'; // Allow editing if status is pending
        
        // After normalization, ensure at least one default empty row exists in create mode or edit mode with pending status
        if ((!$this->isEditMode || $pendingStatus) && !$hasEmptyRow && empty($this->orderProducts)) {
            $this->orderProducts = [['product_id' => '', 'quantity' => '', 'is_custom' => 0, 'custom_note' => '', 'custom_images' => [], 'product_type' => 'hardware', 'product_ids' => [], 'supplier_id' => null]];
            // Initialize dropdown state for new row
            $this->productDropdownOpen[0] = false;
            $this->productSearch[0] = '';
            $this->productSearchResults[0] = [];
            $this->productPage[0] = 1;
            $this->productHasMore[0] = false;
            $this->productLoading[0] = false;
            $this->supplierDropdownOpen[0] = false;
            $this->supplierSearch[0] = '';
            $this->supplierSearchResults[0] = [];
            $this->supplierPage[0] = 1;
            $this->supplierHasMore[0] = false;
            $this->supplierLoading[0] = false;
        }
    }

    protected function initializeProducts(): void
    {
        $this->normalizeProductsArray();
    }

    public function addProductRow(): void
    {
        // Normalize existing products first
        $this->normalizeProductsArray();
        
        // Add new row with all required fields
        $newIndex = count($this->orderProducts);
        $this->orderProducts[] = [
            'product_id' => '', 
            'quantity' => '', 
            'is_custom' => 0, 
            'custom_note' => '', 
            'custom_images' => [],
            'product_type' => 'hardware', // Default type
            'product_ids' => [], // Required field
            'custom_product_id' => null, // Required field
            'supplier_id' => null,
        ];
        $this->orderProducts = array_values($this->orderProducts);
        
        // Initialize dropdown state for new row
        $this->productDropdownOpen[$newIndex] = false;
        $this->productSearch[$newIndex] = '';
        $this->productSearchResults[$newIndex] = [];
        $this->productPage[$newIndex] = 1;
        $this->productHasMore[$newIndex] = false;
        $this->productLoading[$newIndex] = false;
        
        // Initialize supplier dropdown state
        $this->supplierDropdownOpen[$newIndex] = false;
        $this->supplierSearch[$newIndex] = '';
        $this->supplierSearchResults[$newIndex] = [];
        $this->supplierPage[$newIndex] = 1;
        $this->supplierHasMore[$newIndex] = false;
        $this->supplierLoading[$newIndex] = false;
        
        // Don't normalize again here - let it happen naturally on next render
        // This ensures the new row is preserved
    }

    /**
     * Add a product row to a specific group (hardware, workshop, lpo)
     */
    public function addProductRowToGroup(string $groupType): void
    {
        $this->initializeProducts();
        
        // Validate group type
        if (!in_array($groupType, ['hardware', 'workshop', 'lpo'])) {
            $groupType = 'hardware';
        }
        
        $newIndex = count($this->orderProducts);
        $this->orderProducts[] = [
            'product_id' => '', 
            'quantity' => '', 
            'is_custom' => 0, // Can be regular or custom for workshop
            'custom_note' => '', 
            'custom_images' => [],
            'product_type' => $groupType,
            'product_ids' => [],
            'custom_product_id' => null,
            'supplier_id' => null,
        ];
        $this->orderProducts = array_values($this->orderProducts);
        
        // Initialize dropdown state for new row
        $this->productDropdownOpen[$newIndex] = false;
        $this->productSearch[$newIndex] = '';
        $this->productSearchResults[$newIndex] = [];
        $this->productPage[$newIndex] = 1;
        $this->productHasMore[$newIndex] = false;
        $this->productLoading[$newIndex] = false;
        
        // Initialize supplier dropdown state
        $this->supplierDropdownOpen[$newIndex] = false;
        $this->supplierSearch[$newIndex] = '';
        $this->supplierSearchResults[$newIndex] = [];
        $this->supplierPage[$newIndex] = 1;
        $this->supplierHasMore[$newIndex] = false;
        $this->supplierLoading[$newIndex] = false;
    }

    /**
     * Add a custom product row to workshop group
     */
    public function addCustomProductRowToGroup(): void
    {
        $this->initializeProducts();
        $newIndex = count($this->orderProducts);
        $this->orderProducts[] = [
            'product_id' => '',
            'quantity' => '', // Do not set a default quantity; let the user input it
            'is_custom' => 1,
            'custom_note' => '',
            'custom_images' => [],
            'product_type' => 'workshop', // Custom products belong to workshop
        ];
        $this->orderProducts = array_values($this->orderProducts);
    }

    public function addCustomProductRow(): void
    {
        $this->initializeProducts();
        $this->orderProducts[] = [
            'product_id' => '', 
            'quantity' => '', 
            'is_custom' => 1, 
            'custom_note' => '', 
            'custom_images' => [],
            'product_type' => 'workshop', // Custom products belong to workshop
        ];
        $this->orderProducts = array_values($this->orderProducts);
    }

    public function toggleCustomProduct($index): void
    {
        $this->normalizeProductsArray();
        
        if (!isset($this->orderProducts[$index])) {
            $this->orderProducts[$index] = ['product_id' => '', 'quantity' => '', 'is_custom' => 0, 'custom_note' => '', 'custom_images' => []];
        }
        
        $this->orderProducts[$index]['is_custom'] = !($this->orderProducts[$index]['is_custom'] ?? 0);
        
        if ($this->orderProducts[$index]['is_custom']) {
            $this->orderProducts[$index]['product_id'] = '';
            $this->orderProducts[$index]['quantity'] = '';
        } else {
            $this->orderProducts[$index]['custom_note'] = '';
            $this->orderProducts[$index]['custom_images'] = [];
        }
    }

    public function removeProductRow(int $index): void
    {
        if (is_array($this->orderProducts) && isset($this->orderProducts[$index])) {
            unset($this->orderProducts[$index]);
            $this->orderProducts = array_values($this->orderProducts);
        }
        
        if (empty($this->orderProducts)) {
            $this->initializeProducts();
        }
    }

    public function updateProductField($index, $field, $value): void
    {
        $this->normalizeProductsArray();
        
        if (!isset($this->orderProducts[$index])) {
            $this->orderProducts[$index] = ['product_id' => '', 'quantity' => '', 'is_custom' => 0, 'custom_note' => '', 'custom_images' => []];
        }
        
        if (!in_array($field, ['product_id', 'quantity', 'is_custom', 'custom_note', 'custom_images'])) {
            return;
        }
        
        if ($field === 'is_custom') {
            $this->orderProducts[$index][$field] = (bool)$value;
        } elseif ($field === 'custom_images') {
            if (!is_array($value)) {
                $value = $value ? [$value] : [];
            }
            $this->orderProducts[$index][$field] = $value;
        } elseif ($field === 'quantity') {
            // Convert decimal to integer for quantity
            $this->orderProducts[$index][$field] = $this->convertToIntegerString($value);
        } elseif ($field === 'product_id') {
            $normalizedValue = $value ? (string)$value : '';
            
            if ($normalizedValue) {
                $isAlreadySelected = $this->isProductAlreadySelected($normalizedValue, $index);
                if ($isAlreadySelected) {
                    $this->dispatch('show-toast', ['type' => 'error', 'message' => 'This product is already selected in another row.']);
                    return;
                }
            }
            
            $this->orderProducts[$index][$field] = $normalizedValue;
        } else {
            $normalizedValue = $value ? (string)$value : '';
            $this->orderProducts[$index][$field] = $normalizedValue;
        }
    }
    
    protected function isProductAlreadySelected(string $productId, int $currentIndex): bool
    {
        $this->normalizeProductsArray();
        
        foreach ($this->orderProducts as $index => $product) {
            if ($index === $currentIndex) {
                continue;
            }
            
            $isCustom = $product['is_custom'] ?? 0;
            if ($isCustom) {
                continue;
            }
            
            $existingProductId = $product['product_id'] ?? '';
            if ($existingProductId && (string)$existingProductId === (string)$productId) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getAvailableProductsForRow(int $rowIndex)
    {
        $this->normalizeProductsArray();
        $selectedProductIds = [];
        
        foreach ($this->orderProducts as $index => $product) {
            if ($index === $rowIndex) {
                continue;
            }
            
            $isCustom = $product['is_custom'] ?? 0;
            if ($isCustom) {
                continue;
            }
            
            $productId = $product['product_id'] ?? '';
            if ($productId) {
                $selectedProductIds[] = (string)$productId;
            }
        }
        
        return $this->products->reject(function ($product) use ($selectedProductIds) {
            return in_array((string)$product->id, $selectedProductIds);
        });
    }

    public function incrementQuantity($index): void
    {
        $this->normalizeProductsArray();
        
        if (isset($this->orderProducts[$index])) {
            $currentQty = (int)($this->orderProducts[$index]['quantity'] ?? 0);
            // Increment by 1 for integer values
            $newQty = $currentQty + 1;
            $this->orderProducts[$index]['quantity'] = (string)$newQty;
        }
    }

    public function decrementQuantity($index): void
    {
        $this->normalizeProductsArray();
        
        if (isset($this->orderProducts[$index])) {
            $currentQty = (int)($this->orderProducts[$index]['quantity'] ?? 0);
            // Decrement by 1, but don't go below 1 (minimum for orders)
            $newQty = max(1, $currentQty - 1);
            $this->orderProducts[$index]['quantity'] = (string)$newQty;
        }
    }

    public function updatedOrderProducts($value, $key): void
    {
        // When quantity is updated directly via input, convert to integer
        if (str_ends_with($key, '.quantity')) {
            $parts = explode('.', $key);
            $index = (int) $parts[0];
            
            if (isset($this->orderProducts[$index])) {
                $quantity = $this->orderProducts[$index]['quantity'] ?? '0';
                $integerQty = (int)(float)$quantity;
                $integerQty = max(1, $integerQty); // Minimum 1 for orders
                $this->orderProducts[$index]['quantity'] = (string)$integerQty;
            }
        }
    }

    public function addCustomImage($index, $image): void
    {
        $this->normalizeProductsArray();
        
        if (!isset($this->orderProducts[$index])) {
            $this->orderProducts[$index] = ['product_id' => '', 'quantity' => '', 'is_custom' => 0, 'custom_note' => '', 'custom_images' => []];
        }
        
        if (!is_array($this->orderProducts[$index]['custom_images'] ?? null)) {
            $this->orderProducts[$index]['custom_images'] = [];
        }
        
        if ($image) {
            $this->orderProducts[$index]['custom_images'][] = $image;
        }
    }

    public function removeCustomImage($index, $imageIndex): void
    {
        $this->normalizeProductsArray();
        
        if (isset($this->orderProducts[$index]['custom_images']) && is_array($this->orderProducts[$index]['custom_images'])) {
            unset($this->orderProducts[$index]['custom_images'][$imageIndex]);
            $this->orderProducts[$index]['custom_images'] = array_values($this->orderProducts[$index]['custom_images']);
        }
    }

    public function getCurrentStockForProduct($productId = null, $siteId = null): int
    {
        $pid = $productId;
        
        if (!$pid) {
            return 0;
        }

        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        // Admin order flow deducts from GENERAL stock (site_id = null).
        // Show the same stock source in UI to avoid mismatches.
        return max(0, $this->stockService->getCurrentStock((int)$pid, null));
    }

    public function updatedSiteId($value): void
    {
        if ($value) {
            $site = Site::find($value);
            if ($site) {
                if ($site->site_manager_id) {
                    $this->site_manager_id = (string)$site->site_manager_id;
                } else {
                    $this->site_manager_id = null;
                }
                // Auto-fill site location into drop_location
                $this->drop_location = !empty($site->location) ? $site->location : (!empty($site->address) ? $site->address : null);
            } else {
                $this->site_manager_id = null;
                $this->drop_location = null;
            }
        } else {
            $this->site_manager_id = null;
            $this->drop_location = null;
        }
        
        $this->dispatch('site-changed');
    }

    public function updatedApprovalStatus($value): void
    {
        if ($value !== 'approved') {
            $this->transport_manager_id = null;
        }
    }

    /**
     * Handle status value for LPO orders (delivered -> delivery enum)
     */
    protected function normalizeStatusForOrder(string $statusValue, ?Order $order = null): string
    {
        // For delivered status, check if it's LPO to use Delivery enum instead of Completed
        if ($statusValue === 'delivered' && $order && $order->is_lpo) {
            return OrderStatusEnum::Delivery->value;
        }
        return $statusValue;
    }

    /**
     * Map order status to product statuses
     * Returns array of product type => status mappings
     * Based on business rules:
     * - Pending: hardware=pending, warehouse=approved, lpo=pending
     * - Approved: hardware=approved, warehouse=approved, lpo can vary (approved/rejected)
     */
    protected function mapOrderStatusToProductStatuses(string $orderStatus): array
    {
        return match($orderStatus) {
            'pending' => [
                'hardware' => 'pending',
                'workshop' => 'approved', // Warehouse is approved even when order is pending
                'lpo' => 'pending',
                'custom' => 'pending',
            ],
            'approved' => [
                'hardware' => 'approved',
                'workshop' => 'approved',
                'lpo' => 'approved', // Default to approved, but can be manually changed to rejected
                'custom' => 'approved',
            ],
            'in_transit' => [
                'hardware' => 'in_transit',
                'workshop' => 'in_transit',
                'lpo' => 'in_transit',
                'custom' => 'in_transit',
            ],
            'outfordelivery' => [
                'hardware' => 'outfordelivery',
                'workshop' => 'outfordelivery',
                'lpo' => 'outfordelivery',
                'custom' => 'outfordelivery',
            ],
            'delivered', 'completed' => [
                'hardware' => 'delivered',
                'workshop' => 'delivered',
                'lpo' => 'delivered',
                'custom' => 'delivered',
            ],
            'rejected' => [
                'hardware' => 'rejected',
                'workshop' => 'rejected',
                'lpo' => 'rejected',
                'custom' => 'rejected',
            ],
            default => [
                'hardware' => 'pending',
                'workshop' => 'pending',
                'lpo' => 'pending',
                'custom' => 'pending',
            ],
        };
    }

    /**
     * Update product statuses based on order status
     */
    protected function syncProductStatusesFromOrderStatus(string $orderStatus, ?Order $order = null): void
    {
        if (!$this->isEditMode || !$this->editingId) {
            return;
        }

        try {
            if (!$order) {
                $order = Order::find($this->editingId);
            }

            if (!$order) {
                return;
            }

            // Get mapped product statuses for this order status
            $mappedStatuses = $this->mapOrderStatusToProductStatuses($orderStatus);
            
            // Get current product status or initialize
            $currentProductStatus = $order->product_status ?? $order->initializeProductStatus();
            
            // Update product statuses based on order status
            // Only update if the product type exists in the order
            $hasHardware = false;
            $hasWarehouse = false;
            $hasLpo = false;
            $hasCustom = false;

            // Check which product types exist in the order
            if ($order->products) {
                foreach ($order->products as $product) {
                    if ($product->store === StoreEnum::HardwareStore) {
                        $hasHardware = true;
                    } elseif ($product->store === StoreEnum::WarehouseStore) {
                        $hasWarehouse = true;
                    } elseif ($product->store === StoreEnum::LPO) {
                        $hasLpo = true;
                    }
                }
            }

            if ($order->customProducts && $order->customProducts->count() > 0) {
                $hasCustom = true;
            }

            // Update only the product types that exist using Order model methods (same as API)
            if ($hasHardware && isset($mappedStatuses['hardware'])) {
                $order->setProductStatus('hardware', $mappedStatuses['hardware']);
                $this->productStatuses['hardware'] = $mappedStatuses['hardware'];
            }
            if ($hasWarehouse && isset($mappedStatuses['workshop'])) {
                // Map underlying 'warehouse' bucket from DB to workshop UI key
                $order->setProductStatus('workshop', $mappedStatuses['workshop']);
                $this->productStatuses['workshop'] = $mappedStatuses['workshop'];
            }
            if ($hasLpo && isset($mappedStatuses['lpo'])) {
                // For LPO, if mapped status is a string, apply to all suppliers
                // If it's an array, apply supplier-wise
                if (is_array($mappedStatuses['lpo'])) {
                    foreach ($mappedStatuses['lpo'] as $supplierId => $status) {
                        $order->setProductStatus('lpo', $status, (int)$supplierId);
                    }
                } else {
                    // Single status for all LPO suppliers - get supplier IDs from order
                    $supplierMapping = $order->supplier_id ?? [];
                    foreach ($supplierMapping as $productId => $supplierId) {
                        $order->setProductStatus('lpo', $mappedStatuses['lpo'], (int)$supplierId);
                    }
                }
                $order->refresh();
                $currentProductStatus = $order->product_status ?? $order->initializeProductStatus();
                $this->productStatuses['lpo'] = $currentProductStatus['lpo'] ?? [];
            }
            if ($hasCustom && isset($mappedStatuses['custom'])) {
                $order->setProductStatus('custom', $mappedStatuses['custom']);
                $this->productStatuses['custom'] = $mappedStatuses['custom'];
            }

            // Save to database using Order model method
            $order->save();

        } catch (\Exception $e) {
            Log::error('Failed to sync product statuses from order status', [
                'order_id' => $this->editingId,
                'order_status' => $orderStatus,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updatedStatus($value): void
    {
        // Update order status
        if ($this->isEditMode && $this->editingId) {
            try {
                $order = Order::find($this->editingId);
                if ($order) {
                    $normalizedStatus = $this->normalizeStatusForOrder($value, $order);
                    $this->status = $normalizedStatus;
                    
                    // If in edit mode, save immediately to database
                    $order->update([
                        'status' => $normalizedStatus
                    ]);

                    // Sync product statuses based on new order status
                    $this->syncProductStatusesFromOrderStatus($normalizedStatus, $order);
                }
            } catch (\Exception $e) {
                Log::error('Failed to update order status', [
                    'order_id' => $this->editingId,
                    'status' => $value,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // When status changes to in_transit, open modal for driver and vehicle details
        // NOTE: Driver details are ONLY required for Workshop (warehouse/custom) orders,
        // not for pure Hardware/LPO flows.
        if ($value === 'in_transit' && $this->previous_status !== 'in_transit' && !$this->showInTransitModal) {
            // First, ensure we actually have any workshop (warehouse/custom) products in this order
            $hasWorkshopProducts = false;
            foreach ($this->orderProducts as $product) {
                $productType = $product['product_type'] ?? 'hardware';
                $isCustom = !empty($product['is_custom'] ?? 0);
            if ($productType === 'workshop' || $isCustom) {
                    $hasWorkshopProducts = true;
                    break;
                }
            }

            // If there are no workshop products, skip driver details requirement
            if (!$hasWorkshopProducts) {
                $this->previous_status = 'in_transit';
                return;
            }

            // Check if we have driver details for workshop types (warehouse/custom)
            $hasDriverDetails = false;
            foreach (['workshop', 'custom'] as $type) {
                $details = $this->productDriverDetails[$type] ?? [];
                if (!empty($details['driver_name']) && !empty($details['vehicle_number'])) {
                    $hasDriverDetails = true;
                    break;
                }
            }
            
            if (!$hasDriverDetails) {
                // Temporarily revert to previous status until details are saved
                $this->status = $this->previous_status;
                // Pre-fill with existing values if available (use first available product driver details)
                $firstDetails = reset($this->productDriverDetails);
                $this->temp_driver_name = $firstDetails['driver_name'] ?? null;
                $this->temp_vehicle_number = $firstDetails['vehicle_number'] ?? null;
                $this->showInTransitModal = true;
            } else {
                // Details already exist, allow the change and update previous status
                $this->previous_status = 'in_transit';
            }
        }
        
        // When status changes to rejected, open rejection details modal
        if ($value === 'rejected' || $value === OrderStatusEnum::Rejected->value) {
            if ($this->previous_status !== 'rejected' && $this->previous_status !== OrderStatusEnum::Rejected->value) {
                // Status changed to rejected from another status
                // Refresh order to get latest data
                if ($this->isEditMode && $this->editingId) {
                    $order = Order::with(['products', 'customProducts'])->find($this->editingId);
                    if ($order) {
                        $this->prepareRejectionDetails($order);
                        $this->showRejectionDetailsModal = true;
                    }
                }
            }
        } else {
            // Update previous status for other changes
            // Only consider driver details requirement for in_transit on workshop orders
            $hasDriverDetails = false;
            foreach (['workshop', 'custom'] as $type) {
                $details = $this->productDriverDetails[$type] ?? [];
                if (!empty($details['driver_name']) && !empty($details['vehicle_number'])) {
                    $hasDriverDetails = true;
                    break;
                }
            }
            if ($value !== 'in_transit' || !$hasWorkshopProducts || $hasDriverDetails) {
                $this->previous_status = $value;
            }
        }
    }

    public function saveInTransitDetails(): void
    {
        // Validate that both fields are provided
        $this->validate([
            'temp_driver_name' => ['required', 'string', 'max:255'],
            'temp_vehicle_number' => ['required', 'string', 'max:255'],
        ], [
            'temp_driver_name.required' => 'Driver name is required.',
            'temp_vehicle_number.required' => 'Vehicle number is required.',
        ]);
        
        // Save the temporary values to product driver details
        // For order-level in_transit, save ONLY for workshop-related types (workshop/custom)
        // and only where details don't exist yet.
        $driverName = trim($this->temp_driver_name);
        $vehicleNumber = trim($this->temp_vehicle_number);
        
        foreach (['workshop', 'custom'] as $type) {
            if (empty($this->productDriverDetails[$type]['driver_name']) || 
                empty($this->productDriverDetails[$type]['vehicle_number'])) {
                $this->productDriverDetails[$type] = [
                    'driver_name' => $driverName,
                    'vehicle_number' => $vehicleNumber,
                ];
            }
        }
        
        // Sync to public property for validation
        $this->product_driver_details = $this->productDriverDetails;
        
        // Now set status to in_transit and update previous status
        $this->status = 'in_transit';
        $this->previous_status = 'in_transit';
        
        // Trigger the update to sync order status
        $this->updatedStatus('in_transit');
        
        $this->closeInTransitModal();
    }

    public function closeInTransitModal(): void
    {
        // When closing modal, status is already reverted in updatedStatus
        // So we just close the modal and clear temp values
        $this->showInTransitModal = false;
        $this->temp_driver_name = null;
        $this->temp_vehicle_number = null;
    }

    public function getSiteManagersProperty()
    {
        if ($this->site_id) {
            $site = Site::find($this->site_id);
            if ($site && $site->site_manager_id) {
                return Moderator::where('role', RoleEnum::SiteSupervisor->value)
                    ->where('id', $site->site_manager_id)
                    ->orderBy('name')
                    ->get();
            }
        }
        
        return Moderator::where('role', RoleEnum::SiteSupervisor->value)
            ->orderBy('name')
            ->get();
    }

    public function getShouldDisableSiteSupervisorProperty(): bool
    {
        if (!$this->site_id) {
            return false;
        }
        
        $site = Site::find($this->site_id);
        return $site && !empty($site->site_manager_id);
    }

    public function getTransportManagersProperty()
    {
        return Moderator::where('role', RoleEnum::TransportManager->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getSitesProperty()
    {
        return Site::where('status', true)->orderBy('name')->get();
    }

    public function searchProducts(int $index, string $search = '', int $page = 1): void
    {
        $this->normalizeProductsArray();
        
        // Prevent multiple simultaneous searches
        if (isset($this->productLoading[$index]) && $this->productLoading[$index]) {
            return;
        }
        
        // Only search if dropdown is open (unless explicitly called from toggle)
        if (!isset($this->productDropdownOpen[$index]) || !$this->productDropdownOpen[$index]) {
            // Allow search if it's the initial load (page 1, empty search)
            if ($page !== 1 || !empty(trim($search))) {
                return;
            }
        }
        
        $this->productLoading[$index] = true;
        
        if (!isset($this->productSearch[$index])) {
            $this->productSearch[$index] = '';
        }
        if (!isset($this->productPage[$index])) {
            $this->productPage[$index] = 1;
        }
        
        $this->productSearch[$index] = $search;
        $this->productPage[$index] = $page;
        
        try {
            $perPage = 15;
            
            // Get selected product IDs to exclude
            $selectedProductIds = [];
            foreach ($this->orderProducts as $idx => $product) {
                if ($idx !== $index && !($product['is_custom'] ?? 0)) {
                    $productId = $product['product_id'] ?? '';
                    if ($productId) {
                        $selectedProductIds[] = (string)$productId;
                    }
                }
            }
            
            $query = Product::where('status', true)
                ->whereIn('is_product', [1, 2])
                ->with('category');
            
            // In create mode, show ALL products regardless of type
            // In edit mode, filter by product type based on row's product_type
            if (!$this->isEditMode) {
                // Create mode: Show all products (no store filter)
                // This allows users to select any product for any section when creating a new order
            } else {
                // Edit mode: Filter by product type to show only relevant products
                $productType = $this->orderProducts[$index]['product_type'] ?? 'hardware';
                
                // For workshop/custom orders, show ALL warehouse products
                // For other types, filter by store
                if ($productType === 'workshop') {
                    // Show all warehouse store products for workshop/custom orders
                    $query->where('store', StoreEnum::WarehouseStore);
                } else {
                    // Map product_type to StoreEnum for other types
                    $storeFilter = match($productType) {
                        'hardware' => StoreEnum::HardwareStore,
                        'lpo' => StoreEnum::LPO,
                        default => StoreEnum::HardwareStore,
                    };
                    // Apply store filter based on product type
                    $query->where('store', $storeFilter);
                }
            }
            
            // Exclude already selected products
            if (!empty($selectedProductIds)) {
                $query->whereNotIn('id', $selectedProductIds);
            }
            
            if (!empty($search) && trim($search) !== '') {
                $searchTerm = trim($search);
                $query->where('product_name', 'like', '%' . $searchTerm . '%');
            }
            
            $total = $query->count();
            $products = $query->orderByRaw('is_product DESC, product_name ASC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $hasMore = ($page * $perPage) < $total;
            $this->productHasMore[$index] = $hasMore;
            
            $results = $products->map(function ($product) {
                $typeLabel = $product->type ? $product->type->getName() : '';
                $itemType = $product->is_product ? 'Product' : 'Material';
                $displayName = $product->product_name;
                
                if ($product->is_product == 1 && $product->type == ProductTypeEnum::Product) {
                    $displayName = $product->product_name . ' [Product]';
                } elseif ($product->is_product == 1 && $product->type == ProductTypeEnum::Material) {
                    $displayName = $product->product_name . ' [Material as Product]';
                } elseif ($product->is_product == 2) {
                    $displayName = $product->product_name . ' [Material + Product]';
                } else {
                    $displayName = $product->product_name . ' [' . $itemType . ']' . ($typeLabel ? ' (' . $typeLabel . ')' : '');
                }
                
                return [
                    'id' => $product->id,
                    'text' => $displayName,
                    'category_name' => $product->category->name ?? '',
                    'unit_type' => $product->unit_type ?? '',
                    'image_url' => $product->first_image_url ?? null,
                ];
            })->toArray();
            
            if ($page === 1) {
                $this->productSearchResults[$index] = $results;
            } else {
                $this->productSearchResults[$index] = array_merge($this->productSearchResults[$index] ?? [], $results);
            }
        } catch (\Exception $e) {
            // Log error and show user-friendly message
            \Illuminate\Support\Facades\Log::error('Product search error: ' . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Error searching products. Please try again.']);
            $this->productSearchResults[$index] = [];
            $this->productHasMore[$index] = false;
        } finally {
            $this->productLoading[$index] = false;
        }
    }

    public function loadMoreProducts(int $index): void
    {
        $this->normalizeProductsArray();
        
        // Prevent loading if already loading
        if (isset($this->productLoading[$index]) && $this->productLoading[$index]) {
            return;
        }
        
        // Check if dropdown is open
        if (!isset($this->productDropdownOpen[$index]) || !$this->productDropdownOpen[$index]) {
            return;
        }
        
        // Check if there are more results
        if (empty($this->productHasMore[$index] ?? false)) {
            return;
        }
        
        if (!isset($this->productPage[$index])) {
            $this->productPage[$index] = 1;
        }
        
        $nextPage = $this->productPage[$index] + 1;
        $search = $this->productSearch[$index] ?? '';
        $this->searchProducts($index, $search, $nextPage);
    }

    public function selectProduct(int $index, int $productId): void
    {
        $this->normalizeProductsArray();
        
        $product = Product::with('category')->find($productId);
        if (!$product) {
            return;
        }
        
        // Ensure index exists
        if (!isset($this->orderProducts[$index])) {
            $this->orderProducts[$index] = [
                'product_id' => '', 
                'quantity' => '', 
                'is_custom' => 0, 
                'custom_note' => '', 
                'custom_images' => [],
                'product_type' => 'hardware',
                'product_ids' => [],
                'custom_product_id' => null,
                'supplier_id' => null
            ];
        }
        
        $this->orderProducts[$index]['product_id'] = (string)$productId;
        $this->orderProducts[$index]['product_type'] = $this->getProductType($product);
        $this->productDropdownOpen[$index] = false;
        $this->productSearch[$index] = '';
        $this->productSearchResults[$index] = [];
        $this->productPage[$index] = 1;
    }

    public function toggleProductDropdown(int $index): void
    {
        $this->normalizeProductsArray();
        
        // Close all other dropdowns first
        foreach ($this->productDropdownOpen as $key => $value) {
            if ($key !== $index && $value) {
                $this->productDropdownOpen[$key] = false;
            }
        }
        
        if (!isset($this->productDropdownOpen[$index])) {
            $this->productDropdownOpen[$index] = false;
        }
        
        $wasOpen = $this->productDropdownOpen[$index];
        $this->productDropdownOpen[$index] = !$wasOpen;
        
        if ($this->productDropdownOpen[$index] && !$wasOpen) {
            // Load initial products immediately when opening
            $this->productSearch[$index] = '';
            $this->productPage[$index] = 1;
            $this->productSearchResults[$index] = [];
            $this->searchProducts($index, '', 1);
        }
    }

    public function closeProductDropdown(int $index): void
    {
        $this->productDropdownOpen[$index] = false;
    }

    public function updatedProductSearch($value, $key): void
    {
        // This is called by wire:model.live - handle it with debounce
        $this->handleProductSearch($value, $key);
    }

    public function handleProductSearch($value, $key): void
    {
        // Extract index from key (e.g., "productSearch.0" -> 0)
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $index = (int)$parts[1];
            if (is_numeric($index)) {
                // Ensure dropdown is open, if not, open it
                if (!isset($this->productDropdownOpen[$index]) || !$this->productDropdownOpen[$index]) {
                    $this->productDropdownOpen[$index] = true;
                }
                
                // Update the search value
                $this->productSearch[$index] = $value ?? '';
                
                // Reset pagination and clear results
                $this->productPage[$index] = 1;
                $this->productSearchResults[$index] = [];
                
                // Perform search
                $this->searchProducts($index, $value ?? '', 1);
            }
        }
    }

    public function getProductsProperty()
    {
        $query = Product::where('status', true)
            ->whereIn('is_product', [1, 2])
            ->with(['productImages', 'category']);
        
        // Don't filter by store in edit mode - we need all products to properly display selected ones
        // The store filter was causing products not to be found when loading order
        
        return $query->orderByRaw('is_product DESC, product_name ASC')
            ->get();
    }

    // Site search methods
    public function searchSites(string $search = '', int $page = 1): void
    {
        if ($this->siteLoading) {
            return;
        }
        
        if (!$this->siteDropdownOpen) {
            return;
        }
        
        $this->siteLoading = true;
        $this->siteSearch = $search;
        $this->sitePage = $page;
        
        try {
            $perPage = 15;
            
            $query = Site::query()->where('status', true); // Only show active sites
            
            if (!empty($search) && trim($search) !== '') {
                $searchTerm = trim($search);
                $query->where('name', 'like', '%' . $searchTerm . '%');
            }
            
            $total = $query->count();
            $sites = $query->orderBy('name')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $hasMore = ($page * $perPage) < $total;
            $this->siteHasMore = $hasMore;
            
            $results = $sites->map(function ($site) {
                return [
                    'id' => $site->id,
                    'text' => $site->name,
                    'location' => $site->location ?? $site->address ?? '',
                ];
            })->toArray();
            
            if ($page === 1) {
                $this->siteSearchResults = $results;
            } else {
                $this->siteSearchResults = array_merge($this->siteSearchResults ?? [], $results);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Site search error: ' . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Error searching sites. Please try again.']);
            $this->siteSearchResults = [];
            $this->siteHasMore = false;
        } finally {
            $this->siteLoading = false;
        }
    }

    public function loadMoreSites(): void
    {
        if ($this->siteLoading) {
            return;
        }
        
        if (!$this->siteDropdownOpen) {
            return;
        }
        
        if (!$this->siteHasMore) {
            return;
        }
        
        $nextPage = $this->sitePage + 1;
        $this->searchSites($this->siteSearch, $nextPage);
    }

    public function selectSite(?int $siteId): void
    {
        $this->site_id = $siteId ? (string)$siteId : null;
        $this->siteDropdownOpen = false;
        $this->siteSearch = '';
        $this->siteSearchResults = [];
        $this->sitePage = 1;
        
        // Trigger updatedSiteId to auto-fill site manager and location
        $this->updatedSiteId($this->site_id);
    }

    public function toggleSiteDropdown(): void
    {
        $this->siteDropdownOpen = !$this->siteDropdownOpen;
        
        if ($this->siteDropdownOpen) {
            $this->siteSearch = '';
            $this->sitePage = 1;
            $this->siteSearchResults = [];
            $this->searchSites('', 1);
        }
    }

    public function closeSiteDropdown(): void
    {
        $this->siteDropdownOpen = false;
    }

    public function handleSiteSearch($value, $key): void
    {
        if (!$this->siteDropdownOpen) {
            $this->siteDropdownOpen = true;
        }
        
        $this->sitePage = 1;
        $this->siteSearchResults = [];
        $this->searchSites($value ?? '', 1);
    }

    // Site Manager search methods
    public function searchSiteManagers(string $search = '', int $page = 1): void
    {
        if ($this->siteManagerLoading) {
            return;
        }
        
        if (!$this->siteManagerDropdownOpen) {
            return;
        }
        
        if (!$this->site_id) {
            $this->siteManagerSearchResults = [];
            return;
        }
        
        $this->siteManagerLoading = true;
        $this->siteManagerSearch = $search;
        $this->siteManagerPage = $page;
        
        try {
            $perPage = 15;
            
            $query = Moderator::where('role', RoleEnum::SiteSupervisor->value);
            
            // Filter by site if site_id is set
            if ($this->site_id) {
                $query->whereHas('sites', function($q) {
                    $q->where('sites.id', $this->site_id);
                });
            }
            
            if (!empty($search) && trim($search) !== '') {
                $searchTerm = trim($search);
                $query->where('name', 'like', '%' . $searchTerm . '%');
            }
            
            $total = $query->count();
            $managers = $query->orderBy('name')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $hasMore = ($page * $perPage) < $total;
            $this->siteManagerHasMore = $hasMore;
            
            $results = $managers->map(function ($manager) {
                return [
                    'id' => $manager->id,
                    'text' => $manager->name,
                    'email' => $manager->email ?? '',
                ];
            })->toArray();
            
            if ($page === 1) {
                $this->siteManagerSearchResults = $results;
            } else {
                $this->siteManagerSearchResults = array_merge($this->siteManagerSearchResults ?? [], $results);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Site Manager search error: ' . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Error searching site managers. Please try again.']);
            $this->siteManagerSearchResults = [];
            $this->siteManagerHasMore = false;
        } finally {
            $this->siteManagerLoading = false;
        }
    }

    public function loadMoreSiteManagers(): void
    {
        if ($this->siteManagerLoading) {
            return;
        }
        
        if (!$this->siteManagerDropdownOpen) {
            return;
        }
        
        if (!$this->siteManagerHasMore) {
            return;
        }
        
        $nextPage = $this->siteManagerPage + 1;
        $this->searchSiteManagers($this->siteManagerSearch, $nextPage);
    }

    public function selectSiteManager(?int $siteManagerId): void
    {
        $this->site_manager_id = $siteManagerId ? (string)$siteManagerId : null;
        $this->siteManagerDropdownOpen = false;
        $this->siteManagerSearch = '';
        $this->siteManagerSearchResults = [];
        $this->siteManagerPage = 1;
    }

    public function toggleSiteManagerDropdown(): void
    {
        if (!$this->site_id) {
            $this->dispatch('show-toast', ['type' => 'warning', 'message' => 'Please select a site first.']);
            return;
        }
        
        $this->siteManagerDropdownOpen = !$this->siteManagerDropdownOpen;
        
        if ($this->siteManagerDropdownOpen) {
            $this->siteManagerSearch = '';
            $this->siteManagerPage = 1;
            $this->siteManagerSearchResults = [];
            $this->searchSiteManagers('', 1);
        }
    }

    public function closeSiteManagerDropdown(): void
    {
        $this->siteManagerDropdownOpen = false;
    }

    public function handleSiteManagerSearch($value, $key): void
    {
        if (!$this->site_id) {
            return;
        }
        
        if (!$this->siteManagerDropdownOpen) {
            $this->siteManagerDropdownOpen = true;
        }
        
        $this->siteManagerPage = 1;
        $this->siteManagerSearchResults = [];
        $this->searchSiteManagers($value ?? '', 1);
    }

    // Status dropdown methods
    public function toggleStatusDropdown(): void
    {
        $this->statusDropdownOpen = !$this->statusDropdownOpen;
    }

    public function closeStatusDropdown(): void
    {
        $this->statusDropdownOpen = false;
    }

    public function selectStatus(string $status): void
    {
        // Close dropdown first
        $this->statusDropdownOpen = false;
        
        // For in_transit status, always open modal to enter/edit driver details
        if ($status === 'in_transit' && $this->previous_status !== 'in_transit') {
            // Pre-fill with existing values if available (use first available product driver details)
            $firstDetails = reset($this->productDriverDetails);
            $this->temp_driver_name = $firstDetails['driver_name'] ?? null;
            $this->temp_vehicle_number = $firstDetails['vehicle_number'] ?? null;
            // Open the modal - status will remain as previous until details are saved
            $this->showInTransitModal = true;
            return;
        }
        
        // For other cases, set the status and trigger updatedStatus
        $this->status = $status;
        $this->updatedStatus($status);
    }

    // NOTE: orders.delivery_status column was removed; delivery state is represented by `status` only.

    protected function getValidationRules(): array
    {
        $request = new StoreOrderRequest(
            isEditMode: $this->isEditMode,
            editingId: $this->editingId,
            site_id: $this->site_id,
            orderProducts: $this->orderProducts,
            stockService: $this->stockService
        );

        $rules = $request->rules();
        
        // Add conditional validation for product_driver_details when status is in_transit
        if ($this->status === 'in_transit') {
            // Ensure at least one product type has driver details
            $hasDriverDetails = false;
            foreach ($this->productDriverDetails as $details) {
                if (!empty($details['driver_name']) && !empty($details['vehicle_number'])) {
                    $hasDriverDetails = true;
                    break;
                }
            }
            
            if (!$hasDriverDetails) {
                $rules['product_driver_details'] = ['required', 'array'];
                $rules['product_driver_details.hardware.driver_name'] = ['required_with:product_driver_details.hardware', 'string', 'max:255'];
                $rules['product_driver_details.hardware.vehicle_number'] = ['required_with:product_driver_details.hardware', 'string', 'max:255'];
            } else {
                $rules['product_driver_details'] = ['nullable', 'array'];
            }
        }
        
        return $rules;
    }

    protected function getValidationMessages(): array
    {
        $request = new StoreOrderRequest(
            isEditMode: $this->isEditMode,
            editingId: $this->editingId,
            site_id: $this->site_id,
            orderProducts: $this->orderProducts,
            stockService: $this->stockService
        );

        return $request->messages();
    }

    /**
     * Validate products and quantity manually to ensure errors are properly registered
     * Product ID and quantity are required for non-custom products.
     * Custom products must have either custom_note or custom_images (no quantity required).
     * This is a backup validation in case the main validation doesn't catch empty values.
     */
    protected function validateProductsAndQuantity(): void
    {
        $this->normalizeProductsArray();
        
        foreach ($this->orderProducts as $index => $product) {
            $isCustom = $this->normalizeBoolean($product['is_custom'] ?? false);
            
            // For non-custom products, product_id and quantity are required
            if (!$isCustom) {
                $productId = $product['product_id'] ?? '';
                if (empty($productId) || $productId === '' || $productId === '0' || $productId === 0) {
                    $this->addError("orderProducts.{$index}.product_id", 'Please select a product.');
                }
                
                $quantity = $product['quantity'] ?? '';
                if (empty($quantity) || $quantity === '' || $quantity === '0' || $quantity === 0) {
                    $this->addError("orderProducts.{$index}.quantity", 'Quantity is required.');
                }
            } else {
                // For custom products, validate that they have either custom_note or custom_images
                $customNote = trim($product['custom_note'] ?? '');
                $customImages = $product['custom_images'] ?? [];
                if (empty($customNote) && empty($customImages)) {
                    $this->addError("orderProducts.{$index}.custom_note", 'Either custom note or custom images are required for custom products.');
                }
            }
        }
    }
    
    /**
     * Normalize boolean value
     */
    protected function normalizeBoolean($value): bool
    {
        if ($value === 0 || $value === '0' || $value === false || $value === null) {
            return false;
        }
        return (bool)$value;
    }
    
    /**
     * Update product status for a specific product group (edit mode only)
     * Saves immediately to database
     * Opens driver details modal if status is in_transit
     */
    public function updateProductStatus(string $type, string $status): void
    {
        if (!$this->isEditMode || !$this->editingId) {
            return;
        }
        
        // Store original type for view/event dispatch (view uses 'warehouse' but data uses 'workshop')
        $originalType = $type;
        
        // Map 'warehouse' to 'workshop' for internal storage (view uses 'warehouse' but data uses 'workshop')
        if ($type === 'warehouse') {
            $type = 'workshop';
        }
        
        // Validate product type
        if (!in_array($type, ['hardware', 'workshop', 'lpo', 'custom'])) {
            return;
        }
        
        // Ensure productStatuses array has the key initialized
        if (!isset($this->productStatuses[$type])) {
            $this->productStatuses[$type] = 'pending';
        }
        
        // Validate status (no separate 'completed' status anymore)
        $validStatuses = ['pending', 'approved', 'in_transit', 'outfordelivery', 'delivered', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            return;
        }
        
        // For hardware and LPO types, prevent in_transit status (they skip directly to outfordelivery)
        // Hardware & LPO workflow: Pending -> Approved -> Out for delivery -> Delivered
        if (in_array($type, ['hardware', 'lpo']) && in_array($status, ['in_transit'])) {
            $this->setProductStatusError($type, ucfirst($type) . " products cannot be set to '{$status}' status.");
            // Revert the status change
            try {
                $order = Order::find($this->editingId);
                if ($order && $order->product_status && isset($order->product_status[$type])) {
                    $this->productStatuses[$type] = $order->product_status[$type];
                } else {
                    $this->productStatuses[$type] = 'pending';
                }
            } catch (\Exception $e) {
                $this->productStatuses[$type] = 'pending';
            }
            // Dispatch browser event to revert select value (use originalType for view compatibility)
            $this->dispatch('revert-product-status-select', type: $originalType, status: $this->productStatuses[$type]);
            return;
        }
        
        // RESTRICTION: If already approved, cannot change to pending or rejected
        // Can only change to: outfordelivery, in_transit, or delivered
        // Get current status from database (not from property, as wire:model may have already updated it)
        // Normalize to string - LPO can be array (supplier-wise), so calculate combined status
        $currentStatus = 'pending';
        try {
            $order = Order::find($this->editingId);
            if ($order && $order->product_status && isset($order->product_status[$type])) {
                $rawStatus = $order->product_status[$type];
                
                // For LPO, if it's an array, calculate combined status
                if ($type === 'lpo' && is_array($rawStatus)) {
                    $uniqueStatuses = array_unique(array_values($rawStatus));
                    if (in_array('rejected', $uniqueStatuses, true)) {
                        $currentStatus = 'rejected';
                    } elseif (in_array('pending', $uniqueStatuses, true)) {
                        $currentStatus = 'pending';
                    } elseif (in_array('approved', $uniqueStatuses, true)) {
                        $currentStatus = 'approved';
                    } elseif (in_array('outfordelivery', $uniqueStatuses, true) || in_array('out_of_delivery', $uniqueStatuses, true) || in_array('outofdelivery', $uniqueStatuses, true)) {
                        $currentStatus = 'outfordelivery';
                    } elseif (in_array('delivered', $uniqueStatuses, true)) {
                        $currentStatus = 'delivered';
                    } else {
                        $currentStatus = 'pending';
                    }
                } else {
                    // For non-LPO or already string status, use as-is but ensure it's a string
                    $currentStatus = is_string($rawStatus) ? $rawStatus : 'pending';
                }
            } elseif (isset($this->productStatuses[$type])) {
                // Fallback to property if database doesn't have it yet
                $rawStatus = $this->productStatuses[$type];
                // Normalize if it's an array (for LPO)
                if (is_array($rawStatus)) {
                    $uniqueStatuses = array_unique(array_values($rawStatus));
                    if (in_array('rejected', $uniqueStatuses, true)) {
                        $currentStatus = 'rejected';
                    } elseif (in_array('pending', $uniqueStatuses, true)) {
                        $currentStatus = 'pending';
                    } elseif (in_array('approved', $uniqueStatuses, true)) {
                        $currentStatus = 'approved';
                    } elseif (in_array('outfordelivery', $uniqueStatuses, true) || in_array('out_of_delivery', $uniqueStatuses, true) || in_array('outofdelivery', $uniqueStatuses, true)) {
                        $currentStatus = 'outfordelivery';
                    } elseif (in_array('delivered', $uniqueStatuses, true)) {
                        $currentStatus = 'delivered';
                    } else {
                        $currentStatus = 'pending';
                    }
                } else {
                    $currentStatus = is_string($rawStatus) ? $rawStatus : 'pending';
                }
            }
        } catch (\Exception $e) {
            // Use property as fallback
            $rawStatus = $this->productStatuses[$type] ?? 'pending';
            if (is_array($rawStatus)) {
                $uniqueStatuses = array_unique(array_values($rawStatus));
                if (in_array('rejected', $uniqueStatuses, true)) {
                    $currentStatus = 'rejected';
                } elseif (in_array('pending', $uniqueStatuses, true)) {
                    $currentStatus = 'pending';
                } elseif (in_array('approved', $uniqueStatuses, true)) {
                    $currentStatus = 'approved';
                } elseif (in_array('outfordelivery', $uniqueStatuses, true) || in_array('out_of_delivery', $uniqueStatuses, true) || in_array('outofdelivery', $uniqueStatuses, true)) {
                    $currentStatus = 'outfordelivery';
                } elseif (in_array('delivered', $uniqueStatuses, true)) {
                    $currentStatus = 'delivered';
                } else {
                    $currentStatus = 'pending';
                }
            } else {
                $currentStatus = is_string($rawStatus) ? $rawStatus : 'pending';
            }
        }
        
        // Allow REJECT after approval, but still prevent going back to PENDING once progressed.
        $restrictedStatuses = ['approved', 'outfordelivery', 'in_transit', 'delivered'];
        if (in_array($currentStatus, $restrictedStatuses, true) && $status === 'pending') {
            $this->setProductStatusError($type, 'Cannot change status from ' . $currentStatus . ' to pending.');
            $this->productStatuses[$type] = $currentStatus;
            $this->dispatch('revert-product-status-select', type: $type, status: $currentStatus);
            return;
        }

        // If user selects "rejected", require rejection reason BEFORE persisting the status.
        // This keeps the UI consistent: Close = no status change, Save = status becomes rejected.
        if ($status === 'rejected' && $currentStatus !== 'rejected') {
            $labels = [
                'hardware' => 'Hardware',
                'workshop' => 'Workshop',
                'lpo' => 'LPO',
                'custom' => 'Custom',
            ];

            $this->pendingRejectionType = $type;
            $this->pendingRejectionPreviousStatus = $currentStatus;
            $this->currentRejectionType = $type;
            $this->rejectionDetailsProductStatuses = [
                $type => $labels[$type] ?? ucfirst($type),
            ];

            // Preload existing note (if any)
            try {
                $orderForNotes = Order::find($this->editingId);
                if ($orderForNotes) {
                    $this->productRejectionNotes[$type] = $orderForNotes->getProductRejectionNote($type) ?? '';
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $this->showRejectionDetailsModal = true;

            // Revert the dropdown back until user clicks Save in the modal
            $this->productStatuses[$type] = $currentStatus;
            $this->dispatch('revert-product-status-select', type: $originalType, status: $currentStatus);
            return;
        }
        
        // In transit status - no modal required, allow direct status change
        // Driver details will be required when moving to "out for delivery" instead
        
        // If status is outfordelivery, check if driver details are needed
        // Out for delivery requires driver details for ALL product types (hardware, warehouse, lpo, custom)
        // Hardware & LPO: Approved → Out for delivery (with driver details) → Delivered
        // Warehouse: In Transit → Out for delivery (with driver details) → Delivered
        if ($status === 'outfordelivery') {
            // If not already outfordelivery, open modal for driver details
            if ($currentStatus !== 'outfordelivery') {
                // Check if driver details already exist for out for delivery
                // Load from database if not in local state
                $order = Order::find($this->editingId);
                $existingOutForDeliveryDetails = null;
                if ($order && $order->product_driver_details) {
                    $existingOutForDeliveryDetails = $order->product_driver_details[$type]['out_for_delivery'] ?? null;
                }
                
                // Also check local state
                if (!$existingOutForDeliveryDetails && isset($this->productDriverDetails[$type]['out_for_delivery'])) {
                    $existingOutForDeliveryDetails = $this->productDriverDetails[$type]['out_for_delivery'];
                }
                
                $existingDriverName = $existingOutForDeliveryDetails['driver_name'] ?? null;
                $existingVehicleNumber = $existingOutForDeliveryDetails['vehicle_number'] ?? null;
                
                // If driver details don't exist, open modal
                if (empty($existingDriverName) || empty($existingVehicleNumber)) {
                    // Store previous status for potential revert on cancel
                    $this->productOutForDeliveryPreviousStatus = $currentStatus;
                    $this->productOutForDeliveryType = $type;
                    $this->productOutForDeliveryDriverName = $existingDriverName;
                    $this->productOutForDeliveryVehicleNumber = $existingVehicleNumber;
                    $this->showProductOutForDeliveryModal = true;
                    // Revert the status change until driver details are saved
                    $this->productStatuses[$type] = $currentStatus;
                    // Dispatch browser event to revert select value
                    $this->dispatch('revert-product-status-select', type: $type, status: $currentStatus);
                    return;
                }
            }
        }
        
        // Update the property and save the status update
        $this->productStatuses[$type] = $status;
        $this->saveProductStatusUpdate($type, $status);
    }

    /**
     * Update LPO product status for a specific supplier (edit mode only).
     * This allows supplier-wise status updates from the admin panel.
     */
    public function updateLpoSupplierStatus($rowIndex, $status): void
    {
        // Normalize incoming values from Livewire (they arrive as strings)
        $rowIndex = (int) $rowIndex;
        $status = is_string($status) ? $status : (string) $status;

        if (!$this->isEditMode || !$this->editingId || $rowIndex < 0) {
            return;
        }

        // Determine supplier for this row from orderProducts
        $supplierId = null;
        if (isset($this->orderProducts[$rowIndex]['supplier_id']) && $this->orderProducts[$rowIndex]['supplier_id']) {
            $supplierId = (int) $this->orderProducts[$rowIndex]['supplier_id'];
        }

        if ($supplierId <= 0) {
            return;
        }

        // Only allow valid product statuses (no separate 'completed' state)
        $validStatuses = ['pending', 'approved', 'in_transit', 'outfordelivery', 'delivered', 'rejected'];
        if (!in_array($status, $validStatuses, true)) {
            return;
        }

        // LPO suppliers should not move to in_transit directly
        if ($status === 'in_transit') {
            session()->flash('product_status_error', "LPO supplier products cannot be set to '{$status}' status.");
            return;
        }

        // Get current status for this supplier
        $currentSupplierStatus = 'pending';
        try {
            $order = Order::find($this->editingId);
            if ($order && $order->product_status && isset($order->product_status['lpo']) && is_array($order->product_status['lpo'])) {
                $currentSupplierStatus = $order->product_status['lpo'][(string)$supplierId] ?? 'pending';
            }
        } catch (\Exception $e) {
            $currentSupplierStatus = 'pending';
        }

        // RESTRICTION: If already approved/outfordelivery/in_transit/delivered, cannot change to pending or rejected
        // Can only change to: outfordelivery, in_transit, or delivered
        $restrictedStatuses = ['approved', 'outfordelivery', 'in_transit', 'delivered'];
        if (in_array($currentSupplierStatus, $restrictedStatuses, true) && in_array($status, ['pending', 'rejected'], true)) {
            session()->flash('product_status_error', 'Cannot change LPO supplier status from ' . $currentSupplierStatus . ' to ' . $status . '. You can only change to outfordelivery, in_transit, or delivered.');
            return;
        }

        try {
            /** @var \App\Models\Order|null $order */
            $order = Order::find($this->editingId);
            if (!$order) {
                return;
            }

            // Use Order model's setProductStatus method (same as API) for consistency
            // This ensures the same logic is used everywhere for LPO supplier status updates
            $order->setProductStatus('lpo', $status, $supplierId);
            $order->save();

            // Sync order status based on updated product statuses
            // Use Order model's method (same as API) for consistency
            $order->refresh();
            $order->syncOrderStatusFromProductStatuses();

            // Update local Livewire state so UI reflects latest supplier-wise LPO statuses
            $order->refresh();
            $currentProductStatus = $order->product_status ?? $order->initializeProductStatus();
            $this->productStatuses['lpo'] = $currentProductStatus['lpo'] ?? [];

            session()->flash('product_status_updated', 'LPO supplier status updated successfully.');
        } catch (\Throwable $e) {
            // Log but do not break the UI
            \Illuminate\Support\Facades\Log::error('Failed to update LPO supplier status', [
                'order_id' => $this->editingId,
                'supplier_id' => $supplierId,
                'status' => $status,
                'message' => $e->getMessage(),
            ]);
            session()->flash('product_status_error', 'Unable to update LPO supplier status. Please try again.');
        }
    }
    
    /**
     * Save product status update to database
     */
    protected function saveProductStatusUpdate(string $type, string $status): void
    {
        try {
            // Find the order
            $order = Order::findOrFail($this->editingId);
            
            // Get current product status for comparison (before update)
            $currentProductStatus = $order->product_status ?? $order->initializeProductStatus();
            
            // Store old status for potential rollback and stock deduction check
            $oldStatus = null;
            if ($type === 'lpo') {
                // For LPO, get combined status (since it's supplier-wise)
                $lpoStatuses = $currentProductStatus['lpo'] ?? [];
                if (is_array($lpoStatuses) && !empty($lpoStatuses)) {
                    $uniqueStatuses = array_unique(array_values($lpoStatuses));
                    if (count($uniqueStatuses) === 1) {
                        $oldStatus = reset($uniqueStatuses);
                    } else {
                        // Multiple suppliers - calculate combined status
                        if (in_array('rejected', $uniqueStatuses, true)) {
                            $oldStatus = 'rejected';
                        } elseif (in_array('pending', $uniqueStatuses, true)) {
                            $oldStatus = 'pending';
                        } elseif (in_array('approved', $uniqueStatuses, true)) {
                            $oldStatus = 'approved';
                        } else {
                            $oldStatus = 'pending';
                        }
                    }
                } else {
                    $oldStatus = 'pending';
                }
            } else {
                $oldStatus = $currentProductStatus[$type] ?? 'pending';
            }
            
            /**
             * STOCK CHECK & DEDUCTION RULES (per product type)
             *
             * - Hardware:
             *   - Check + deduct stock when status changes to "approved".
             * - Warehouse / Custom:
             *   - Check + deduct stock when status changes to "outfordelivery".
             *   - Approval alone should NOT deduct stock.
             * - LPO:
             *   - Handled separately; do not change here.
             */

            // HARDWARE: pre-check & deduct on "approved"
            if ($type === 'hardware') {
                // If approving (and wasn't already approved), pre-check stock BEFORE persisting status.
                if ($status === 'approved' && $oldStatus !== 'approved') {
                    $check = $this->canApproveProductType($order, $type);
                    if (!($check['ok'] ?? false)) {
                        $msg = (string) ($check['message'] ?? "{$type}: Insufficient stock.");
                        $this->setProductStatusError($type, $msg);
                        $this->productStatuses[$type] = $oldStatus;
                        $this->dispatch('revert-product-status-select', type: $type, status: $oldStatus);
                        return;
                    }

                    // IMPORTANT: Deduct stock BEFORE persisting the status to "approved".
                    // If deduction fails, we want the status to remain unchanged.
                    $this->deductStockForProductType($order, $type);
                }
            }

            // Use Order model's updateProductStatus method (same as API) for consistency
            // This ensures the same logic is used everywhere
            $order->updateProductStatus($type, $status);
            
            // Get current product driver details or initialize
            $currentDriverDetails = $order->product_driver_details ?? [];
            
            // If status is in_transit and we have driver details in local state, save them
            if ($status === 'in_transit' && isset($this->productDriverDetails[$type])) {
                $currentDriverDetails[$type] = $this->productDriverDetails[$type];
                // Sync to public property
                $this->product_driver_details = $this->productDriverDetails;
            }
            
            // If status is outfordelivery, save driver details for out for delivery
            // and, for warehouse/custom, perform stock check & deduction here.
            if ($status === 'outfordelivery') {
                // WORKSHOP/CUSTOM: pre-check & deduct on "outfordelivery"
                if (in_array($type, ['workshop', 'custom'], true) && $oldStatus !== 'outfordelivery') {
                    $check = $this->canApproveProductType($order, $type);
                    if (!($check['ok'] ?? false)) {
                        $msg = (string) ($check['message'] ?? "{$type}: Insufficient stock.");
                        $this->setProductStatusError($type, $msg);
                        $this->productStatuses[$type] = $oldStatus;
                        $this->dispatch('revert-product-status-select', type: $type, status: $oldStatus);
                        return;
                    }

                    // Deduct stock BEFORE persisting the status to "outfordelivery".
                    // If deduction fails, we want the status to remain unchanged.
                    $this->deductStockForProductType($order, $type);
                }

                // Initialize product type entry if it doesn't exist
                if (!isset($currentDriverDetails[$type])) {
                    $currentDriverDetails[$type] = [];
                }
                // Save out for delivery driver details
                if (isset($this->productDriverDetails[$type]['out_for_delivery'])) {
                    $currentDriverDetails[$type]['out_for_delivery'] = $this->productDriverDetails[$type]['out_for_delivery'];
                } elseif ($this->productOutForDeliveryDriverName && $this->productOutForDeliveryVehicleNumber) {
                    // Save from modal values if not in productDriverDetails yet
                    $currentDriverDetails[$type]['out_for_delivery'] = [
                        'driver_name' => trim($this->productOutForDeliveryDriverName),
                        'vehicle_number' => trim($this->productOutForDeliveryVehicleNumber),
                    ];
                    // Also update local state
                    $this->productDriverDetails[$type]['out_for_delivery'] = $currentDriverDetails[$type]['out_for_delivery'];
                }
                // Sync to public property
                $this->product_driver_details = $this->productDriverDetails;
            }
            
            // Save driver details if they were updated
            if (!empty($currentDriverDetails)) {
                $order->update([
                    'product_driver_details' => $currentDriverDetails
                ]);
            }
            
            // Refresh the order to get latest data
            $order->refresh();
            
            // Restore stock when product status changes to 'rejected' from a state
            // where stock had previously been deducted for that product type.
            if ($status === 'rejected' && $oldStatus !== 'rejected') {
                $hadStockDeducted = false;

                // Hardware: stock is deducted when status becomes "approved"
                if ($type === 'hardware' && in_array($oldStatus, ['approved', 'outfordelivery', 'in_transit', 'delivered'], true)) {
                    $hadStockDeducted = true;
                }

                // Workshop/Custom: stock is deducted when status becomes "outfordelivery"
                if (in_array($type, ['workshop', 'custom'], true) && in_array($oldStatus, ['outfordelivery', 'in_transit', 'delivered'], true)) {
                    $hadStockDeducted = true;
                }

                if ($hadStockDeducted) {
                    $this->restoreStockForProductType($order, $type);
                }
            }

            // Stock deduction for approval happens BEFORE updating status (see above)
            
            // Sync order status from product statuses after product status update
            // Use Order model's method (same as API) for consistency
            $oldOrderStatus = $order->status;
            $order->syncOrderStatusFromProductStatuses();
            $order->refresh();
            
            // Check if order status became rejected after product status update
            $newOrderStatus = $order->status;
            $isOrderNowRejected = false;
            if ($newOrderStatus instanceof OrderStatusEnum) {
                $isOrderNowRejected = ($newOrderStatus === OrderStatusEnum::Rejected);
            } else {
                $statusValue = is_string($newOrderStatus) ? $newOrderStatus : ($newOrderStatus?->value ?? '');
                $isOrderNowRejected = ($statusValue === OrderStatusEnum::Rejected->value || $statusValue === 'rejected');
            }
            
            $wasOrderRejected = false;
            if ($oldOrderStatus instanceof OrderStatusEnum) {
                $wasOrderRejected = ($oldOrderStatus === OrderStatusEnum::Rejected);
            } else {
                $oldStatusValue = is_string($oldOrderStatus) ? $oldOrderStatus : ($oldOrderStatus?->value ?? '');
                $wasOrderRejected = ($oldStatusValue === OrderStatusEnum::Rejected->value || $oldStatusValue === 'rejected');
            }
            
            // If order status became rejected (wasn't before but is now), open modal
            if ($isOrderNowRejected && !$wasOrderRejected && !$this->suppressAutoOpenRejectionModal) {
                $this->prepareRejectionDetails($order);
                $this->showRejectionDetailsModal = true;
            }
            
            // Refresh again to get updated order status
            $order->refresh();
            
            // Update local state to ensure consistency
            $this->productStatuses[$type] = $status;
            
            // Update local order status to reflect calculated status
            if ($order->status) {
                $this->status = $order->status->value ?? $order->status;
            }
            
            // Ensure product status is synced from database after refresh
            if ($order->product_status && isset($order->product_status[$type])) {
                $this->productStatuses[$type] = $order->product_status[$type];
            }
        } catch (\Exception $e) {
            Log::error('Failed to update product status', [
                'order_id' => $this->editingId,
                'type' => $type,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            // Revert local UI state back to old status (best effort)
            $fallbackOld = $oldStatus ?? 'pending';
            $this->productStatuses[$type] = $fallbackOld;
            $this->dispatch('revert-product-status-select', type: $type, status: $fallbackOld);

            // Show the real error (usually stock/validation)
            $this->setProductStatusError($type, $e->getMessage());
        }
    }
    
    /**
     * Save product in_transit driver details
     * Validates all cases and saves driver details
     */
    public function saveProductInTransitDetails(): void
    {
        // Validate that product type is set
        if (!$this->productInTransitType) {
            session()->flash('product_status_error', 'Invalid product type. Please try again.');
            return;
        }
        
        // Validate that we're in edit mode
        if (!$this->isEditMode || !$this->editingId) {
            session()->flash('product_status_error', 'Cannot update product status. Order not found.');
            $this->closeProductInTransitModal();
            return;
        }
        
        // Trim and validate driver name
        $driverName = trim($this->productTempDriverName ?? '');
        $vehicleNumber = trim($this->productTempVehicleNumber ?? '');
        
        // Comprehensive validation
        $errors = [];
        
        if (empty($driverName)) {
            $errors['productTempDriverName'] = 'Driver name is required for in-transit status.';
        } elseif (strlen($driverName) > 255) {
            $errors['productTempDriverName'] = 'Driver name must not exceed 255 characters.';
        }
        
        if (empty($vehicleNumber)) {
            $errors['productTempVehicleNumber'] = 'Vehicle number is required for in-transit status.';
        } elseif (strlen($vehicleNumber) > 255) {
            $errors['productTempVehicleNumber'] = 'Vehicle number must not exceed 255 characters.';
        }
        
        // If validation errors exist, show them and return (don't close modal)
        if (!empty($errors)) {
            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }
            session()->flash('product_status_error', 'Please fix the validation errors and try again.');
            // Don't close modal - let user fix errors
            return;
        }
        
        try {
            // Save the temporary values to product driver details
            $this->productDriverDetails[$this->productInTransitType] = [
                'driver_name' => $driverName,
                'vehicle_number' => $vehicleNumber,
            ];
            
            // Sync to public property for validation
            $this->product_driver_details = $this->productDriverDetails;
            
            // Store product type before closing modal
            $productType = $this->productInTransitType;
            
            // Now set product status to in_transit in local state
            $this->productStatuses[$productType] = 'in_transit';
            
            // Save to database (this will update both product_status and product_driver_details)
            $this->saveProductStatusUpdate($productType, 'in_transit');
            
            // Clear previous status since we successfully saved
            $this->productPreviousStatus = null;
            
            // Close the modal (this will clear modal state but won't revert since previousStatus is null)
            $this->closeProductInTransitModal();
            
            // Dispatch event to update select dropdown to show in_transit status after modal closes
            $this->dispatch('update-product-status-select', type: $productType, status: 'in_transit');
            
            // Show success message
            session()->flash('product_status_updated', "Product status for {$productType} updated to in_transit successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to save product in_transit details', [
                'order_id' => $this->editingId,
                'product_type' => $this->productInTransitType,
                'error' => $e->getMessage()
            ]);
            
            // Revert status on error
            if ($this->productPreviousStatus && $this->productInTransitType) {
                $this->productStatuses[$this->productInTransitType] = $this->productPreviousStatus;
                // Dispatch browser event to revert select value
                $this->dispatch('revert-product-status-select', type: $this->productInTransitType, status: $this->productPreviousStatus);
            }
            
            // Don't close modal on error - let user retry
            session()->flash('product_status_error', 'Failed to save driver details. Please check the errors and try again.');
        }
    }
    
    /**
     * Close product in_transit modal
     * Reverts to previous status if modal was closed without saving
     */
    public function closeProductInTransitModal(): void
    {
        $productType = $this->productInTransitType;
        $previousStatus = $this->productPreviousStatus;
        
        // If modal is closed without saving (cancel clicked), revert to previous status
        if ($productType && $previousStatus !== null) {
            try {
                // Get current status from database to ensure we have the correct value
                $dbStatus = null;
                if ($this->editingId) {
                    try {
                        $order = Order::find($this->editingId);
                        if ($order && $order->product_status && isset($order->product_status[$productType])) {
                            $dbStatus = $order->product_status[$productType];
                        }
                    } catch (\Exception $e) {
                        // Ignore database errors
                    }
                }
                
                // Use database status if available and not in_transit, otherwise use previous status
                $statusToRevert = ($dbStatus && $dbStatus !== 'in_transit') ? $dbStatus : $previousStatus;
                
                // Revert status to previous value in local state
                $this->productStatuses[$productType] = $statusToRevert;
                
                // Dispatch browser event to revert select value - use $dispatchBrowserEvent for immediate execution
                $this->dispatch('revert-product-status-select', type: $productType, status: $statusToRevert);
                
            } catch (\Exception $e) {
                Log::error('Failed to revert product status on modal close', [
                    'order_id' => $this->editingId,
                    'product_type' => $productType,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Clear all modal-related state AFTER reverting
        $this->showProductInTransitModal = false;
        $this->productInTransitType = null;
        $this->productTempDriverName = null;
        $this->productTempVehicleNumber = null;
        $this->productPreviousStatus = null;
    }
    
    /**
     * Save product out for delivery driver details
     * Validates and saves driver details for out for delivery status
     */
    public function saveProductOutForDeliveryDetails(): void
    {
        // Validate that product type is set
        if (!$this->productOutForDeliveryType) {
            session()->flash('product_status_error', 'Invalid product type. Please try again.');
            return;
        }
        
        // Validate that we're in edit mode
        if (!$this->isEditMode || !$this->editingId) {
            session()->flash('product_status_error', 'Cannot update product status. Order not found.');
            $this->closeProductOutForDeliveryModal();
            return;
        }
        
        // Trim and validate driver name
        $driverName = trim($this->productOutForDeliveryDriverName ?? '');
        $vehicleNumber = trim($this->productOutForDeliveryVehicleNumber ?? '');
        
        // Comprehensive validation
        $errors = [];
        
        if (empty($driverName)) {
            $errors['productOutForDeliveryDriverName'] = 'Driver name is required for out for delivery status.';
        } elseif (strlen($driverName) > 255) {
            $errors['productOutForDeliveryDriverName'] = 'Driver name must not exceed 255 characters.';
        }
        
        if (empty($vehicleNumber)) {
            $errors['productOutForDeliveryVehicleNumber'] = 'Vehicle number is required for out for delivery status.';
        } elseif (strlen($vehicleNumber) > 255) {
            $errors['productOutForDeliveryVehicleNumber'] = 'Vehicle number must not exceed 255 characters.';
        }
        
        // If validation errors exist, show them and return (don't close modal)
        if (!empty($errors)) {
            foreach ($errors as $field => $message) {
                $this->addError($field, $message);
            }
            session()->flash('product_status_error', 'Please fix the validation errors and try again.');
            // Don't close modal - let user fix errors
            return;
        }
        
        try {
            // Save the temporary values to product driver details
            // Store under 'out_for_delivery' key for this product type
            $this->productDriverDetails[$this->productOutForDeliveryType]['out_for_delivery'] = [
                'driver_name' => $driverName,
                'vehicle_number' => $vehicleNumber,
            ];
            
            // Sync to public property for validation
            $this->product_driver_details = $this->productDriverDetails;
            
            // Store product type before closing modal
            $productType = $this->productOutForDeliveryType;
            
            // Now set product status to outfordelivery in local state
            $this->productStatuses[$productType] = 'outfordelivery';
            
            // Save to database (this will update both product_status and product_driver_details)
            $this->saveProductStatusUpdate($productType, 'outfordelivery');
            
            // Clear previous status since we successfully saved
            $this->productOutForDeliveryPreviousStatus = null;
            
            // Close the modal (this will clear modal state but won't revert since previousStatus is null)
            $this->closeProductOutForDeliveryModal();
            
            // Dispatch event to update select dropdown to show outfordelivery status after modal closes
            $this->dispatch('update-product-status-select', type: $productType, status: 'outfordelivery');
            
            // Show success message
            session()->flash('product_status_updated', "Product status for {$productType} updated to out for delivery successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to save product out for delivery details', [
                'order_id' => $this->editingId,
                'product_type' => $this->productOutForDeliveryType,
                'error' => $e->getMessage()
            ]);
            
            // Revert status on error
            if ($this->productOutForDeliveryPreviousStatus && $this->productOutForDeliveryType) {
                $this->productStatuses[$this->productOutForDeliveryType] = $this->productOutForDeliveryPreviousStatus;
                // Dispatch browser event to revert select value
                $this->dispatch('revert-product-status-select', type: $this->productOutForDeliveryType, status: $this->productOutForDeliveryPreviousStatus);
            }
            
            // Don't close modal on error - let user retry
            session()->flash('product_status_error', 'Failed to save driver details. Please check the errors and try again.');
        }
    }
    
    /**
     * Close product out for delivery modal
     * Reverts to previous status if modal was closed without saving
     */
    public function closeProductOutForDeliveryModal(): void
    {
        $productType = $this->productOutForDeliveryType;
        $previousStatus = $this->productOutForDeliveryPreviousStatus;
        
        // If modal was closed without saving (previousStatus exists), revert to previous status
        if ($previousStatus && $productType) {
            $this->productStatuses[$productType] = $previousStatus;
            // Dispatch browser event to revert select value
            $this->dispatch('revert-product-status-select', type: $productType, status: $previousStatus);
        }
        
        // Clear all modal-related state
        $this->showProductOutForDeliveryModal = false;
        $this->productOutForDeliveryType = null;
        $this->productOutForDeliveryDriverName = null;
        $this->productOutForDeliveryVehicleNumber = null;
        $this->productOutForDeliveryPreviousStatus = null;
    }
    

    protected function getFormData(): array
    {
        $data = [
            'status' => !empty($this->status) ? $this->status : OrderStatusEnum::Pending->value,
            'site_manager_id' => $this->site_manager_id ? (int)$this->site_manager_id : null,
            'transport_manager_id' => $this->transport_manager_id ? (int)$this->transport_manager_id : null,
            'site_id' => $this->site_id ? (int)$this->site_id : null,
            'expected_delivery_date' => $this->expected_delivery_date ?: null,
            'drop_location' => $this->drop_location,
            'priority' => $this->priority ?: null,
            'note' => $this->note ?: null,
            'product_driver_details' => $this->product_driver_details ?: $this->productDriverDetails,
        ];
        $storeManagerIdFromProducts = $this->resolveStoreManagerIdFromProducts();
        if ($storeManagerIdFromProducts) {
            $data['store_manager_id'] = $storeManagerIdFromProducts;
        }
        
        if ($this->status) {
            // Get order for status normalization (needed for LPO check)
            $order = $this->isEditMode && $this->editingId ? Order::find($this->editingId) : null;
            
            // Normalize status for LPO orders
            $data['status'] = $this->normalizeStatusForOrder($this->status, $order);
            
            if ($this->status === 'approved') {
                // If transport manager is assigned, override to in_transit
                if (!empty($this->transport_manager_id) || !empty($data['transport_manager_id'])) {
                    $data['status'] = OrderStatusEnum::InTransit->value;
                }
            }
            
            // deliveries table removed; order delivery is represented by status only.
        }
        
        return $data;
    }
    protected function resolveStoreManagerIdFromProducts(): ?int
    {
        if (!is_array($this->orderProducts) || empty($this->orderProducts)) {
            return null;
        }

        foreach ($this->orderProducts as $productRow) {
            $isCustom = (bool)($productRow['is_custom'] ?? 0);

            // Skip custom products – they don't belong to a specific store manager
            if ($isCustom) {
                continue;
            }

            $productId = $productRow['product_id'] ?? null;
            if (empty($productId)) {
                continue;
            }

            $product = Product::find((int)$productId);
            if ($product && $product->store_manager_id) {
                return (int)$product->store_manager_id;
            }
        }

        return null;
    }

    protected function setFormData($model): void
    {
        if (!$model->relationLoaded('siteManager')) {
            $model->load('siteManager', 'transportManager', 'site', 'products.productImages');
        }
        
        if (!$model->relationLoaded('products')) {
            $model->load('products.productImages');
        }
        
        $displayStatus = $this->calculateOrderStatusFromProductStatus($model);
        $this->status = $displayStatus;
        $this->site_manager_id = $model->site_manager_id ? (string)$model->site_manager_id : null;
        $this->transport_manager_id = $model->transport_manager_id ? (string)$model->transport_manager_id : null;
        $this->site_id = $model->site_id ? (string)$model->site_id : null;
        // Determine order_store from attached products (orders table no longer has a store column)
        // Prefer first product's store enum when available
        $firstProduct = $model->products->first();
        $this->order_store = $firstProduct && $firstProduct->store
            ? (string) ($firstProduct->store instanceof \App\Utility\Enums\StoreEnum ? $firstProduct->store->value : $firstProduct->store)
            : null;
        $this->previous_status = $displayStatus;
        $this->original_status = $displayStatus;
        // Load drop_location from order, or fall back to site's location
        if (!empty($model->drop_location)) {
            $this->drop_location = $model->drop_location;
        } elseif ($model->site) {
            $this->drop_location = !empty($model->site->location) ? $model->site->location : (!empty($model->site->address) ? $model->site->address : null);
        } else {
            $this->drop_location = null;
        }
        $this->priority = $model->priority ?? null;
        $this->note = $model->note ?? null;
        $this->rejected_note = $model->rejected_note ?? null;
        
        // Load product statuses for each group (edit mode)
        if ($model->product_status && is_array($model->product_status)) {
            $this->productStatuses = array_merge($this->productStatuses, $model->product_status);
        }
        
        // Initialize product rejection notes
        $this->productRejectionNotes = [];
        if ($model->product_rejection_notes && is_array($model->product_rejection_notes)) {
            $this->productRejectionNotes = $model->product_rejection_notes;
        }
        
        // Prepare rejection details if order is rejected (but don't auto-open modal)
        if ($this->status === OrderStatusEnum::Rejected->value && $this->rejected_note) {
            $this->prepareRejectionDetails($model);
        }
        
        // Initialize product driver details from order's product_driver_details JSON column
        $orderDriverDetails = $model->product_driver_details ?? [];
        $this->productDriverDetails = [
            'hardware' => $orderDriverDetails['hardware'] ?? ['driver_name' => null, 'vehicle_number' => null],
            'workshop' => $orderDriverDetails['workshop'] ?? ['driver_name' => null, 'vehicle_number' => null],
            'lpo' => $orderDriverDetails['lpo'] ?? ['driver_name' => null, 'vehicle_number' => null],
            'custom' => $orderDriverDetails['custom'] ?? ['driver_name' => null, 'vehicle_number' => null],
        ];
        // Sync to public property for validation
        $this->product_driver_details = $this->productDriverDetails;
        $this->expected_delivery_date = $model->expected_delivery_date
            ? $model->expected_delivery_date->format('Y-m-d')
            : null;
        
        $this->orderProducts = [];

        // Refresh base model and ensure relations are loaded
        $model->refresh();
        $model->load(['products.productImages', 'customProducts']);

        // Load regular products from order_products table for current order only
        $orderProductsData = DB::table('order_products')
            ->where('order_id', $model->id)
            ->get();

        // Load supplier_id mapping from order
        $supplierMapping = $model->supplier_id ?? [];
        if (!is_array($supplierMapping)) {
            $supplierMapping = [];
        }
        
        $productIndex = 0;
        foreach ($orderProductsData as $orderProduct) {
            $product = Product::with('productImages', 'category')->find($orderProduct->product_id);

            if ($product) {
                // Get supplier_id for this product from the mapping
                $productSupplierId = $supplierMapping[(string)$product->id] ?? null;
                
                $this->orderProducts[] = [
                    'product_id' => (string)$product->id,
                    'quantity' => (string)$orderProduct->quantity,
                    'is_custom' => 0,
                    'custom_note' => '',
                    'custom_images' => [],
                    'product_type' => $this->getProductType($product), // Add product type for grouping
                    'supplier_id' => $productSupplierId ? (string)$productSupplierId : null,
                ];
                
                // Initialize dropdown state for each existing item
                $this->productDropdownOpen[$productIndex] = false;
                $this->productSearch[$productIndex] = '';
                $this->productSearchResults[$productIndex] = [];
                $this->productPage[$productIndex] = 1;
                $this->productHasMore[$productIndex] = false;
                $this->productLoading[$productIndex] = false;
                
                // Initialize supplier dropdown state
                $this->supplierDropdownOpen[$productIndex] = false;
                $this->supplierSearch[$productIndex] = '';
                $this->supplierSearchResults[$productIndex] = [];
                $this->supplierPage[$productIndex] = 1;
                $this->supplierHasMore[$productIndex] = false;
                $this->supplierLoading[$productIndex] = false;
                
                $productIndex++;
            }
        }

        // Load custom products from order_custom_products table for current order only
        $customProductsData = OrderCustomProduct::where('order_id', $model->id)->get();

        foreach ($customProductsData as $customProduct) {
            // Get images from new order_custom_product_images table
            $customImages = DB::table('order_custom_product_images')
                ->where('order_custom_product_id', $customProduct->id)
                ->orderBy('sort_order')
                ->pluck('image_path')
                ->toArray();

            // Get product_ids if they exist (these are stored in order_custom_products, not displayed as separate rows)
            $productIds = $customProduct->product_ids ?? [];
            if (!is_array($productIds)) {
                // Handle JSON string from database
                if (is_string($productIds)) {
                    $decoded = json_decode($productIds, true);
                    $productIds = is_array($decoded) ? $decoded : [];
                } else {
                    $productIds = $productIds ? [$productIds] : [];
                }
            }
            // Ensure all are integers
            $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn($id) => $id > 0)));
            
            // Add custom product entry
            $currentIndex = count($this->orderProducts);
            $this->orderProducts[] = [
                'product_id' => '',
                'quantity' => '', // Do not set a default quantity for custom products
                'is_custom' => 1,
                'custom_note' => $customProduct->custom_note ?? '',
                'custom_images' => $customImages,
                'custom_product_id' => $customProduct->id,
                'product_type' => 'workshop', // Custom products belong to workshop
                'product_ids' => $productIds, // Store product_ids for popup editing
            ];
            
            // Auto-expand if custom product has connected products
            if (!empty($productIds)) {
                $this->expandedCustomProducts[$currentIndex] = true;
            }
        }
        
        // orders.product_id/orders.quantity columns were removed; products are stored in order_products pivot only.
        
        $this->orderProducts = array_values($this->orderProducts);
        
        if (empty($this->orderProducts) && !$this->isEditMode) {
            $this->initializeProducts();
        }
    }

    protected function resetForm(): void
    {
        $this->site_manager_id = null;
        $this->transport_manager_id = null;
        $this->site_id = null;
        $this->status = 'pending';
        $this->previous_status = 'pending';
        $this->original_status = 'pending';
        $this->drop_location = null;
        $this->priority = PriorityEnum::High->value;
        $this->note = null;
        $this->expected_delivery_date = null;
        $this->initializeProducts();
    }

    protected function handleFileUpload(string $field, $value)
    {
        if ($value instanceof \Illuminate\Http\UploadedFile) {
            $path = $value->store('orders/' . $field, 'public');
            return $path;
        } elseif (is_string($value) && !empty($value)) {
            return $value;
        }
        return null;
    }

    public function save(): void
    {
        // Prevent saving delivered orders (orders.is_completed removed)
        if ($this->isEditMode && $this->editingId) {
            $order = Order::find($this->editingId);
            $statusValue = $order?->status?->value ?? ($order?->status ?? OrderStatusEnum::Pending->value);
            if ($order && (string)$statusValue === OrderStatusEnum::Delivery->value) {
                throw new \Exception('Delivered orders cannot be modified. They are read-only.');
            }
        }
        
        $user = auth('moderator')->user();
        $userRole = ($user && $user instanceof Moderator) ? $user->getRole() : null;
        
        if (!$this->isEditMode && $userRole === RoleEnum::TransportManager) {
            throw new \Exception('Transport Managers cannot create new orders. You can only manage assigned orders.');
        }
        
        // Manual validation for product and quantity first (to ensure errors are properly registered)
        $this->validateProductsAndQuantity();
        
        // Then run main validation
        $this->validate($this->getValidationRules(), $this->getValidationMessages());

        try {
            DB::transaction(function () use ($user, $userRole) {
                $data = $this->getFormData();
                $productsData = [];
                $customProductsData = [];
                $supplierMapping = []; // Initialize supplier mapping for LPO products

                $rawProducts = $this->orderProducts;

                foreach ($rawProducts as $index => $product) {
                    $isCustom = $product['is_custom'] ?? 0;
                    
                    if ($isCustom) {
                        $customNote = trim($product['custom_note'] ?? '');
                        $customImages = $product['custom_images'] ?? [];
                        if (!is_array($customImages)) {
                            $customImages = $customImages ? [$customImages] : [];
                        }
                        
                        // Get product_ids if set (from custom product popup)
                        $productIds = $product['product_ids'] ?? [];
                        
                        // Handle different data types
                        if (!is_array($productIds)) {
                            if (is_string($productIds)) {
                                $decoded = json_decode($productIds, true);
                                $productIds = is_array($decoded) ? $decoded : [];
                            } elseif (is_numeric($productIds)) {
                                $productIds = [(int)$productIds];
                            } elseif (!empty($productIds)) {
                                $productIds = [$productIds];
                            } else {
                                $productIds = [];
                            }
                        }
                        
                        // Filter and ensure they're integers, remove duplicates
                        $productIds = array_values(array_unique(array_filter(array_map(function($id) {
                            if (is_numeric($id)) {
                                return (int)$id;
                            }
                            if (is_string($id) && is_numeric($id)) {
                                return (int)$id;
                            }
                            return null;
                        }, $productIds), fn($id) => $id !== null && $id > 0)));
                        
                        // Log to debug
                        if (!empty($productIds)) {
                            \Illuminate\Support\Facades\Log::info('OrderForm: Collecting custom product with product_ids', [
                                'index' => $index,
                                'product_ids' => $productIds,
                                'custom_product_id' => $product['custom_product_id'] ?? null,
                                'raw_product_ids' => $product['product_ids'] ?? null,
                            ]);
                        }
                        
                        // Save custom product if it has note, images, OR product_ids (or if it's an existing custom product)
                        $existingCustomProductId = $product['custom_product_id'] ?? null;
                        if (!empty($customNote) || !empty($customImages) || !empty($productIds) || $existingCustomProductId) {
                            $customImagePaths = [];
                            foreach ($customImages as $customImage) {
                                // Livewire temporary uploads are not instances of UploadedFile but
                                // they still expose a `store` method. Support both strings (already stored)
                                // and any object that can be stored.
                                if (is_object($customImage) && method_exists($customImage, 'store')) {
                                    $storedPath = $customImage->store('orders/custom-products', 'public');
                                    if (!empty($storedPath)) {
                                        $customImagePaths[] = $storedPath;
                                    }
                                } elseif (is_string($customImage) && !empty($customImage)) {
                                    $customImagePaths[] = $customImage;
                                }
                            }
                            
                            // Always add/update custom product data
                            // Always include product_ids (even if empty array) so we can clear them
                            $customProductEntry = [
                                'custom_product_id' => $existingCustomProductId,
                                'custom_note' => $customNote,
                                'custom_images' => $customImagePaths,
                                'product_ids' => $productIds, // Always store product_ids (can be empty array)
                            ];
                            
                            // Log for debugging
                            \Illuminate\Support\Facades\Log::info('OrderForm: Adding custom product to save array', [
                                'index' => $index,
                                'custom_product_id' => $existingCustomProductId,
                                'product_ids' => $productIds,
                                'product_ids_count' => count($productIds),
                                'raw_product_ids_from_array' => $product['product_ids'] ?? null,
                            ]);
                            
                            $customProductsData[] = $customProductEntry;
                        }
                    } else {
                        // Regular products will be processed below via shared workflow service
                    }
                }

                // Use shared workflow service to extract regular products + LPO supplier mapping
                /** @var \App\Services\OrderWorkflowService $workflow */
                $workflow = app(\App\Services\OrderWorkflowService::class);
                [$regularProductsData, $regularSupplierMapping] = $workflow->extractRegularProductsAndSuppliers($rawProducts);

                // Merge into local structures (in case custom handling added other entries)
                $productsData = array_replace($productsData, $regularProductsData);
                $supplierMapping = array_replace($supplierMapping, $regularSupplierMapping);

                if ($this->isEditMode && $this->editingId) {
                    $model = Order::with('products.productImages')->findOrFail($this->editingId);
                    $oldSiteId = $model->site_id;
                    $oldTransportManagerId = $model->transport_manager_id;
                    $oldStatus = $model->status?->value ?? 'pending';
                    
                    $oldProductsData = [];
                    foreach ($model->products as $product) {
                        $oldProductsData[$product->id] = [
                            'quantity' => (float)$product->pivot->quantity,
                        ];
                    }
                    
                    if ($userRole === RoleEnum::StoreManager || $userRole === RoleEnum::SuperAdmin) {
                        if (isset($data['status'])) {
                            // Normalize status for LPO orders
                            $data['status'] = $this->normalizeStatusForOrder($data['status'], $model);
                            
                            // Sync product statuses based on order status when status changes
                            if ($data['status'] !== $oldStatus) {
                                $mappedProductStatuses = $this->mapOrderStatusToProductStatuses($data['status']);
                                // Update local product statuses from mapping
                                foreach ($mappedProductStatuses as $type => $mappedStatus) {
                                    // Only update if not manually changed (preserve manual overrides)
                                    if (!isset($this->productStatuses[$type]) || 
                                        $this->productStatuses[$type] === ($model->product_status[$type] ?? 'pending')) {
                                        $this->productStatuses[$type] = $mappedStatus;
                                    }
                                }
                            }
                            
                            if ($data['status'] === 'approved' && $oldStatus !== 'approved') {
                                $transportManagerId = $data['transport_manager_id'] ?? $model->transport_manager_id;
                                if (!empty($transportManagerId)) {
                                    // Override to in_transit if transport manager is assigned
                                    $data['status'] = OrderStatusEnum::InTransit->value;
                                }
                            }
                            
                            if ($data['status'] === OrderStatusEnum::Delivery->value) {
                                // For LPO orders, must be in transit before marking as delivered
                                if ($model->is_lpo && $oldStatus !== 'in_transit') {
                                    throw new \Exception('LPO must be in transit before marking as delivered.');
                                }
                                // deliveries table removed; no delivery record sync
                            }
                        }
                        
                    } else {
                        unset($data['transport_manager_id']); // Transport manager is now assigned in Delivery module
                    }
                    
                    if ($userRole === RoleEnum::TransportManager) {
                        throw new \Exception('Transport Managers cannot manage orders here.');
                    }
                    
                    if ($userRole === RoleEnum::SiteSupervisor) {
                        unset($data['transport_manager_id']); // Transport manager is now assigned in Delivery module
                        unset($data['drop_location']);
                    }
                    
                    // Remove transport_manager_id from data - it should only be assigned in Delivery module
                    unset($data['transport_manager_id']);
                    
                    // Validate site is active if site_id is being changed or set
                    if (isset($data['site_id']) && $data['site_id'] != $model->site_id) {
                        $siteId = $data['site_id'] ?? null;
                        if ($siteId) {
                            $site = Site::find($siteId);
                            if (!$site) {
                                throw new \Exception('Site not found.');
                            }
                            if (!$site->status) {
                                throw new \Exception('Cannot change order to inactive site. The site must be active to place orders.');
                            }
                        }
                    }
                    
                    // Check status changes BEFORE updating the model
                    $newStatus = $data['status'] ?? $oldStatus;
                    // Stock deduction/restoration in admin should only be tied to APPROVED status,
                    // not in_transit / delivered / completed, to avoid double deductions.
                    $wasApprovedOrInTransit = in_array($oldStatus, ['approved']);
                    $isNowApprovedOrInTransit = in_array($newStatus, ['approved']);
                    
                    // Update product_status based on current products
                    $hasHardwareProducts = false;
                    $hasWarehouseProducts = false;
                    $hasLpoProducts = false;
                    $hasCustomProducts = !empty($customProductsData);
                    
                    // Check all products to determine types
                    foreach ($productsData as $productId => $productInfo) {
                        $product = Product::find($productId);
                        if ($product && $product->store) {
                            // Product store is cast to StoreEnum, so compare directly
                            if ($product->store === StoreEnum::LPO) {
                                $hasLpoProducts = true;
                            } elseif ($product->store === StoreEnum::WarehouseStore) {
                                $hasWarehouseProducts = true;
                            } elseif ($product->store === StoreEnum::HardwareStore) {
                                $hasHardwareProducts = true;
                            } else {
                                // Unknown store type, default to hardware
                                $hasHardwareProducts = true;
                            }
                        } else {
                            // Product not found or no store set, default to hardware
                            $hasHardwareProducts = true;
                        }
                    }
                    
                    // Update product_status based on current productStatuses (which may have been synced from order status)
                    // Preserve existing LPO structure if it exists, otherwise initialize as array
                    $existingProductStatus = $model->product_status ?? [];
                    $lpoStatus = $existingProductStatus['lpo'] ?? [];
                    
                    // Handle legacy format where LPO might be a string
                    if (is_string($lpoStatus)) {
                        $lpoStatus = []; // Reset to array format
                    }
                    
                    // If we have supplier mapping, ensure all suppliers have status
                    if ($hasLpoProducts && !empty($supplierMapping) && is_array($lpoStatus)) {
                        foreach ($supplierMapping as $productId => $supplierId) {
                            // Only set status if not already set (preserve existing statuses)
                            if (!isset($lpoStatus[(string)$supplierId])) {
                                $lpoStatus[(string)$supplierId] = $this->productStatuses['lpo'] ?? 'pending';
                            }
                        }
                    }
                    
                    // Build product_status only for product types that actually exist in this order
                    $productStatusPayload = [];
                    if ($hasHardwareProducts) {
                        $productStatusPayload['hardware'] = $this->productStatuses['hardware'] ?? 'pending';
                    }
                    if ($hasWarehouseProducts || $hasCustomProducts) {
                        // Persist as 'warehouse' in DB but use 'workshop' key in UI
                        $productStatusPayload['workshop'] = $this->productStatuses['workshop'] ?? 'pending';
                    }
                    if ($hasLpoProducts) {
                        $productStatusPayload['lpo'] = $lpoStatus;
                    }
                    if ($hasCustomProducts) {
                        $productStatusPayload['custom'] = $this->productStatuses['custom'] ?? 'pending';
                    }

                    $data['product_status'] = $productStatusPayload;
                    
                    // Update product_driver_details if status is in_transit
                    if ($this->status === 'in_transit') {
                        // Ensure product_driver_details is synced
                        if (empty($this->product_driver_details)) {
                            $this->product_driver_details = $this->productDriverDetails;
                        }
                        $data['product_driver_details'] = $this->product_driver_details;
                    } else {
                        // Keep existing driver details even if status is not in_transit
                        $data['product_driver_details'] = $this->product_driver_details ?: $this->productDriverDetails;
                    }
                    
                    // Add supplier_id mapping for LPO products
                    if (!empty($supplierMapping)) {
                        $data['supplier_id'] = $supplierMapping;
                    } else {
                        $data['supplier_id'] = [];
                    }
                    
                    $model->update($data);
                    
                    // Check if status changed to rejected
                    $newStatus = $data['status'] ?? $model->status?->value ?? 'pending';
                    $isStatusRejected = ($newStatus === 'rejected' || $newStatus === OrderStatusEnum::Rejected->value);
                    $wasStatusRejected = ($oldStatus === 'rejected' || $oldStatus === OrderStatusEnum::Rejected->value);
                    
                    // Sync order status from product statuses if order status wasn't manually changed
                    // (If order status was manually changed, product statuses were already synced from order status above)
                    $orderStatusManuallyChanged = false;
                    if (($userRole === RoleEnum::StoreManager || $userRole === RoleEnum::SuperAdmin) && 
                        isset($data['status']) && $data['status'] !== $oldStatus) {
                        $orderStatusManuallyChanged = true;
                    }
                    
                    // If order status wasn't manually changed but product statuses were updated, sync order status
                    if (!$orderStatusManuallyChanged && isset($data['product_status'])) {
                        $model->refresh();
                        $model->syncOrderStatusFromProductStatuses();
                        $model->refresh();
                        // Check again if status became rejected after sync
                        $finalStatus = $model->status?->value ?? $model->status ?? 'pending';
                        $isStatusRejected = ($finalStatus === 'rejected' || $finalStatus === OrderStatusEnum::Rejected->value);
                    }
                    
                    // If status changed to rejected (from any other status), open rejection details modal
                    if ($isStatusRejected && !$wasStatusRejected) {
                        $model->refresh();
                        $model->load(['products', 'customProducts']);
                        $this->prepareRejectionDetails($model);
                        $this->showRejectionDetailsModal = true;
                    }
                    
                    // Manually sync products with only quantity field (is_custom, custom_note, custom_image were moved to order_custom_products table)
                    // Use manual DB operations to avoid Laravel trying to update non-existent columns
                    $productIds = array_keys($productsData);
                    
                    // Get existing product IDs directly from DB
                    $existingProductIds = DB::table('order_products')
                        ->where('order_id', $model->id)
                        ->pluck('product_id')
                        ->toArray();
                    
                    // Delete products that are no longer in the list
                    $productsToDelete = array_diff($existingProductIds, $productIds);
                    if (!empty($productsToDelete)) {
                        DB::table('order_products')
                            ->where('order_id', $model->id)
                            ->whereIn('product_id', $productsToDelete)
                            ->delete();
                    }
                    
                    // Attach new products or update existing ones
                    foreach ($productsData as $productId => $productData) {
                        $exists = DB::table('order_products')
                            ->where('order_id', $model->id)
                            ->where('product_id', $productId)
                            ->exists();
                        
                        if ($exists) {
                            // Update existing pivot record - only update quantity
                            DB::table('order_products')
                                ->where('order_id', $model->id)
                                ->where('product_id', $productId)
                                ->update([
                                    'quantity' => $productData['quantity'],
                                    'updated_at' => now(),
                                ]);
                        } else {
                            // Insert new product - only include quantity
                            DB::table('order_products')->insert([
                                'order_id' => $model->id,
                                'product_id' => $productId,
                                'quantity' => $productData['quantity'],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    
                    // Sync custom products - update existing, create new, delete removed
                    $existingCustomProductIds = OrderCustomProduct::where('order_id', $model->id)->pluck('id')->toArray();
                    $updatedCustomProductIds = [];
                    
                    if (!empty($customProductsData)) {
                        foreach ($customProductsData as $customProduct) {
                            if (!empty($customProduct['custom_product_id'])) {
                                // Update existing custom product
                                $existingCustomProduct = OrderCustomProduct::find($customProduct['custom_product_id']);
                                if ($existingCustomProduct) {
                                    $updateData = [
                                        'custom_note' => $customProduct['custom_note'],
                                    ];
                                    
                                    // Always update product_ids (even if empty array)
                                    $productIdsToSave = $customProduct['product_ids'] ?? [];
                                    if (!is_array($productIdsToSave)) {
                                        $productIdsToSave = [];
                                    }
                                    // Ensure they're integers
                                    $productIdsToSave = array_values(array_unique(array_filter(array_map('intval', $productIdsToSave), fn($id) => $id > 0)));
                                    $updateData['product_ids'] = $productIdsToSave;
                                    
                                    // Preserve existing product_details (including materials) - don't overwrite it
                                    // Materials are updated via the popup modal, so we preserve them here
                                    // The product_details will remain unchanged unless explicitly updated via popup
                                    
                                    // Log for debugging
                                    \Illuminate\Support\Facades\Log::info('OrderForm: Updating custom product product_ids', [
                                        'custom_product_id' => $existingCustomProduct->id,
                                        'product_ids' => $productIdsToSave,
                                    ]);
                                    
                                    // Only update the fields we want to change, preserve product_details
                                    $existingCustomProduct->custom_note = $updateData['custom_note'];
                                    $existingCustomProduct->product_ids = $updateData['product_ids'];
                                    $existingCustomProduct->save();
                                    
                                    // Verify update
                                    $existingCustomProduct->refresh();
                                    \Illuminate\Support\Facades\Log::info('OrderForm: After update, product_ids in DB', [
                                        'custom_product_id' => $existingCustomProduct->id,
                                        'product_ids' => $existingCustomProduct->product_ids,
                                    ]);
                                    
                                    // Delete existing images and save new ones
                                    OrderCustomProductImage::where('order_custom_product_id', $existingCustomProduct->id)->delete();
                                    $this->saveCustomProductImages($existingCustomProduct->id, $customProduct['custom_images'] ?? []);
                                    
                                    $updatedCustomProductIds[] = $customProduct['custom_product_id'];
                                }
                            } else {
                                // Create new custom product
                                $productIdsToSave = $customProduct['product_ids'] ?? [];
                                
                                // Handle different data types
                                if (!is_array($productIdsToSave)) {
                                    if (is_string($productIdsToSave)) {
                                        $decoded = json_decode($productIdsToSave, true);
                                        $productIdsToSave = is_array($decoded) ? $decoded : [];
                                    } elseif (is_numeric($productIdsToSave)) {
                                        $productIdsToSave = [(int)$productIdsToSave];
                                    } elseif (!empty($productIdsToSave)) {
                                        $productIdsToSave = [$productIdsToSave];
                                    } else {
                                        $productIdsToSave = [];
                                    }
                                }
                                
                                // Ensure they're integers and filter
                                $productIdsToSave = array_values(array_unique(array_filter(array_map(function($id) {
                                    if (is_numeric($id)) {
                                        return (int)$id;
                                    }
                                    if (is_string($id) && is_numeric($id)) {
                                        return (int)$id;
                                    }
                                    return null;
                                }, $productIdsToSave), fn($id) => $id !== null && $id > 0)));
                                
                                $createData = [
                                    'order_id' => $model->id,
                                    'custom_note' => $customProduct['custom_note'],
                                    'product_ids' => $productIdsToSave, // Always include product_ids
                                ];
                                
                                // Log for debugging
                                \Illuminate\Support\Facades\Log::info('OrderForm: Creating custom product with product_ids', [
                                    'order_id' => $model->id,
                                    'product_ids_from_array' => $productIdsToSave,
                                    'product_ids_from_customProduct' => $customProduct['product_ids'] ?? null,
                                    'raw_data' => $customProduct,
                                ]);
                                
                                $newCustomProduct = OrderCustomProduct::create($createData);
                                
                                // Verify creation
                                $newCustomProduct->refresh();
                                \Illuminate\Support\Facades\Log::info('OrderForm: After create, product_ids in DB', [
                                    'custom_product_id' => $newCustomProduct->id,
                                    'product_ids_in_db' => $newCustomProduct->product_ids,
                                    'expected_product_ids' => $productIdsToSave,
                                ]);
                                
                                // Save images to new table
                                $this->saveCustomProductImages($newCustomProduct->id, $customProduct['custom_images'] ?? []);
                                
                                $updatedCustomProductIds[] = $newCustomProduct->id;
                            }
                        }
                    }
                    
                    // Delete removed custom products (this will cascade delete images)
                    $idsToDelete = array_diff($existingCustomProductIds, $updatedCustomProductIds);
                    if (!empty($idsToDelete)) {
                        OrderCustomProduct::whereIn('id', $idsToDelete)->delete();
                    }
                    
                    // Handle stock adjustments based on order status changes
                    if (!$this->stockService) {
                        $this->stockService = app(StockService::class);
                    }
                    
                    if (!$wasApprovedOrInTransit && $isNowApprovedOrInTransit) {
                        $this->deductStockForOrder($model, $productsData, null); // Use general stock
                    }elseif ($wasApprovedOrInTransit && !$isNowApprovedOrInTransit) {
                        Log::info("OrderForm CASE 2: Status changed from approved/in_transit to pending - RESTORING STOCK");
                        $this->restoreStockForOrder($model, $oldProductsData, null); // Use general stock
                    }elseif ($isNowApprovedOrInTransit && !empty($oldProductsData)) {
                        Log::info("OrderForm CASE 3: Order already approved/in_transit and products changed - ADJUSTING STOCK");
                        $this->adjustStockForProductChanges($model, $oldProductsData, $productsData, null); // Use general stock
                    } else {
                        Log::info("OrderForm NO STOCK OPERATION: wasApproved=" . ($wasApprovedOrInTransit ? 'YES' : 'NO') . ", isNowApproved=" . ($isNowApprovedOrInTransit ? 'YES' : 'NO') . ", hasOldProducts=" . (!empty($oldProductsData) ? 'YES' : 'NO'));
                    }
                    $model->refresh();
                    
                    // deliveries table removed; transport manager assignment no longer creates delivery records here

                    if ($oldStatus !== 'approved' && ($model->status?->value ?? 'pending') === 'approved') {
                        if ($model->siteManager) {
                            $model->siteManager->notify(new \App\Notifications\OrderApprovedNotification($model));
                        }
                    }
                    
                    if ($oldTransportManagerId != $model->transport_manager_id && $model->transport_manager_id) {
                        $model->load(['site', 'products.productImages', 'siteManager']);
                        $transportManager = Moderator::find($model->transport_manager_id);
                        if ($transportManager) {
                            try {
                                $transportManager->notify(new TransportManagerAssignedNotification($model));
                            } catch (\Exception $e) {
                                Log::error('Failed to send notification to Transport Manager: ' . $transportManager->email . ' - ' . $e->getMessage());
                            }
                        }
                    }

                    // deliveries table removed; no "ready for completion" checks

                } else {
                    // For new orders, default to 'pending' if status is not selected
                    if (empty($data['status']) || !isset($data['status']) || $data['status'] === OrderStatusEnum::Delivery->value) {
                        $data['status'] = OrderStatusEnum::Pending->value;
                    }
                    // Determine product types and initialize product_status
                    $hasLpoProducts = false;
                    $hasHardwareProducts = false;
                    $hasWarehouseProducts = false;
                    $primaryStore = StoreEnum::HardwareStore->value; // Default store
                    
                    // Check all products to determine types
                    foreach ($productsData as $productId => $productInfo) {
                        $product = Product::find($productId);
                        if ($product && $product->store) {
                            // Product store is cast to StoreEnum, so compare directly
                            if ($product->store === StoreEnum::LPO) {
                                $hasLpoProducts = true;
                                $primaryStore = StoreEnum::LPO->value;
                            } elseif ($product->store === StoreEnum::WarehouseStore) {
                                $hasWarehouseProducts = true;
                                if (!$hasLpoProducts) {
                                    $primaryStore = StoreEnum::WarehouseStore->value;
                                }
                            } elseif ($product->store === StoreEnum::HardwareStore) {
                                $hasHardwareProducts = true;
                            } else {
                                // Unknown store type, default to hardware
                                $hasHardwareProducts = true;
                            }
                        } else {
                            // Product not found or no store set, default to hardware
                            $hasHardwareProducts = true;
                        }
                    }
                    
                    // Initialize product_status based on product groups
                    $productStatus = [
                        'hardware' => $this->productStatuses['hardware'] ?? 'pending',
                        'workshop' => $this->productStatuses['workshop'] ?? 'pending',
                        'lpo' => [], // Supplier-wise: {supplier_id: status}
                    ];
                    
                    // For NEW LPO orders, force initial supplier status to 'pending'
                    // regardless of any temporary in-memory mapping, so that
                    // LPO-only orders show as Pending right after creation.
                    $initialLpoStatus = (!$this->isEditMode && $hasLpoProducts)
                        ? 'pending'
                        : ($this->productStatuses['lpo'] ?? 'pending');
                    
                    // Initialize LPO status with supplier-specific statuses
                    if ($hasLpoProducts && !empty($supplierMapping)) {
                        foreach ($supplierMapping as $productId => $supplierId) {
                            $productStatus['lpo'][(string)$supplierId] = $initialLpoStatus;
                        }
                    }
                    
                    // Set order data
                    $orderData = $data;
                    $orderData['is_lpo'] = $hasLpoProducts;
                    $orderData['store'] = $primaryStore;
                    $orderData['product_status'] = $productStatus;
                    
                    // Add product_driver_details if status is in_transit
                    if (isset($orderData['status']) && $orderData['status'] === 'in_transit') {
                        // Ensure product_driver_details is synced
                        if (empty($this->product_driver_details)) {
                            $this->product_driver_details = $this->productDriverDetails;
                        }
                        $orderData['product_driver_details'] = $this->product_driver_details;
                    } else {
                        // Initialize empty driver details
                        $orderData['product_driver_details'] = [];
                    }
                    
                    // Add supplier_id mapping for LPO products
                    if (!empty($supplierMapping)) {
                        $orderData['supplier_id'] = $supplierMapping;
                    } else {
                        $orderData['supplier_id'] = [];
                    }
                    
                    // Set default status to 'pending' if not selected
                    if (empty($orderData['status']) || !isset($orderData['status'])) {
                        $orderData['status'] = OrderStatusEnum::Pending->value;
                    }
                    
                    // Validate site is active before creating order
                    $siteId = $orderData['site_id'] ?? null;
                    if ($siteId) {
                        $site = Site::find($siteId);
                        if (!$site) {
                            throw new \Exception('Site not found.');
                        }
                        if (!$site->status) {
                            throw new \Exception('Cannot place order for inactive site. The site must be active to place orders.');
                        }
                    }
                    
                    // Create single order
                    $order = Order::create($orderData);
                    
                    // Attach all products to the order
                    foreach ($productsData as $productId => $productData) {
                        DB::table('order_products')->insert([
                            'order_id' => $order->id,
                            'product_id' => $productId,
                            'quantity' => $productData['quantity'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    
                    // Attach custom products
                    if (!empty($customProductsData)) {
                        foreach ($customProductsData as $customProduct) {
                            $createData = [
                                'order_id' => $order->id,
                                'custom_note' => $customProduct['custom_note'],
                            ];
                            
                            // Always include product_ids (even if empty array)
                            $productIdsToSave = $customProduct['product_ids'] ?? [];
                            
                            // Handle different data types
                            if (!is_array($productIdsToSave)) {
                                if (is_string($productIdsToSave)) {
                                    $decoded = json_decode($productIdsToSave, true);
                                    $productIdsToSave = is_array($decoded) ? $decoded : [];
                                } elseif (is_numeric($productIdsToSave)) {
                                    $productIdsToSave = [(int)$productIdsToSave];
                                } elseif (!empty($productIdsToSave)) {
                                    $productIdsToSave = [$productIdsToSave];
                                } else {
                                    $productIdsToSave = [];
                                }
                            }
                            
                            // Ensure they're integers and filter
                            $productIdsToSave = array_values(array_unique(array_filter(array_map(function($id) {
                                if (is_numeric($id)) {
                                    return (int)$id;
                                }
                                if (is_string($id) && is_numeric($id)) {
                                    return (int)$id;
                                }
                                return null;
                            }, $productIdsToSave), fn($id) => $id !== null && $id > 0)));
                            
                            $createData['product_ids'] = $productIdsToSave;
                            
                            // Log before create
                            \Illuminate\Support\Facades\Log::info('OrderForm: Creating custom product (create mode) with product_ids', [
                                'order_id' => $order->id,
                                'product_ids_to_save' => $productIdsToSave,
                                'product_ids_from_array' => $customProduct['product_ids'] ?? null,
                            ]);
                            
                            $newCustomProduct = OrderCustomProduct::create($createData);
                            
                            // Verify after create
                            $newCustomProduct->refresh();
                            \Illuminate\Support\Facades\Log::info('OrderForm: After create (create mode), product_ids in DB', [
                                'custom_product_id' => $newCustomProduct->id,
                                'product_ids_in_db' => $newCustomProduct->product_ids,
                                'expected' => $productIdsToSave,
                            ]);
                            $this->saveCustomProductImages($newCustomProduct->id, $customProduct['custom_images'] ?? []);
                        }
                    }
                    
                    $order->refresh();

                    // For newly created orders, if overall order status is APPROVED,
                    // deduct stock for all non-LPO products once based on their quantities.
                    $orderStatusValue = $order->status?->value ?? 'pending';
                    if ($orderStatusValue === 'approved') {
                        if (!$this->stockService) {
                            $this->stockService = app(StockService::class);
                        }

                        $productsToDeduct = [];
                        foreach ($productsData as $productId => $productInfo) {
                            $product = Product::find($productId);
                            if (!$product || !$product->store) {
                                continue;
                            }

                            // Skip LPO products entirely – handled in LPO/LPO purchase flow
                            if ($product->store === StoreEnum::LPO) {
                                continue;
                            }

                            $productsToDeduct[$productId] = $productInfo;
                        }

                        if (!empty($productsToDeduct)) {
                            $this->deductStockForOrder($order, $productsToDeduct, null);
                        }
                    }
                    
                    // deliveries table removed; do not create delivery records here
                    // If transport manager is assigned, reflect that with status only.
                    if (!empty($data['transport_manager_id'])) {
                        $order->update([
                            'status' => OrderStatusEnum::InTransit->value,
                        ]);
                    }
                    
                    // Send notifications to store managers
                    $storeManagers = Moderator::where('role', RoleEnum::StoreManager->value)
                        ->where('status', 'active')
                        ->get();
                    
                    if ($storeManagers->isEmpty()) {
                        Log::warning('No active Store Managers found to notify about Order #' . $order->id);
                    } else {
                        foreach ($storeManagers as $storeManager) {
                            try {
                                $storeManager->notify(new OrderCreatedNotification($order));
                            } catch (\Exception $e) {
                                Log::error('Failed to send notification to Store Manager: ' . $storeManager->email . ' - ' . $e->getMessage());
                            }
                        }
                    }
                    
                    $model = $order;
                }
            });

            
            $message = $this->isEditMode ? 'Order updated successfully!' : 'Order created successfully!';
            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.orders.index'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Livewire can display them properly
            throw $e;
        } catch (\Exception $e) {
            // Log the full error for debugging
            Log::error('Order save failed', [
                'is_edit_mode' => $this->isEditMode,
                'editing_id' => $this->editingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Show user-friendly error message
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'SQLSTATE') || str_contains($errorMessage, 'database')) {
                $errorMessage = 'A database error occurred. Please try again or contact support.';
            }
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $errorMessage]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.orders.index'));
    }

    /**
     * Pre-check stock before approving a product type.
     * If stock is insufficient, the status should NOT be updated.
     */
    protected function canApproveProductType(Order $order, string $type): array
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        // LPO is external; no stock checks/deductions
        if ($type === 'lpo') {
            return ['ok' => true, 'message' => null];
        }

        $order->loadMissing(['products', 'customProducts']);

        // Build product list for this type (same selection logic as deductStockForProductType)
        $productsToCheck = [];

        if ($type === 'hardware') {
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::HardwareStore) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToCheck[$product->id] = [
                            'quantity' => (int) $pivot->quantity,
                        ];
                    }
                }
            }
        } elseif ($type === 'workshop') {
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::WarehouseStore) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToCheck[$product->id] = [
                            'quantity' => (int) $pivot->quantity,
                        ];
                    }
                }
            }

            // Include connected products from custom products (warehouse type)
            $customProducts = $order->customProducts;
            if ($customProducts && $customProducts->isNotEmpty()) {
                foreach ($customProducts as $customProduct) {
                    $productIds = $customProduct->product_ids ?? [];
                    if (!is_array($productIds)) {
                        if (is_string($productIds)) {
                            $decoded = json_decode($productIds, true);
                            $productIds = is_array($decoded) ? $decoded : [];
                        } else {
                            $productIds = $productIds ? [$productIds] : [];
                        }
                    }

                    // Use total quantity from product_details if available; otherwise default to 1
                    $details = is_array($customProduct->product_details ?? null)
                        ? $customProduct->product_details
                        : [];
                    $customQty = isset($details['quantity'])
                        ? (int) $details['quantity']
                        : 1;
                    $customQty = max(1, $customQty);

                    foreach ($productIds as $productId) {
                        $pid = (int) $productId;
                        if ($pid <= 0) {
                            continue;
                        }
                        if (isset($productsToCheck[$pid])) {
                            $productsToCheck[$pid]['quantity'] += $customQty;
                        } else {
                            $productsToCheck[$pid] = [
                                'quantity' => $customQty,
                            ];
                        }
                    }
                }
            }
        }

        // Aggregate material requirements across all products in this type
        $requiredMaterials = []; // material_id => required_qty (float)
        $orderId = $order->id;

        foreach ($productsToCheck as $productId => $productInfo) {
            $qty = (int) ($productInfo['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            /** @var \App\Models\Product|null $product */
            $product = Product::with('materials')->find((int) $productId);
            if (!$product) {
                continue;
            }

            // Skip LPO products
            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }

            // If we already deducted stock for this order/product/type (last adjustment is 'out'), allow approving without re-checking.
            $lastAdjustmentType = \App\Models\Stock::where('product_id', (int) $productId)
                ->where('reference_id', $orderId)
                ->where('reference_type', Order::class)
                ->where('status', true)
                ->where(function ($query) use ($type, $orderId) {
                    $query->where('name', 'like', "%({$type})%")
                        ->orWhere('notes', 'like', "%Product Status Approved ({$type})%")
                        ->orWhere('notes', 'like', "%Stock deducted for Order #{$orderId} - Product Status Approved ({$type})%");
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('adjustment_type');

            if ($lastAdjustmentType === 'out') {
                continue;
            }

            $isProduct = ($product->is_product ?? 1) > 0;
            $available = $isProduct
                ? $this->stockService->getCurrentProductStock((int) $productId, null)
                : $this->stockService->getCurrentMaterialStock((int) $productId, null);

            if ($available < $qty) {
                $productName = $product->product_name ?? "Product ID {$productId}";
                return [
                    'ok' => false,
                    'message' => "{$type}: Failed to deduct stock for {$productName}. Insufficient stock. Available: {$available}, Requested: {$qty}",
                ];
            }

            foreach ($product->materials as $material) {
                $perUnit = (float) ($material->pivot->quantity ?? 0);
                if ($perUnit <= 0) {
                    continue;
                }
                $requiredMaterials[(int) $material->id] = ($requiredMaterials[(int) $material->id] ?? 0.0) + ($perUnit * $qty);
            }
        }

        // When checking materials, include BOTH general stock and site-specific stock,
        // same as the actual deduction logic in deductStockForOrder().
        $siteId = $order->site_id ? (int) $order->site_id : null;

        foreach ($requiredMaterials as $materialId => $requiredQty) {
            $requiredQtyInt = (int) ceil($requiredQty);
            if ($requiredQtyInt <= 0) {
                continue;
            }

            $generalStock = $this->stockService->getCurrentMaterialStock((int) $materialId, null);
            $siteStock = $siteId ? $this->stockService->getCurrentMaterialStock((int) $materialId, $siteId) : 0;
            $available = $generalStock + $siteStock;

            if ($available < $requiredQtyInt) {
                $materialModel = Product::find((int) $materialId);
                $materialName = $materialModel?->product_name ?? $materialModel?->material_name ?? "Material ID {$materialId}";
                return [
                    'ok' => false,
                    'message' => "{$type}: Failed to deduct stock for {$materialName}. Insufficient material stock. Available: {$available}, Requested: {$requiredQtyInt}",
                ];
            }
        }

        return ['ok' => true, 'message' => null];
    }

    /**
     * Deduct stock for products of a specific type when product status changes to 'approved'
     */
    protected function deductStockForProductType(Order $order, string $type): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        // Load order products
        $order->load('products');
        
        // Get products for this specific type
        $productsToDeduct = [];
        
        if ($type === 'hardware') {
            // Get hardware store products
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::HardwareStore) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToDeduct[$product->id] = [
                            'quantity' => (int)$pivot->quantity,
                        ];
                    }
                }
            }
        } elseif ($type === 'workshop') {
            // Get workshop (warehouse store) products
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::WarehouseStore) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToDeduct[$product->id] = [
                            'quantity' => (int)$pivot->quantity,
                        ];
                    }
                }
            }
            
            // Also include custom products (they belong to warehouse type)
            $customProducts = $order->customProducts;
            if ($customProducts && $customProducts->isNotEmpty()) {
                foreach ($customProducts as $customProduct) {
                    $productIds = $customProduct->product_ids ?? [];
                    if (!is_array($productIds)) {
                        if (is_string($productIds)) {
                            $decoded = json_decode($productIds, true);
                            $productIds = is_array($decoded) ? $decoded : [];
                        } else {
                            $productIds = $productIds ? [$productIds] : [];
                        }
                    }

                    // Use total quantity from product_details if available; otherwise default to 1
                    $details = is_array($customProduct->product_details ?? null)
                        ? $customProduct->product_details
                        : [];
                    $customQty = isset($details['quantity'])
                        ? (int) $details['quantity']
                        : 1;
                    $customQty = max(1, $customQty);

                    foreach ($productIds as $productId) {
                        if (isset($productsToDeduct[$productId])) {
                            // If product already exists, increment quantity
                            $productsToDeduct[$productId]['quantity'] += $customQty;
                        } else {
                            $productsToDeduct[$productId] = [
                                'quantity' => $customQty,
                            ];
                        }
                    }
                }
            }
        } elseif ($type === 'lpo') {
            // Get LPO products
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::LPO) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToDeduct[$product->id] = [
                            'quantity' => (int)$pivot->quantity,
                        ];
                    }
                }
            }
        }
        
        // Skip LPO products from stock deduction (they are external)
        if ($type === 'lpo') {
            return;
        }
        
        // Deduct stock for products, checking if already deducted
        foreach ($productsToDeduct as $productId => $productInfo) {
            $product = Product::with('materials')->find($productId);
            
            // Skip if product not found
            if (!$product) {
                continue;
            }
            
            // Skip LPO products
            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }
            
            $quantity = (int)($productInfo['quantity'] ?? 0);
            
            if ($quantity > 0) {
                // Determine if this is a product or material based on is_product flag
                $isProduct = ($product->is_product ?? 1) > 0;
                
                // Check last stock adjustment for this order/product/type.
                // If the last adjustment is 'out', we consider it currently deducted and skip.
                $orderId = $order->id;
                $lastAdjustmentType = \App\Models\Stock::where('product_id', $productId)
                    ->where('reference_id', $orderId)
                    ->where('reference_type', Order::class)
                    ->where('status', true)
                    ->where(function($query) use ($type, $orderId) {
                        $query->where('name', 'like', "%({$type})%")
                              ->orWhere('notes', 'like', "%Product Status Approved ({$type})%")
                              ->orWhere('notes', 'like', "%Stock deducted for Order #{$orderId} - Product Status Approved ({$type})%");
                    })
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->value('adjustment_type');
                
                if ($lastAdjustmentType === 'out') {
                    Log::info("OrderForm: Stock already deducted for product {$productId} (type: {$type}) in order {$orderId}, skipping.");
                    continue;
                }
                
                try {
                    // Determine if this is a product or material based on is_product flag
                    $isProduct = ($product->is_product ?? 1) > 0;
                    
                    // Refresh product to ensure we have latest data
                    $product->refresh();
                    
                    // IMPORTANT:
                    // StockService->adjustStock(site_id != null) deducts ONLY from site stock.
                    // Admin order approval uses GENERAL stock (site_id = null).
                    // So we must check and deduct against GENERAL stock only to avoid false "Available: 0" errors.
                    $generalStock = $isProduct
                        ? $this->stockService->getCurrentProductStock((int)$productId, null)
                        : $this->stockService->getCurrentMaterialStock((int)$productId, null);
                    $totalAvailableStock = $generalStock;
                    
                    // Log stock check for debugging
                    Log::info("OrderForm: Stock check for product {$productId} (is_product: {$isProduct}) in order {$order->id}. General: {$generalStock}, Total: {$totalAvailableStock}, Requested: {$quantity}");
                    
                    if ($totalAvailableStock < $quantity) {
                        $productName = $product->product_name ?? "Product ID {$productId}";
                        Log::warning("OrderForm: Insufficient stock for product {$productId} in order {$order->id}. Available: {$totalAvailableStock}, Requested: {$quantity}");
                        throw new \RuntimeException("Failed to deduct stock for {$productName}. Insufficient stock. Available: {$totalAvailableStock}, Requested: {$quantity}");
                    }
                    
                    // 1) Deduct finished product stock - pass is_product flag explicitly
                    // Reload product fresh from database to ensure we have correct is_product flag
                    $productForDeduction = Product::withoutGlobalScopes()->find($productId);
                    if (!$productForDeduction) {
                        throw new \Exception("Product {$productId} not found");
                    }
                    $isProductForDeduction = ($productForDeduction->is_product ?? 1) > 0;
                    
                    // Ensure available_qty is synced before deduction (in case it's out of sync)
                    if ($isProductForDeduction && $productForDeduction->available_qty != $generalStock) {
                        Log::info("OrderForm: Syncing available_qty for product {$productId}. Current: {$productForDeduction->available_qty}, Stock: {$generalStock}");
                        $productForDeduction->update(['available_qty' => $generalStock]);
                    }
                    
                    Log::info("OrderForm: Deducting stock for product {$productId} (is_product: {$isProductForDeduction}, available_qty: {$productForDeduction->available_qty}) in order {$order->id}. Quantity: {$quantity}, Site ID: " . ($order->site_id ?? 'NULL') . ", Current Stock: {$generalStock}");
                    
                    $this->stockService->adjustStock(
                        (int)$productId,
                        $quantity,
                        'out',
                        null,
                        "Stock deducted for Order #{$order->id} - Product Status Approved ({$type}) (quantity: " . number_format($quantity, 2) . ")",
                        $order,
                        "Order #{$order->id} - Stock Deducted ({$type})",
                        null,
                        $isProductForDeduction // Pass is_product flag explicitly
                    );

                    // 2) Deduct material stock based on product BOM
                    foreach ($product->materials as $material) {
                        $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                        if ($materialQtyPerUnit <= 0) {
                            continue;
                        }

                        $materialTotalQty = $materialQtyPerUnit * $quantity;

                        // Idempotency for material deductions:
                        // If we've already created an 'out' stock entry for this order+material, skip to avoid double deduction.
                        $materialAlreadyDeducted = Stock::where('product_id', (int) $material->id)
                            ->where('status', true)
                            ->where('adjustment_type', 'out')
                            ->where('reference_id', $order->id)
                            ->where('reference_type', Order::class)
                            ->exists();

                        if ($materialAlreadyDeducted) {
                            Log::info("OrderForm::deductStockForOrder: Material stock already deducted for material {$material->id} ({$material->material_name}) in order {$order->id}, skipping duplicate deduction.");
                            continue;
                        }

                        $this->stockService->adjustMaterialStock(
                            (int)$material->id,
                            (int)$materialTotalQty,
                            'out',
                            null,
                            "Material stock deducted for Order #{$order->id} - Product Status Approved ({$type}) (product: {$product->product_name}, ordered qty: " . number_format($quantity, 2) . ", material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: " . number_format($materialTotalQty, 2) . ")",
                            $order,
                            "Order #{$order->id} - Material {$material->material_name} Deducted ({$type})",
                            [
                                'product_id' => $product->id,
                                'product_quantity' => $quantity,
                                'material_quantity_per_unit' => $materialQtyPerUnit,
                                'material_total_quantity' => $materialTotalQty,
                                'product_type' => $type,
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    Log::error("OrderForm: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    $productName = $product->product_name ?? "Product ID {$productId}";
                    throw new \RuntimeException("Failed to deduct stock for {$productName}. " . $e->getMessage(), 0, $e);
                }
            }
        }
    }

    /**
     * Restore stock for products of a specific type when product status changes to 'rejected'
     * Uses GENERAL stock (site_id = null) and tags entries with ({type}) so re-approve can re-deduct.
     */
    protected function restoreStockForProductType(Order $order, string $type): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        $order->loadMissing(['products', 'customProducts']);

        // Determine products to restore (same selection logic as deduct)
        $productsToRestore = [];

        if ($type === 'hardware') {
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::HardwareStore) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToRestore[$product->id] = ['quantity' => (int)$pivot->quantity];
                    }
                }
            }
        } elseif ($type === 'workshop') {
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::WarehouseStore) {
                    $pivot = $product->pivot;
                    if ($pivot && !empty($pivot->quantity)) {
                        $productsToRestore[$product->id] = ['quantity' => (int)$pivot->quantity];
                    }
                }
            }

            // Include custom products (workshop/warehouse type)
            $customProducts = $order->customProducts;
            if ($customProducts && $customProducts->isNotEmpty()) {
                foreach ($customProducts as $customProduct) {
                    $productIds = $customProduct->product_ids ?? [];
                    if (!is_array($productIds)) {
                        if (is_string($productIds)) {
                            $decoded = json_decode($productIds, true);
                            $productIds = is_array($decoded) ? $decoded : [];
                        } else {
                            $productIds = $productIds ? [$productIds] : [];
                        }
                    }
                    $customQty = 1;
                    foreach ($productIds as $productId) {
                        $pid = (int)$productId;
                        if ($pid <= 0) continue;
                        if (isset($productsToRestore[$pid])) {
                            $productsToRestore[$pid]['quantity'] += $customQty;
                        } else {
                            $productsToRestore[$pid] = ['quantity' => $customQty];
                        }
                    }
                }
            }
        } elseif ($type === 'lpo') {
            return; // no stock restore for LPO
        }

        $orderId = $order->id;

        foreach ($productsToRestore as $productId => $info) {
            $qty = (int)($info['quantity'] ?? 0);
            if ($qty <= 0) continue;

            $product = Product::with('materials')->find($productId);
            if (!$product) continue;
            if ($product->store && $product->store === StoreEnum::LPO) continue;

            // Only restore if last adjustment for this order/product/type was 'out'
            $lastAdjustmentType = \App\Models\Stock::where('product_id', $productId)
                ->where('reference_id', $orderId)
                ->where('reference_type', Order::class)
                ->where('status', true)
                ->where(function($query) use ($type, $orderId) {
                    $query->where('name', 'like', "%({$type})%")
                          ->orWhere('notes', 'like', "%Product Status Approved ({$type})%")
                          ->orWhere('notes', 'like', "%Stock deducted for Order #{$orderId} - Product Status Approved ({$type})%");
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('adjustment_type');

            if ($lastAdjustmentType !== 'out') {
                continue;
            }

            try {
                // Restore product stock (general only)
                $this->stockService->adjustStock(
                    (int)$productId,
                    $qty,
                    'in',
                    null,
                    "Stock restored for Order #{$orderId} - Product Status Rejected ({$type}) (quantity: " . number_format($qty, 2) . ")",
                    $order,
                    "Order #{$orderId} - Stock Restored ({$type})"
                );

                // Restore BOM materials
                foreach ($product->materials as $material) {
                    $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);
                    if ($materialQtyPerUnit <= 0) continue;
                    $materialTotalQty = $materialQtyPerUnit * $qty;

                    $this->stockService->adjustMaterialStock(
                        (int)$material->id,
                        (int)$materialTotalQty,
                        'in',
                        null,
                        "Material stock restored for Order #{$orderId} - Product Status Rejected ({$type}) (product: {$product->product_name}, restored qty: " . number_format($qty, 2) . ", material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: " . number_format($materialTotalQty, 2) . ")",
                        $order,
                        "Order #{$orderId} - Material {$material->material_name} Restored ({$type})",
                        [
                            'product_id' => $product->id,
                            'product_quantity' => $qty,
                            'material_quantity_per_unit' => $materialQtyPerUnit,
                            'material_total_quantity' => $materialTotalQty,
                            'product_type' => $type,
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::error("OrderForm: Failed to restore stock for product {$productId} (type: {$type}) in order {$orderId}: " . $e->getMessage());
            }
        }
    }

    protected function deductStockForOrder($order, array $productsData, ?int $siteId = null): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        // Wrap in transaction to ensure atomicity - if one product fails, rollback all
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $productsData, $siteId) {
            foreach ($productsData as $productId => $productInfo) {
                // Reload product fresh from database without global scopes
                $product = Product::withoutGlobalScopes()->with('materials')->find($productId);
                
                // Skip if product not found
                if (!$product) {
                    Log::warning("OrderForm: Product {$productId} not found in order {$order->id}");
                    continue;
                }
                
                // Skip LPO products - check store field
                if ($product->store && $product->store === StoreEnum::LPO) {
                    continue;
                }

                $quantity = (float)($productInfo['quantity'] ?? 0);
                
                if ($quantity > 0) {
                    try {
                    // Determine if this is a product or material based on is_product flag
                    $isProduct = ($product->is_product ?? 1) > 0;
                    
                    // Check stock availability before deducting - use appropriate method
                    $generalStock = $isProduct 
                        ? $this->stockService->getCurrentProductStock((int)$productId, null)
                        : $this->stockService->getCurrentMaterialStock((int)$productId, null);
                    $siteStock = $siteId 
                        ? ($isProduct 
                            ? $this->stockService->getCurrentProductStock((int)$productId, $siteId)
                            : $this->stockService->getCurrentMaterialStock((int)$productId, $siteId))
                        : 0;
                    $totalAvailableStock = $generalStock + $siteStock;
                    
                    // Log stock check for debugging
                    Log::info("OrderForm::deductStockForOrder: Stock check for product {$productId} (is_product: {$isProduct}) in order {$order->id}. General: {$generalStock}, Site: {$siteStock}, Total: {$totalAvailableStock}, Requested: {$quantity}");
                    
                    // Ensure available_qty is synced before deduction
                    if ($isProduct && $product->available_qty != $generalStock) {
                        Log::info("OrderForm::deductStockForOrder: Syncing available_qty for product {$productId}. Current: {$product->available_qty}, Stock: {$generalStock}");
                        $product->update(['available_qty' => $generalStock]);
                        $product->refresh();
                    }
                    
                    if ($totalAvailableStock < $quantity) {
                        $productName = $product->product_name ?? "Product ID {$productId}";
                        Log::warning("OrderForm::deductStockForOrder: Insufficient stock for product {$productId} ({$productName}) in order {$order->id}. Available: {$totalAvailableStock}, Requested: {$quantity}");
                        throw new \Exception("Insufficient stock for {$productName}. Available: {$totalAvailableStock}, Requested: {$quantity}");
                    }
                    
                    // Check if stock was already deducted for this order/product combination
                    $orderId = $order->id;
                    $alreadyDeducted = \App\Models\Stock::where('product_id', $productId)
                        ->where('reference_id', $orderId)
                        ->where('reference_type', Order::class)
                        ->where('adjustment_type', 'out')
                        ->where('status', true)
                        ->where(function($query) use ($orderId) {
                            $query->where('notes', 'like', "%Stock deducted for Order #{$orderId}%")
                                  ->orWhere('name', 'like', "%Order #{$orderId}%");
                        })
                        ->exists();
                    
                    if ($alreadyDeducted) {
                        Log::info("OrderForm::deductStockForOrder: Stock already deducted for product {$productId} in order {$orderId}, skipping.");
                        continue;
                    }
                    
                    // 1) Deduct finished product stock - pass is_product flag explicitly
                    // Reload product one more time to ensure we have the latest is_product flag and available_qty
                    $productForDeduction = Product::withoutGlobalScopes()->find($productId);
                    if (!$productForDeduction) {
                        throw new \Exception("Product {$productId} not found");
                    }
                    $isProductForDeduction = ($productForDeduction->is_product ?? 1) > 0;
                    
                    // Final sync of available_qty before deduction to ensure adjustStock can use it as fallback
                    if ($isProductForDeduction && $productForDeduction->available_qty != $generalStock) {
                        Log::info("OrderForm::deductStockForOrder: Final sync of available_qty for product {$productId}. Current: {$productForDeduction->available_qty}, Stock: {$generalStock}");
                        $productForDeduction->update(['available_qty' => $generalStock]);
                        $productForDeduction->refresh();
                    }
                    
                    Log::info("OrderForm::deductStockForOrder: Deducting stock for product {$productId} ({$productForDeduction->product_name}, is_product: {$isProductForDeduction}, available_qty: {$productForDeduction->available_qty}) in order {$order->id}. Quantity: {$quantity}, Site ID: " . ($siteId ?? 'NULL') . ", Current Stock: {$generalStock}");
                    
                    $this->stockService->adjustStock(
                        (int)$productId,
                        (int)$quantity,
                        'out',
                        $siteId,
                        "Stock deducted for Order #{$order->id} (quantity: " . number_format($quantity, 2) . ")",
                        $order,
                        "Order #{$order->id} - Stock Deducted",
                        null,
                        $isProductForDeduction // Pass is_product flag explicitly
                    );

                    // 2) Deduct material stock based on product BOM
                    foreach ($product->materials as $material) {
                        $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                        if ($materialQtyPerUnit <= 0) {
                            continue;
                        }

                        $materialTotalQty = $materialQtyPerUnit * $quantity;
                        
                        // Check material stock availability before deducting
                        $materialGeneralStock = $this->stockService->getCurrentMaterialStock((int)$material->id, null);
                        $materialSiteStock = $siteId 
                            ? $this->stockService->getCurrentMaterialStock((int)$material->id, $siteId)
                            : 0;
                        $materialTotalAvailableStock = $materialGeneralStock + $materialSiteStock;
                        
                        Log::info("OrderForm::deductStockForOrder: Material stock check for material {$material->id} ({$material->material_name}) in order {$order->id}. General: {$materialGeneralStock}, Site: {$materialSiteStock}, Total: {$materialTotalAvailableStock}, Required: {$materialTotalQty}");
                        
                        if ($materialTotalAvailableStock < $materialTotalQty) {
                            $materialName = $material->material_name ?? "Material ID {$material->id}";
                            Log::warning("OrderForm::deductStockForOrder: Insufficient material stock for {$materialName} in order {$order->id}. Available: {$materialTotalAvailableStock}, Required: {$materialTotalQty}");
                            throw new \Exception("Insufficient material stock for {$materialName}. Available: {$materialTotalAvailableStock}, Required: {$materialTotalQty}");
                        }

                        $this->stockService->adjustMaterialStock(
                            (int)$material->id,
                            (int)$materialTotalQty,
                            'out',
                            $siteId,
                            "Material stock deducted for Order #{$order->id} (product: {$product->product_name}, ordered qty: " . number_format($quantity, 2) . ", material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: " . number_format($materialTotalQty, 2) . ")",
                            $order,
                            "Order #{$order->id} - Material {$material->material_name} Deducted",
                            [
                                'product_id' => $product->id,
                                'product_quantity' => $quantity,
                                'material_quantity_per_unit' => $materialQtyPerUnit,
                                'material_total_quantity' => $materialTotalQty,
                            ]
                        );
                        
                        Log::info("OrderForm::deductStockForOrder: Successfully deducted material stock for material {$material->id} ({$material->material_name}) in order {$order->id}");
                    }
                } catch (\Exception $e) {
                    Log::error("OrderForm::deductStockForOrder: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    $productName = $product->product_name ?? "Product ID {$productId}";
                    session()->flash('product_status_error', "Failed to deduct stock for {$productName}. " . $e->getMessage());
                        throw $e; // Re-throw to stop processing and rollback transaction
                    }
                }
            }
        });
    }

    /**
     * Restore stock for order products
     */
    protected function restoreStockForOrder($order, array $productsData, ?int $siteId = null): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        foreach ($productsData as $productId => $productInfo) {
            $product = Product::with('materials')->find($productId);

            if (!$product) {
                continue;
            }

            // Skip LPO products - check store field
            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }

            $quantity = (float)($productInfo['quantity'] ?? 0);
            
            if ($quantity > 0) {
                try {
                    // 1) Restore finished product stock
                    $this->stockService->adjustStock(
                        (int)$productId,
                        (int)$quantity,
                        'in',
                        $siteId,
                        "Stock restored for Order #{$order->id} (quantity: " . number_format($quantity, 2) . ")",
                        $order,
                        "Order #{$order->id} - Stock Restored"
                    );

                    // 2) Restore material stock based on product BOM
                    foreach ($product->materials as $material) {
                        $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                        if ($materialQtyPerUnit <= 0) {
                            continue;
                        }

                        $materialTotalQty = $materialQtyPerUnit * $quantity;

                        $this->stockService->adjustMaterialStock(
                            (int)$material->id,
                            (int)$materialTotalQty,
                            'in',
                            $siteId,
                            "Material stock restored for Order #{$order->id} (product: {$product->product_name}, restored qty: " . number_format($quantity, 2) . ", material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: " . number_format($materialTotalQty, 2) . ")",
                            $order,
                            "Order #{$order->id} - Material {$material->material_name} Restored",
                            [
                                'product_id' => $product->id,
                                'product_quantity' => $quantity,
                                'material_quantity_per_unit' => $materialQtyPerUnit,
                                'material_total_quantity' => $materialTotalQty,
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    Log::error("OrderForm: Failed to restore stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                }
            }
        }
    }
    protected function adjustStockForProductChanges($order, array $oldProductsData, array $newProductsData, ?int $siteId = null): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        $allProductIds = array_unique(array_merge(array_keys($oldProductsData), array_keys($newProductsData)));

        foreach ($allProductIds as $productId) {
            $product = Product::with('materials')->find($productId);

            if (!$product) {
                continue;
            }

            // Skip LPO products - check store field
            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }

            $oldQuantity = isset($oldProductsData[$productId]) ? (int)($oldProductsData[$productId]['quantity'] ?? 0) : 0;
            $newQuantity = isset($newProductsData[$productId]) ? (int)($newProductsData[$productId]['quantity'] ?? 0) : 0;
            $difference = $newQuantity - $oldQuantity;

            // Only adjust if there's a change
            if ($difference != 0) {
                if ($difference > 0) {
                    try {
                        // 1) Deduct additional finished product stock
                        $this->stockService->adjustStock(
                            (int)$productId,
                            $difference,
                            'out',
                            $siteId,
                            "Stock adjusted for Order #{$order->id} (quantity increased: -{$difference})",
                            $order,
                            "Order #{$order->id} - Stock Deducted"
                        );

                        // 2) Deduct additional material stock based on product BOM
                        foreach ($product->materials as $material) {
                            $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                            if ($materialQtyPerUnit <= 0) {
                                continue;
                            }

                            $materialTotalQty = $materialQtyPerUnit * $difference;

                            $this->stockService->adjustMaterialStock(
                                (int)$material->id,
                                (int)$materialTotalQty,
                                'out',
                                $siteId,
                                "Material stock adjusted for Order #{$order->id} (product: {$product->product_name}, qty increased: " . number_format($difference, 2) . ", material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: " . number_format($materialTotalQty, 2) . ")",
                                $order,
                                "Order #{$order->id} - Material {$material->material_name} Deducted",
                                [
                                    'product_id' => $product->id,
                                    'product_quantity_difference' => $difference,
                                    'material_quantity_per_unit' => $materialQtyPerUnit,
                                    'material_total_quantity' => $materialTotalQty,
                                ]
                            );
                        }
                } catch (\Exception $e) {
                    Log::error("OrderForm::deductStockForOrder: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    $productName = $product->product_name ?? "Product ID {$productId}";
                    session()->flash('product_status_error', "Failed to deduct stock for {$productName}. " . $e->getMessage());
                    throw $e; // Re-throw to stop processing and show error to user
                }
                } else {
                    $restoreAmount = abs($difference);
                    try {
                        // 1) Restore finished product stock
                        $this->stockService->adjustStock(
                            (int)$productId,
                            $restoreAmount,
                            'in',
                            $siteId,
                            "Stock adjusted for Order #{$order->id} (quantity decreased: +" . number_format($restoreAmount, 2) . ")",
                            $order,
                            "Order #{$order->id} - Stock Restored"
                        );

                        // 2) Restore material stock based on product BOM
                        foreach ($product->materials as $material) {
                            $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                            if ($materialQtyPerUnit <= 0) {
                                continue;
                            }

                            $materialTotalQty = $materialQtyPerUnit * $restoreAmount;

                            $this->stockService->adjustMaterialStock(
                                (int)$material->id,
                                (int)$materialTotalQty,
                                'in',
                                $siteId,
                                "Material stock adjusted for Order #{$order->id} (product: {$product->product_name}, qty decreased: " . number_format($restoreAmount, 2) . ", material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: " . number_format($materialTotalQty, 2) . ")",
                                $order,
                                "Order #{$order->id} - Material {$material->material_name} Restored",
                                [
                                    'product_id' => $product->id,
                                    'product_quantity_difference' => -$restoreAmount,
                                    'material_quantity_per_unit' => $materialQtyPerUnit,
                                    'material_total_quantity' => $materialTotalQty,
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        Log::error("OrderForm: Failed to restore stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    }
                }
            }
        }
    }

    /**
     * Save custom product images to order_custom_product_images table
     */
    private function saveCustomProductImages(int $customProductId, array $imagePaths): void
    {
        foreach ($imagePaths as $index => $imagePath) {
            if (!empty($imagePath)) {
                OrderCustomProductImage::create([
                    'order_custom_product_id' => $customProductId,
                    'image_path' => $imagePath,
                    'sort_order' => $index,
                ]);
            }
        }
    }

    /**
     * Get product type for grouping (hardware, workshop, lpo)
     */
    protected function getProductType(Product $product): string
    {
        if (!$product->store) {
            return 'hardware'; // Default to hardware if no store
        }
        
        if ($product->store === StoreEnum::LPO) {
            return 'lpo';
        } elseif ($product->store === StoreEnum::WarehouseStore) {
            return 'workshop';
        } else {
            return 'hardware';
        }
    }

    // Custom Product Edit Popup Methods
    public function openCustomProductModal(int $index): void
    {
        $this->normalizeProductsArray();
        
        if (!isset($this->orderProducts[$index]) || !($this->orderProducts[$index]['is_custom'] ?? 0)) {
            return;
        }
        
        $this->editingCustomProductIndex = $index;
        $this->showCustomProductModal = true;
        
        // Initialize popup products from existing product_ids
        $existingProductIds = $this->orderProducts[$index]['product_ids'] ?? [];
        $this->customProductPopupProducts = [];

        // Try to load overall quantity and per-product quantities from existing custom product details
        $overallQuantity = null;
        $perProductQuantities = [];
        $customProductId = $this->orderProducts[$index]['custom_product_id'] ?? null;
        if ($customProductId) {
            $existingCustomProduct = OrderCustomProduct::find($customProductId);
            if ($existingCustomProduct && is_array($existingCustomProduct->product_details)) {
                $details = $existingCustomProduct->product_details;
                if (isset($details['quantity']) && (int)$details['quantity'] > 0) {
                    $overallQuantity = (int)$details['quantity'];
                }
                // Load per-product quantities if they exist
                if (!empty($details['product_quantities']) && is_array($details['product_quantities'])) {
                    $perProductQuantities = $details['product_quantities'];
                }
            }
        }

        if (!empty($existingProductIds) && is_array($existingProductIds)) {
            $isSingleProduct = count($existingProductIds) === 1;
            foreach ($existingProductIds as $productId) {
                $product = Product::with('category')->find($productId);
                if ($product) {
                    // Default quantity logic:
                    // - If we have saved per-product quantities, use them.
                    // - Else if there is a single connected product and an overall quantity, use that.
                    // - Otherwise default to 1.
                    if (!empty($perProductQuantities) && array_key_exists($productId, $perProductQuantities)) {
                        $quantity = (int) $perProductQuantities[$productId];
                    } elseif ($isSingleProduct && $overallQuantity !== null) {
                        $quantity = $overallQuantity;
                    } else {
                        $quantity = 1;
                    }
                    $this->customProductPopupProducts[] = [
                        'id' => $product->id,
                        'name' => $product->product_name,
                        'category' => $product->category->name ?? '',
                        'unit' => $product->unit_type ?? '',
                        'quantity' => $quantity,
                    ];
                }
            }
        }
        
        // Load materials from existing order custom product if it exists
        $this->customProductPopupMaterials = [];
        
        // If custom product ID exists, load materials from it
        if ($customProductId) {
            $customProduct = OrderCustomProduct::find($customProductId);
            if ($customProduct && $customProduct->product_details) {
                $productDetails = $customProduct->product_details;
                $materials = $productDetails['materials'] ?? [];
                
                if (!empty($materials) && is_array($materials)) {
                    foreach ($materials as $materialIndex => $material) {
                        $materialId = $material['material_id'] ?? null;
                        if ($materialId) {
                            $materialProduct = Product::with('category')->find($materialId);
                            if ($materialProduct) {
                                $measurements = $material['measurements'] ?? [1];
                                // Ensure measurements is an array
                                if (!is_array($measurements)) {
                                    $measurements = [1];
                                }
                                // Ensure at least one measurement exists
                                if (empty($measurements)) {
                                    $measurements = [1];
                                }
                                
                                $this->customProductPopupMaterials[] = [
                                    'material_id' => $materialId,
                                    'name' => $materialProduct->product_name,
                                    'category' => $materialProduct->category->name ?? '',
                                    'unit' => $materialProduct->unit_type ?? '',
                                    'actual_pcs' => max(1, (int)($material['actual_pcs'] ?? 1)),
                                    'measurements' => $measurements,
                                    'calculated_quantity' => 0, // Will be recalculated below
                                ];
                                
                                // Recalculate quantity for this material
                                $lastIndex = count($this->customProductPopupMaterials) - 1;
                                $this->recalculateMaterialQuantity($lastIndex);
                            }
                        }
                    }
                }
            }
        } elseif ($this->isEditMode && $this->editingId) {
            // Fallback: Try to load by index if custom_product_id not available
            $order = Order::find($this->editingId);
            if ($order) {
                $customProducts = OrderCustomProduct::where('order_id', $order->id)->get();
                if ($customProducts->count() > $index) {
                    $customProduct = $customProducts[$index];
                    if ($customProduct && $customProduct->product_details) {
                        $productDetails = $customProduct->product_details;
                        $materials = $productDetails['materials'] ?? [];
                        
                        if (!empty($materials) && is_array($materials)) {
                            foreach ($materials as $materialIndex => $material) {
                                $materialId = $material['material_id'] ?? null;
                                if ($materialId) {
                                    $materialProduct = Product::with('category')->find($materialId);
                                    if ($materialProduct) {
                                        $measurements = $material['measurements'] ?? [1];
                                        // Ensure measurements is an array
                                        if (!is_array($measurements)) {
                                            $measurements = [1];
                                        }
                                        // Ensure at least one measurement exists
                                        if (empty($measurements)) {
                                            $measurements = [1];
                                        }
                                        
                                        $this->customProductPopupMaterials[] = [
                                            'material_id' => $materialId,
                                            'name' => $materialProduct->product_name,
                                            'category' => $materialProduct->category->name ?? '',
                                            'unit' => $materialProduct->unit_type ?? '',
                                            'actual_pcs' => max(1, (int)($material['actual_pcs'] ?? 1)),
                                            'measurements' => $measurements,
                                            'calculated_quantity' => 0, // Will be recalculated below
                                        ];
                                        
                                        // Recalculate quantity for this material
                                        $lastIndex = count($this->customProductPopupMaterials) - 1;
                                        $this->recalculateMaterialQuantity($lastIndex);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // If no materials exist, load connected materials from selected products
        if (empty($this->customProductPopupMaterials) && !empty($this->customProductPopupProducts)) {
            $this->loadConnectedMaterialsForProducts();
        }
        
        // Recalculate all materials after loading
        foreach ($this->customProductPopupMaterials as $index => $material) {
            $this->recalculateMaterialQuantity($index);
        }
        
        // Reset popup search
        $this->customProductPopupSearchTerm = '';
        $this->customProductPopupResults = [];
        $this->customProductPopupDropdownOpen = false;
        $this->customProductPopupPage = 1;
        $this->customProductPopupHasMore = false;
    }

    public function closeCustomProductModal(): void
    {
        $this->showCustomProductModal = false;
        $this->editingCustomProductIndex = null;
        $this->customProductPopupProducts = [];
        $this->customProductPopupMaterials = [];
        $this->customProductPopupSearchTerm = '';
        $this->customProductPopupResults = [];
        $this->customProductPopupDropdownOpen = false;
    }
    
    /**
     * Load connected materials for selected products
     */
    protected function loadConnectedMaterialsForProducts(): void
    {
        if (empty($this->customProductPopupProducts)) {
            return;
        }
        
        $productIds = array_column($this->customProductPopupProducts, 'id');
        if (empty($productIds)) {
            return;
        }
        
        // Get all materials connected to these products
        $materials = DB::table('product_materials')
            ->whereIn('product_id', $productIds)
            ->join('products', 'product_materials.material_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id', 'left')
            ->whereIn('products.is_product', [0, 2])
            ->where('products.status', true)
            ->select('products.id as material_id', 'products.product_name as name', 'categories.name as category', 'products.unit_type as unit')
            ->distinct()
            ->get();
        
        $addedMaterialIds = [];
        foreach ($materials as $material) {
            // Avoid duplicates
            if (in_array($material->material_id, $addedMaterialIds)) {
                continue;
            }
            
            $this->customProductPopupMaterials[] = [
                'material_id' => $material->material_id,
                'name' => $material->name,
                'category' => $material->category ?? '',
                'unit' => $material->unit ?? '',
                'actual_pcs' => 1,
                'measurements' => [1], // Default measurement value of 1
                'calculated_quantity' => 0,
            ];
            
            $addedMaterialIds[] = $material->material_id;
            
            // Recalculate quantity for newly added material
            $lastIndex = count($this->customProductPopupMaterials) - 1;
            $this->recalculateMaterialQuantity($lastIndex);
        }
    }
    
    /**
     * Add a new material to custom product popup
     */
    public function addMaterialToCustomPopup(int $materialId): void
    {
        $material = Product::with('category')->find($materialId);
        if (!$material) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Material not found.']);
            return;
        }
        
        // Check if already added
        foreach ($this->customProductPopupMaterials as $item) {
            if (isset($item['material_id']) && $item['material_id'] == $materialId) {
                $this->dispatch('show-toast', ['type' => 'info', 'message' => 'Material already added.']);
                return;
            }
        }
        
        $this->customProductPopupMaterials[] = [
            'material_id' => $material->id,
            'name' => $material->product_name,
            'category' => $material->category->name ?? '',
            'unit' => $material->unit_type ?? '',
            'actual_pcs' => 1,
            'measurements' => [1], // Default measurement value of 1
            'calculated_quantity' => 0,
        ];
        
        // Recalculate quantity for newly added material
        $lastIndex = count($this->customProductPopupMaterials) - 1;
        $this->recalculateMaterialQuantity($lastIndex);
    }
    
    /**
     * Remove material from custom product popup
     */
    public function removeMaterialFromCustomPopup(int $index): void
    {
        if (isset($this->customProductPopupMaterials[$index])) {
            unset($this->customProductPopupMaterials[$index]);
            $this->customProductPopupMaterials = array_values($this->customProductPopupMaterials);
        }
    }
    
    /**
     * Update material actual_pcs in custom product popup
     */
    public function updateCustomPopupMaterialPcs(int $index, $pcs): void
    {
        if (isset($this->customProductPopupMaterials[$index])) {
            $numericPcs = is_numeric($pcs) ? max(1, (int)$pcs) : 1;
            $this->customProductPopupMaterials[$index]['actual_pcs'] = $numericPcs;
            $this->recalculateMaterialQuantity($index);
        }
    }
    
    /**
     * Update actual_pcs via wire:model.live
     */
    public function updatedCustomProductPopupMaterialsActualPcs($value, $key): void
    {
        // Extract material index from key (e.g., "0.actual_pcs" -> 0)
        $parts = explode('.', $key);
        if (isset($parts[0]) && is_numeric($parts[0])) {
            $materialIndex = (int)$parts[0];
            $this->recalculateMaterialQuantity($materialIndex);
        }
    }
    
    /**
     * Add measurement field to material
     */
    public function addMaterialMeasurement(int $materialIndex): void
    {
        if (isset($this->customProductPopupMaterials[$materialIndex])) {
            if (!isset($this->customProductPopupMaterials[$materialIndex]['measurements'])) {
                $this->customProductPopupMaterials[$materialIndex]['measurements'] = [];
            }
            $this->customProductPopupMaterials[$materialIndex]['measurements'][] = 1; // Default value 1
            $this->recalculateMaterialQuantity($materialIndex);
        }
    }
    
    /**
     * Update measurement value at specific index
     */
    public function updateMaterialMeasurement(int $materialIndex, int $measurementIndex, $value): void
    {
        if (isset($this->customProductPopupMaterials[$materialIndex]['measurements'][$measurementIndex])) {
            // Ensure value is numeric and non-negative
            $numericValue = is_numeric($value) ? max(0, (float)$value) : 0;
            $this->customProductPopupMaterials[$materialIndex]['measurements'][$measurementIndex] = $numericValue;
            $this->recalculateMaterialQuantity($materialIndex);
        }
    }
    
    /**
     * Update measurement via wire:model.live
     */
    public function updatedCustomProductPopupMaterialsMeasurements($value, $key): void
    {
        // Extract material index from key (e.g., "0.measurements.0" -> 0)
        $parts = explode('.', $key);
        if (isset($parts[0]) && is_numeric($parts[0])) {
            $materialIndex = (int)$parts[0];
            $this->recalculateMaterialQuantity($materialIndex);
        }
    }
    
    /**
     * Remove measurement from material
     */
    public function removeMaterialMeasurement(int $materialIndex, int $measurementIndex): void
    {
        if (isset($this->customProductPopupMaterials[$materialIndex]['measurements'][$measurementIndex])) {
            unset($this->customProductPopupMaterials[$materialIndex]['measurements'][$measurementIndex]);
            $this->customProductPopupMaterials[$materialIndex]['measurements'] = array_values(
                $this->customProductPopupMaterials[$materialIndex]['measurements']
            );
            
            // Ensure at least one measurement field exists
            if (empty($this->customProductPopupMaterials[$materialIndex]['measurements'])) {
                $this->customProductPopupMaterials[$materialIndex]['measurements'] = [1];
            }
            
            $this->recalculateMaterialQuantity($materialIndex);
        }
    }
    
    /**
     * Recalculate material quantity based on measurements and actual_pcs
     * Formula: QTY = (m1 + m2 + ... + m5) × Pcs
     * Where:
     * - m1, m2, ... m5 are measurement values
     * - Pcs is the actual_pcs (pieces count)
     * 
     * This method can be called directly from the view (public)
     */
    public function recalculateMaterialQuantity(int $index): void
    {
        if (!isset($this->customProductPopupMaterials[$index])) {
            return;
        }
        
        // Get fresh material data
        $material = $this->customProductPopupMaterials[$index];
        $measurements = $material['measurements'] ?? [];
        $actualPcs = max(1, (int)($material['actual_pcs'] ?? 1)); // Ensure minimum of 1
        
        // Ensure measurements is an array
        if (!is_array($measurements)) {
            $measurements = [1];
            $this->customProductPopupMaterials[$index]['measurements'] = $measurements;
        }
        
        // Ensure at least one measurement exists
        if (empty($measurements)) {
            $measurements = [1];
            $this->customProductPopupMaterials[$index]['measurements'] = $measurements;
        }
        
        // Filter out empty, null, or non-numeric values and ensure numeric
        $numericMeasurements = [];
        foreach ($measurements as $m) {
            if ($m !== null && $m !== '' && is_numeric($m)) {
                $numericMeasurements[] = (float)$m;
            }
        }
        
        if (!empty($numericMeasurements)) {
            // Sum all measurements: m1 + m2 + ... + m5
            $sumOfMeasurements = array_sum($numericMeasurements);
            
            // Calculate quantity: (sum of measurements) × pieces
            $calculatedQty = $sumOfMeasurements * $actualPcs;
            
            $this->customProductPopupMaterials[$index]['calculated_quantity'] = round($calculatedQty, 2);
        } else {
            // If no valid measurements, set to default and recalculate
            $this->customProductPopupMaterials[$index]['measurements'] = [1];
            $this->customProductPopupMaterials[$index]['calculated_quantity'] = round(1 * $actualPcs, 2);
        }
    }
    
    /**
     * Livewire hook - called when customProductPopupMaterials is updated via wire:model
     * This is a fallback for any other property changes
     */
    public function updatedCustomProductPopupMaterials($value, $key): void
    {
        // Extract material index from key (e.g., "0.measurements.0" -> 0, "0.actual_pcs" -> 0)
        $parts = explode('.', $key);
        if (isset($parts[0]) && is_numeric($parts[0])) {
            $materialIndex = (int)$parts[0];
            $this->recalculateMaterialQuantity($materialIndex);
        }
    }
    
    /**
     * Manual update button - recalculate all materials
     */
    public function recalculateAllMaterials(): void
    {
        foreach ($this->customProductPopupMaterials as $index => $material) {
            $this->recalculateMaterialQuantity($index);
        }
        $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Quantities updated successfully.']);
    }
    
    /**
     * Get available materials for selection (not already added)
     */
    public function getAvailableMaterialsForCustomPopup(): array
    {
        if (empty($this->customProductPopupProducts)) {
            return [];
        }
        
        $productIds = array_column($this->customProductPopupProducts, 'id');
        $addedMaterialIds = array_column($this->customProductPopupMaterials, 'material_id');
        
        $materials = DB::table('product_materials')
            ->whereIn('product_id', $productIds)
            ->join('products', 'product_materials.material_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id', 'left')
            ->whereIn('products.is_product', [0, 2])
            ->where('products.status', true)
            ->whereNotIn('products.id', $addedMaterialIds)
            ->select('products.id as material_id', 'products.product_name as name', 'categories.name as category', 'products.unit_type as unit')
            ->distinct()
            ->orderBy('products.product_name')
            ->get()
            ->map(function ($material) {
                return [
                    'id' => $material->material_id,
                    'text' => $material->name,
                    'category' => $material->category ?? '',
                    'unit' => $material->unit ?? '',
                ];
            })
            ->toArray();
        
        return $materials;
    }
    
    /**
     * Prepare rejection details for display in modal
     */
    protected function prepareRejectionDetails($order): void
    {
        $this->rejectionDetailsProductStatuses = [];
        $this->productRejectionNotes = [];
        $productStatus = $order->product_status ?? [];
        $productRejectionNotes = $order->product_rejection_notes ?? [];
        
        if (!empty($productStatus)) {
            $statusLabels = [
                'hardware' => 'Hardware',
                'Workshop' => 'Workshop',
                'lpo' => 'LPO',
                'custom' => 'Custom',
            ];
            
            foreach ($productStatus as $type => $status) {
                if ($status === 'rejected' && isset($statusLabels[$type])) {
                    $this->rejectionDetailsProductStatuses[$type] = $statusLabels[$type];
                    // Load rejection note for this product type
                    $this->productRejectionNotes[$type] = $order->getProductRejectionNote($type) ?? '';
                }
            }
            
            // Handle LPO supplier-wise statuses
            if (isset($productStatus['lpo']) && is_array($productStatus['lpo'])) {
                if (!isset($this->productRejectionNotes['lpo']) || !is_array($this->productRejectionNotes['lpo'])) {
                    $this->productRejectionNotes['lpo'] = [];
                }
                foreach ($productStatus['lpo'] as $supplierId => $lpoStatus) {
                    if ($lpoStatus === 'rejected') {
                        $supplier = \App\Models\Supplier::find($supplierId);
                        $supplierName = $supplier ? $supplier->name : "Supplier #{$supplierId}";
                        $this->rejectionDetailsProductStatuses['lpo_' . $supplierId] = "LPO ({$supplierName})";
                        // Load rejection note for this LPO supplier, initialize to empty string if not set
                        $existingNote = $order->getProductRejectionNote('lpo', (int)$supplierId);
                        $this->productRejectionNotes['lpo'][$supplierId] = $existingNote ?? '';
                    }
                }
            }
        }
        
        // Also load general rejected_note for backward compatibility
        if (!$this->rejected_note) {
            $this->rejected_note = $order->rejected_note ?? '';
        }
    }
    
    /**
     * Open rejection details modal
     */
    public function openRejectionDetailsModal(): void
    {
        if (!$this->isEditMode || !$this->editingId) {
            return;
        }
        
        try {
            $order = Order::with(['products', 'customProducts'])->find($this->editingId);
            if (!$order) {
                return;
            }
            
            // Check if order is rejected - compare both enum and string value
            $isRejected = false;
            if ($order->status instanceof OrderStatusEnum) {
                $isRejected = ($order->status === OrderStatusEnum::Rejected);
            } else {
                $statusValue = $order->status ?? $this->status;
                $isRejected = ($statusValue === OrderStatusEnum::Rejected->value || $statusValue === 'rejected');
            }
            
            // Also check if any product type is rejected
            $hasRejectedProduct = false;
            $productStatus = $order->product_status ?? [];
            if (!empty($productStatus)) {
                foreach ($productStatus as $type => $status) {
                    if ($status === 'rejected') {
                        $hasRejectedProduct = true;
                        break;
                    }
                    // Check LPO supplier-wise statuses
                    if ($type === 'lpo' && is_array($status)) {
                        foreach ($status as $supplierStatus) {
                            if ($supplierStatus === 'rejected') {
                                $hasRejectedProduct = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            // Open modal if order is rejected or has rejected products
            if ($isRejected || $hasRejectedProduct) {
                $this->prepareRejectionDetails($order);
                $this->showRejectionDetailsModal = true;
            } elseif ($order->rejected_note || $this->rejected_note) {
                // If no rejection found but rejected_note exists, still show modal
                $this->prepareRejectionDetails($order);
                $this->showRejectionDetailsModal = true;
            }
        } catch (\Exception $e) {
            Log::error("Error opening rejection details modal: " . $e->getMessage());
            // Still try to show modal if we have rejection data
            if ($this->rejected_note || !empty($this->rejectionDetailsProductStatuses)) {
                $this->prepareRejectionDetails($order ?? Order::find($this->editingId));
                $this->showRejectionDetailsModal = true;
            }
        }
    }
    
    /**
     * Close rejection details modal
     */
    public function closeRejectionDetailsModal(): void
    {
        // If we were in a "pending rejection" flow (dropdown selected rejected but user didn't Save),
        // revert the dropdown back to the previous status.
        if ($this->pendingRejectionType && $this->pendingRejectionPreviousStatus) {
            $type = $this->pendingRejectionType;
            $previous = $this->pendingRejectionPreviousStatus;

            $this->productStatuses[$type] = $previous;
            $this->dispatch('revert-product-status-select', type: $type, status: $previous);
        }

        $this->pendingRejectionType = null;
        $this->pendingRejectionPreviousStatus = null;
        $this->showRejectionDetailsModal = false;
        $this->rejectionDetailsProductStatuses = [];
        $this->productRejectionNotes = [];
        $this->currentRejectionType = null;
    }
    
    /**
     * Save rejection details to database (per product type)
     */
    public function saveRejectionDetails(): void
    {
        if (!$this->isEditMode || !$this->editingId) {
            session()->flash('rejection_error', 'Cannot save rejection details. Order not found.');
            return;
        }
        
        try {
            $order = Order::findOrFail($this->editingId);
            $productStatus = $order->product_status ?? [];
            $hasErrors = false;
            
            // Validate and save rejection notes for each rejected product type
            foreach ($this->rejectionDetailsProductStatuses as $typeKey => $label) {
                // Determine the actual type and supplier ID (for LPO)
                $type = $typeKey;
                $supplierId = null;
                
                if (str_starts_with($typeKey, 'lpo_')) {
                    // LPO supplier-wise: extract supplier ID
                    $type = 'lpo';
                    $supplierId = (int)str_replace('lpo_', '', $typeKey);
                }
                
                // Get rejection note for this product type
                $rejectionNote = '';
                if ($type === 'lpo' && $supplierId !== null) {
                    $rejectionNote = $this->productRejectionNotes['lpo'][$supplierId] ?? '';
                } else {
                    $rejectionNote = $this->productRejectionNotes[$type] ?? '';
                }
                
                // Validate rejection note is provided for rejected products
                if (empty($rejectionNote) || trim($rejectionNote) === '') {
                    $errorKey = $type === 'lpo' && $supplierId !== null 
                        ? "productRejectionNotes.lpo.{$supplierId}" 
                        : "productRejectionNotes.{$type}";
                    $this->addError($errorKey, "Rejection reason is required for {$label}.");
                    $hasErrors = true;
                    continue;
                }
                
                // Save rejection note for this product type
                $order->setProductRejectionNote($type, trim($rejectionNote), $supplierId);
            }
            
            if ($hasErrors) {
                return;
            }
            
            // If this modal was opened from selecting "rejected" in the dropdown, persist the rejection status now.
            // We suppress the auto-open behavior inside saveProductStatusUpdate() to avoid reopening this same modal.
            if ($this->pendingRejectionType) {
                $pendingType = $this->pendingRejectionType;
                $this->suppressAutoOpenRejectionModal = true;
                try {
                    $this->productStatuses[$pendingType] = 'rejected';
                    $this->saveProductStatusUpdate($pendingType, 'rejected');
                } finally {
                    $this->suppressAutoOpenRejectionModal = false;
                }

                // Ensure the dropdown reflects the saved status
                $this->dispatch('update-product-status-select', type: $pendingType, status: 'rejected');
            }

            // Save all rejection notes at once
            $order->save();
            
            // Also update general rejected_note for backward compatibility (use first rejection note if available)
            if (!empty($this->productRejectionNotes)) {
                $firstNote = '';
                foreach ($this->productRejectionNotes as $type => $note) {
                    if ($type === 'lpo' && is_array($note)) {
                        $firstNote = reset($note) ?: '';
                    } else {
                        $firstNote = $note ?: '';
                    }
                    if (!empty($firstNote)) {
                        break;
                    }
                }
                if (!empty($firstNote)) {
                    $order->update(['rejected_note' => $firstNote]);
                }
            }
            
            // Refresh to get latest data
            $order->refresh();
            
            // Reload rejection details
            $this->prepareRejectionDetails($order);
            
            // Clear pending rejection flow and close the modal after successful save
            $this->pendingRejectionType = null;
            $this->pendingRejectionPreviousStatus = null;
            $this->showRejectionDetailsModal = false;
            
            session()->flash('rejection_success', 'Rejection details saved successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to save rejection details', [
                'order_id' => $this->editingId,
                'error' => $e->getMessage()
            ]);
            
            session()->flash('rejection_error', 'Failed to save rejection details. Please try again.');
        }
    }

    public function searchCustomProductPopup(string $search = '', int $page = 1): void
    {
        if ($this->customProductPopupLoading) {
            return;
        }
        
        // Open dropdown if searching
        if (!$this->customProductPopupDropdownOpen && !empty(trim($search))) {
            $this->customProductPopupDropdownOpen = true;
        }
        
        if (!$this->customProductPopupDropdownOpen && $page !== 1) {
            return;
        }
        
        $this->customProductPopupLoading = true;
        $this->customProductPopupSearchTerm = $search;
        $this->customProductPopupPage = $page;
        
        try {
            $perPage = 15;
            
            // Always show ALL warehouse products for custom product popup
            // Users can select any warehouse product to connect to the custom product
            $query = Product::where('status', true)
                ->whereIn('is_product', [1, 2])
                ->where('store', StoreEnum::WarehouseStore)
                ->with('category');
            
            // In edit mode, also include products already in product_ids so they can be re-selected if removed
            if ($this->isEditMode && $this->editingId) {
                // Get product_ids from the custom product being edited
                $customProductIds = [];
                if ($this->editingCustomProductIndex !== null && isset($this->orderProducts[$this->editingCustomProductIndex])) {
                    $existingIds = $this->orderProducts[$this->editingCustomProductIndex]['product_ids'] ?? [];
                    if (is_array($existingIds)) {
                        $customProductIds = array_map('intval', array_filter($existingIds));
                    }
                }
                
                // Also get warehouse products from the order to show available options
                $order = Order::find($this->editingId);
                if ($order) {
                    $orderWarehouseProducts = DB::table('order_products')
                        ->join('products', 'order_products.product_id', '=', 'products.id')
                        ->where('order_products.order_id', $order->id)
                        ->where('products.store', StoreEnum::WarehouseStore->value)
                        ->pluck('order_products.product_id')
                        ->toArray();
                    $orderWarehouseProductIds = array_map('intval', $orderWarehouseProducts);
                    
                    // Combine: warehouse products from order + products from product_ids
                    $availableProductIds = array_unique(array_merge($orderWarehouseProductIds, $customProductIds));
                    
                    // If we have specific products to show, filter by them
                    // Otherwise show all warehouse products
                    if (!empty($availableProductIds)) {
                        // Show products from order + product_ids, but don't restrict to only these
                        // Instead, we'll show all warehouse products and let user select
                    }
                }
            }
            // In create mode or if no filter needed, show all warehouse products
            
            // Exclude already selected products in popup
            $selectedIds = array_column($this->customProductPopupProducts, 'id');
            if (!empty($selectedIds)) {
                $query->whereNotIn('id', $selectedIds);
            }
            
            if (!empty($search) && trim($search) !== '') {
                $searchTerm = trim($search);
                $query->where('product_name', 'like', '%' . $searchTerm . '%');
            }
            
            $total = $query->count();
            $products = $query->orderByRaw('is_product DESC, product_name ASC')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $hasMore = ($page * $perPage) < $total;
            $this->customProductPopupHasMore = $hasMore;
            
            $results = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'text' => $product->product_name,
                    'category_name' => $product->category->name ?? '',
                    'unit_type' => $product->unit_type ?? '',
                    'image_url' => $product->first_image_url ?? null,
                ];
            })->toArray();
            
            if ($page === 1) {
                $this->customProductPopupResults = $results;
            } else {
                $this->customProductPopupResults = array_merge($this->customProductPopupResults ?? [], $results);
            }
        } catch (\Exception $e) {
            Log::error('Custom product popup search error: ' . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Error searching products. Please try again.']);
            $this->customProductPopupResults = [];
            $this->customProductPopupHasMore = false;
        } finally {
            $this->customProductPopupLoading = false;
        }
    }

    public function toggleCustomProductPopupDropdown(): void
    {
        $wasOpen = $this->customProductPopupDropdownOpen;
        $this->customProductPopupDropdownOpen = !$wasOpen;
        
        if ($this->customProductPopupDropdownOpen && !$wasOpen) {
            // Open dropdown and load products immediately
            $this->customProductPopupSearchTerm = '';
            $this->customProductPopupPage = 1;
            $this->customProductPopupResults = [];
            $this->customProductPopupHasMore = false;
            $this->customProductPopupLoading = false;
            // Load products
            $this->searchCustomProductPopup('', 1);
        }
    }

    public function loadMoreCustomProductPopup(): void
    {
        if ($this->customProductPopupLoading) {
            return;
        }
        
        if (!$this->customProductPopupDropdownOpen) {
            return;
        }
        
        if (!$this->customProductPopupHasMore) {
            return;
        }
        
        $nextPage = $this->customProductPopupPage + 1;
        $search = $this->customProductPopupSearchTerm ?? '';
        $this->searchCustomProductPopup($search, $nextPage);
    }

    public function updatedCustomProductPopupSearchTerm($value): void
    {
        // Ensure dropdown is open when typing
        if (!$this->customProductPopupDropdownOpen && !empty(trim($value ?? ''))) {
            $this->customProductPopupDropdownOpen = true;
        }
        
        $this->customProductPopupPage = 1;
        $this->customProductPopupResults = [];
        
        if ($this->customProductPopupDropdownOpen) {
            $this->searchCustomProductPopup($value ?? '', 1);
        }
    }

    public function selectProductInCustomPopup(int $productId): void
    {
        $product = Product::with('category')->find($productId);
        if (!$product) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Product not found.']);
            return;
        }
        
        // Check if already selected
        foreach ($this->customProductPopupProducts as $item) {
            if (isset($item['id']) && $item['id'] == $productId) {
                $this->dispatch('show-toast', ['type' => 'info', 'message' => 'Product already selected.']);
                return; // Already selected
            }
        }
        
        $this->customProductPopupProducts[] = [
            'id' => $product->id,
            'name' => $product->product_name,
            'category' => $product->category->name ?? 'N/A',
            'unit' => $product->unit_type ?? 'N/A',
            'quantity' => 1,
        ];
        
        // Close dropdown after selection
        $this->customProductPopupDropdownOpen = false;
        $this->customProductPopupSearchTerm = '';
        $this->customProductPopupPage = 1;
        $this->customProductPopupResults = [];
        
        // Reload materials when product is added (only if materials not already loaded)
        if (empty($this->customProductPopupMaterials)) {
            $this->loadConnectedMaterialsForProducts();
        }
    }

    public function removeProductFromCustomPopup(int $index): void
    {
        if (isset($this->customProductPopupProducts[$index])) {
            unset($this->customProductPopupProducts[$index]);
            $this->customProductPopupProducts = array_values($this->customProductPopupProducts);
            
            // Refresh search results to show the removed product again in dropdown
            if ($this->customProductPopupDropdownOpen) {
                $this->searchCustomProductPopup($this->customProductPopupSearchTerm ?? '', 1);
            }
        }
    }

    public function updateCustomPopupProductQuantity(int $index, int $quantity): void
    {
        if (isset($this->customProductPopupProducts[$index])) {
            $this->customProductPopupProducts[$index]['quantity'] = max(1, (int)$quantity);
        }
    }

    public function saveCustomProductFromPopup(): void
    {
        if ($this->editingCustomProductIndex === null) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'No custom product selected for editing.']);
            return;
        }
        
        $this->normalizeProductsArray();
        
        if (!isset($this->orderProducts[$this->editingCustomProductIndex])) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Custom product not found.']);
            return;
        }
        
        // Extract product_ids from popup products
        $productIds = array_column($this->customProductPopupProducts, 'id');
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn($id) => $id > 0)));
        
        // Update the custom product with product_ids in orderProducts array
        $this->orderProducts[$this->editingCustomProductIndex]['product_ids'] = $productIds;
        
        // Always save to database immediately if custom product exists (either in edit mode or if custom_product_id exists)
        $saveSuccessful = false;
        $customProductId = $this->orderProducts[$this->editingCustomProductIndex]['custom_product_id'] ?? null;
        
        // Save if we have a custom product ID (whether from edit mode or from site manager created custom product)
        if ($customProductId) {
                try {
                    DB::beginTransaction();
                    
                    $customProduct = OrderCustomProduct::find($customProductId);
                    if (!$customProduct) {
                        throw new \Exception('Custom product not found with ID: ' . $customProductId);
                    }
                    
                    // Ensure product_ids is properly formatted
                    $productIdsToSave = $productIds; // Already formatted above
                    
                    // Log before save
                    \Illuminate\Support\Facades\Log::info('OrderForm: Attempting to save product_ids', [
                        'custom_product_id' => $customProductId,
                        'product_ids_to_save' => $productIdsToSave,
                        'before_save_product_ids' => $customProduct->product_ids,
                    ]);

                    // Capture old quantity before changes (used for stock adjustments on quantity change)
                    $productDetails = $customProduct->product_details ?? [];
                    $oldQuantity = (int)($productDetails['quantity'] ?? 0);
                    $newQuantity = $oldQuantity;
                    
                    // Prepare materials data for product_details
                    $materialsData = [];
                    if (!empty($this->customProductPopupMaterials)) {
                        foreach ($this->customProductPopupMaterials as $material) {
                            $materialId = $material['material_id'] ?? null;
                            if (!$materialId) {
                                continue; // Skip materials without ID
                            }
                            
                            $materialData = [
                                'material_id' => $materialId,
                                'actual_pcs' => (int)($material['actual_pcs'] ?? 1),
                            ];
                            
                            // Add measurements if provided
                            $measurements = $material['measurements'] ?? [];
                            if (!empty($measurements) && is_array($measurements)) {
                                // Filter out empty/null measurements
                                $measurements = array_values(array_filter($measurements, function($m) {
                                    return $m !== null && $m !== '' && is_numeric($m);
                                }));
                                if (!empty($measurements)) {
                                    $materialData['measurements'] = $measurements;
                                }
                            }
                            
                            // Calculate quantity using formula: QTY = (m1 + m2 + ... + m5) × Pcs
                            if (!empty($materialData['measurements'])) {
                                // Filter out non-numeric values and sum all measurements
                                $numericMeasurements = array_filter($materialData['measurements'], function($m) {
                                    return is_numeric($m) && $m >= 0;
                                });
                                $sumOfMeasurements = !empty($numericMeasurements) ? array_sum($numericMeasurements) : 0;
                                // Calculate: (sum of measurements) × pieces
                                $calculatedQty = $sumOfMeasurements * $materialData['actual_pcs'];
                                $materialData['calculated_quantity'] = round($calculatedQty, 2);
                                $materialData['cal_qty'] = round($calculatedQty, 2);
                            } else {
                                $materialData['calculated_quantity'] = 0;
                                $materialData['cal_qty'] = 0;
                            }
                            
                            $materialsData[] = $materialData;
                        }
                    }
                    
                    // Update product_details with materials (reusing $productDetails from above)
                    if (!empty($materialsData)) {
                        $productDetails['materials'] = $materialsData;
                        // Recalculate total quantity from materials
                        $totalQuantity = array_sum(array_column($materialsData, 'calculated_quantity'));
                        $productDetails['quantity'] = $totalQuantity;
                        $newQuantity = (int)$totalQuantity;
                    } else {
                        // No materials configured in popup.
                        // In this case, derive quantity from connected products popup quantities
                        // and also persist per-product quantities so that the popup and
                        // order edit view stay in sync.
                        unset($productDetails['materials']);

                        $totalQuantityFromProducts = 0;
                        $perProductQuantities = [];
                        if (!empty($this->customProductPopupProducts) && is_array($this->customProductPopupProducts)) {
                            foreach ($this->customProductPopupProducts as $popupProduct) {
                                $productId = isset($popupProduct['id']) ? (int) $popupProduct['id'] : null;
                                $qty = isset($popupProduct['quantity']) ? (int) $popupProduct['quantity'] : 0;

                                if ($productId && $qty > 0) {
                                    $perProductQuantities[$productId] = $qty;
                                    $totalQuantityFromProducts += $qty;
                                }
                            }
                        }

                        if ($totalQuantityFromProducts > 0) {
                            $productDetails['quantity'] = $totalQuantityFromProducts;
                            $productDetails['product_quantities'] = $perProductQuantities;
                            $newQuantity = (int)$totalQuantityFromProducts;
                        } else {
                            // If nothing else is set, clear per-product quantities and default total to 0
                            unset($productDetails['product_quantities']);
                            if (!isset($productDetails['quantity'])) {
                                $productDetails['quantity'] = 0;
                            }
                            $newQuantity = (int) ($productDetails['quantity'] ?? 0);
                        }
                    }
                    
                    // Save to database
                    $customProduct->product_ids = $productIdsToSave;
                    $customProduct->product_details = $productDetails;
                    $saved = $customProduct->save();
                    
                    if (!$saved) {
                        throw new \Exception('Failed to save product_ids to database - save() returned false');
                    }
                    
                    // Verify by querying database directly
                    $customProduct->refresh();
                    $savedProductIds = $customProduct->product_ids ?? [];
                    
                    // Double check with raw DB query
                    $rawData = DB::table('order_custom_products')
                        ->where('id', $customProductId)
                        ->value('product_ids');
                    $rawProductIds = $rawData ? json_decode($rawData, true) : [];
                    
                    // Verify the save
                    if (!empty($productIdsToSave)) {
                        $idsMatch = (count($productIdsToSave) === count($savedProductIds)) && 
                                   (empty(array_diff($productIdsToSave, $savedProductIds)));
                        if (!$idsMatch) {
                            \Illuminate\Support\Facades\Log::error('OrderForm: product_ids mismatch after save', [
                                'expected' => $productIdsToSave,
                                'got_from_model' => $savedProductIds,
                                'got_from_raw' => $rawProductIds,
                            ]);
                            throw new \Exception('product_ids were not saved correctly - expected: ' . json_encode($productIdsToSave) . ', got: ' . json_encode($savedProductIds));
                        }
                    }

                    // If quantity changed while workshop products are already in a deducted state,
                    // adjust stock for the difference immediately.
                    if ($this->isEditMode && $this->editingId && $newQuantity !== $oldQuantity && !empty($productIdsToSave)) {
                        $orderForQtyAdjust = Order::find($this->editingId);
                        if ($orderForQtyAdjust) {
                            $productStatus = is_array($orderForQtyAdjust->product_status ?? null)
                                ? ($orderForQtyAdjust->product_status['workshop'] ?? null)
                                : null;

                            // Only adjust if workshop status is already in a state where stock should be deducted.
                            if (in_array($productStatus, ['outfordelivery', 'in_transit', 'delivered'], true)) {
                                $oldProductsData = [];
                                $newProductsData = [];
                                foreach ($productIdsToSave as $pid) {
                                    $pid = (int)$pid;
                                    if ($pid <= 0) {
                                        continue;
                                    }
                                    $oldProductsData[$pid] = ['quantity' => $oldQuantity];
                                    $newProductsData[$pid] = ['quantity' => $newQuantity];
                                }

                                if (!empty($oldProductsData) && !empty($newProductsData)) {
                                    $this->adjustStockForProductChanges($orderForQtyAdjust, $oldProductsData, $newProductsData, null);
                                }
                            }
                        }
                    }
                    
                    DB::commit();
                    
                    // Update orderProducts array with fresh data from database
                    $this->orderProducts[$this->editingCustomProductIndex]['product_ids'] = $savedProductIds;
                    $saveSuccessful = true;
                    
                    // Log success
                    \Illuminate\Support\Facades\Log::info('OrderForm: Successfully saved product_ids to database from popup', [
                        'custom_product_id' => $customProductId,
                        'product_ids_to_save' => $productIdsToSave,
                        'product_ids_in_db' => $savedProductIds,
                        'raw_db_value' => $rawProductIds,
                        'save_successful' => true,
                    ]);
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    \Illuminate\Support\Facades\Log::error('OrderForm: Error saving product_ids from popup', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'custom_product_id' => $customProductId ?? null,
                        'product_ids' => $productIds,
                    ]);
                    $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Error saving connected products: ' . $e->getMessage()]);
                    return;
                }
            } else {
                if (!isset($this->orderProducts[$this->editingCustomProductIndex]['product_ids']) || 
                    empty($this->orderProducts[$this->editingCustomProductIndex]['product_ids'])) {
                    // Ensure product_ids are set
                    $this->orderProducts[$this->editingCustomProductIndex]['product_ids'] = $productIds;
                }
                
                \Illuminate\Support\Facades\Log::info('OrderForm: Custom product has no ID yet, product_ids will be saved with form', [
                    'index' => $this->editingCustomProductIndex,
                    'product_ids' => $productIds,
                    'orderProducts_product_ids' => $this->orderProducts[$this->editingCustomProductIndex]['product_ids'] ?? null,
                ]);
                $saveSuccessful = true; // Will be saved with form
            }
        
        if (!$saveSuccessful) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Failed to save connected products. Please try again.']);
            return;
        }
        
        // Auto-expand to show connected products after saving
        if (!empty($productIds)) {
            $this->expandedCustomProducts[$this->editingCustomProductIndex] = true;
        }
        
        // Close popup
        $this->closeCustomProductModal();
        
        // Force refresh of products property to update display
        unset($this->products);
        
        // Trigger Livewire to re-render the connected products section
        $this->dispatch('$refresh');
        
        // Dispatch event to refresh the view
        $this->dispatch('custom-product-updated', ['index' => $this->editingCustomProductIndex]);
        
        $message = !empty($productIds) 
            ? 'Connected ' . count($productIds) . ' product(s) successfully!'
            : 'Connected products cleared successfully!';
        $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
    }

    /**
     * Get connected products for a custom product index
     */
    public function getConnectedProductsForCustomProduct(int $index): array
    {
        $this->normalizeProductsArray();
        
        if (!isset($this->orderProducts[$index]) || !($this->orderProducts[$index]['is_custom'] ?? 0)) {
            return [];
        }
        
        $productIds = $this->orderProducts[$index]['product_ids'] ?? [];
        
        // Handle different data types
        if (!is_array($productIds)) {
            if (is_string($productIds)) {
                $decoded = json_decode($productIds, true);
                $productIds = is_array($decoded) ? $decoded : [];
            } else {
                $productIds = $productIds ? [$productIds] : [];
            }
        }
        
        if (empty($productIds)) {
            return [];
        }
        
        // Filter and convert to integers
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), fn($id) => $id > 0)));
        if (empty($productIds)) {
            return [];
        }
        
        $connectedProducts = [];
        
        try {
            // Load products for connected workshop items
            $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

            // Try to load custom product details (materials + total quantity) for this row
            $customProductId = $this->orderProducts[$index]['custom_product_id'] ?? null;
            $materialsSummary = null;
            $totalQuantity = null;
            $perProductQuantities = [];

            if ($customProductId) {
                $customProduct = OrderCustomProduct::find($customProductId);
                if ($customProduct && is_array($customProduct->product_details)) {
                    $productDetails = $customProduct->product_details;
                    $totalQuantity = $productDetails['quantity'] ?? null;
                    if (!empty($productDetails['product_quantities']) && is_array($productDetails['product_quantities'])) {
                        $perProductQuantities = $productDetails['product_quantities'];
                    }

                    $materials = $productDetails['materials'] ?? [];
                    if (!empty($materials) && is_array($materials)) {
                        $materialIds = array_values(array_unique(array_filter(array_column($materials, 'material_id'))));
                        $materialNames = [];

                        if (!empty($materialIds)) {
                            $materialsModels = Product::whereIn('id', $materialIds)->pluck('product_name', 'id');
                        } else {
                            $materialsModels = collect();
                        }

                        foreach ($materials as $material) {
                            $materialId = $material['material_id'] ?? null;
                            if (!$materialId) {
                                continue;
                            }

                            $name = $materialsModels[$materialId] ?? 'Material';
                            $calQty = $material['calculated_quantity'] ?? $material['cal_qty'] ?? null;

                            if ($calQty !== null) {
                                $materialNames[] = $name . ' (' . (float) $calQty . ')';
                            } else {
                                $materialNames[] = $name;
                            }
                        }

                        if (!empty($materialNames)) {
                            $materialsSummary = implode(', ', $materialNames);
                        }
                    }
                }
            }
            
            // Maintain order from product_ids array
            foreach ($productIds as $productId) {
                if (isset($products[$productId])) {
                    $product = $products[$productId];

                    // Decide which quantity to show for each connected product:
                    // - If per-product quantities were saved, use those.
                    // - Otherwise, fall back to the overall total quantity (legacy behaviour).
                    $displayQty = $totalQuantity;
                    if (!empty($perProductQuantities) && array_key_exists($productId, $perProductQuantities)) {
                        $displayQty = $perProductQuantities[$productId];
                    }

                    $connectedProducts[] = [
                        'id' => $product->id,
                        'name' => $product->product_name,
                        'category' => $product->category->name ?? 'N/A',
                        'unit' => $product->unit_type ?? 'N/A',
                        'image_url' => $product->first_image_url ?? null,
                        'quantity' => $displayQty,
                        'materials_summary' => $materialsSummary,
                    ];
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('OrderForm: Error loading connected products: ' . $e->getMessage());
            return [];
        }
        
        return $connectedProducts;
    }

    public function toggleCustomProductExpanded(int $index): void
    {
        if (!isset($this->expandedCustomProducts[$index])) {
            $this->expandedCustomProducts[$index] = false;
        }
        $this->expandedCustomProducts[$index] = !$this->expandedCustomProducts[$index];
    }

    /**
     * Get grouped products for display in edit mode
     */
    public function getGroupedProductsProperty(): array
    {
        if (!$this->isEditMode) {
            return [];
        }

        $grouped = [
            'hardware' => [],
            'warehouse' => [], // Use 'warehouse' to match view expectations (view uses 'warehouse' but data uses 'workshop')
            'lpo' => [],
        ];

        foreach ($this->orderProducts as $index => $product) {
            $productType = $product['product_type'] ?? 'hardware';
            
            // Determine type based on product or custom
            if (($product['is_custom'] ?? 0) == 1) {
                $productType = 'warehouse'; // Custom products go to warehouse/workshop section
            } elseif (!empty($product['product_id'])) {
                $productModel = Product::find($product['product_id']);
                if ($productModel) {
                    $productType = $this->getProductType($productModel);
                    // Map 'workshop' to 'warehouse' for view compatibility
                    if ($productType === 'workshop') {
                        $productType = 'warehouse';
                    }
                }
            }
            
            $product['index'] = $index;
            $grouped[$productType][] = $product;
        }

        return $grouped;
    }

    /**
     * Search suppliers for a specific product row
     */
    public function searchSuppliers(int $index, string $search = '', int $page = 1): void
    {
        // Prevent multiple simultaneous searches
        if (isset($this->supplierLoading[$index]) && $this->supplierLoading[$index]) {
            return;
        }
        
        // Only search if dropdown is open
        if (!isset($this->supplierDropdownOpen[$index]) || !$this->supplierDropdownOpen[$index]) {
            // Allow search if it's the initial load (page 1, empty search)
            if ($page !== 1 || !empty(trim($search))) {
                return;
            }
        }
        
        $this->supplierLoading[$index] = true;
        
        if (!isset($this->supplierSearch[$index])) {
            $this->supplierSearch[$index] = '';
        }
        if (!isset($this->supplierPage[$index])) {
            $this->supplierPage[$index] = 1;
        }
        
        $this->supplierSearch[$index] = $search;
        $this->supplierPage[$index] = $page;
        
        try {
            $perPage = 15;
            
            $query = Supplier::where('status', true);
            
            if (!empty($search) && trim($search) !== '') {
                $searchTerm = trim($search);
                $query->where('name', 'like', '%' . $searchTerm . '%');
            }
            
            $total = $query->count();
            $suppliers = $query->orderBy('name')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            $hasMore = ($page * $perPage) < $total;
            $this->supplierHasMore[$index] = $hasMore;
            
            $results = $suppliers->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'text' => $supplier->name,
                    'type' => $supplier->supplier_type ?? '',
                    'email' => $supplier->email ?? '',
                    'phone' => $supplier->phone ?? '',
                ];
            })->toArray();
            
            if ($page === 1) {
                $this->supplierSearchResults[$index] = $results;
            } else {
                $this->supplierSearchResults[$index] = array_merge($this->supplierSearchResults[$index] ?? [], $results);
            }
        } catch (\Exception $e) {
            Log::error('Supplier search error: ' . $e->getMessage());
            $this->dispatch('show-toast', ['type' => 'error', 'message' => 'Error searching suppliers. Please try again.']);
            $this->supplierSearchResults[$index] = [];
            $this->supplierHasMore[$index] = false;
        } finally {
            $this->supplierLoading[$index] = false;
        }
    }

    public function loadMoreSuppliers(int $index): void
    {
        if (isset($this->supplierLoading[$index]) && $this->supplierLoading[$index]) {
            return;
        }
        
        if (!isset($this->supplierDropdownOpen[$index]) || !$this->supplierDropdownOpen[$index]) {
            return;
        }
        
        if (!isset($this->supplierHasMore[$index]) || !$this->supplierHasMore[$index]) {
            return;
        }
        
        $nextPage = ($this->supplierPage[$index] ?? 1) + 1;
        $this->searchSuppliers($index, $this->supplierSearch[$index] ?? '', $nextPage);
    }

    public function selectSupplier(int $index, ?int $supplierId): void
    {
        if (!isset($this->orderProducts[$index])) {
            return;
        }
        
        $this->orderProducts[$index]['supplier_id'] = $supplierId ? (string)$supplierId : null;
        $this->supplierDropdownOpen[$index] = false;
        $this->supplierSearch[$index] = '';
        $this->supplierSearchResults[$index] = [];
        $this->supplierPage[$index] = 1;
    }

    public function toggleSupplierDropdown(int $index): void
    {
        if (!isset($this->supplierDropdownOpen[$index])) {
            $this->supplierDropdownOpen[$index] = false;
        }
        
        $this->supplierDropdownOpen[$index] = !$this->supplierDropdownOpen[$index];
        
        if ($this->supplierDropdownOpen[$index]) {
            // Load initial suppliers immediately when opening
            $this->supplierSearch[$index] = '';
            $this->supplierPage[$index] = 1;
            $this->supplierSearchResults[$index] = [];
            $this->searchSuppliers($index, '', 1);
        }
    }

    public function closeSupplierDropdown(int $index): void
    {
        $this->supplierDropdownOpen[$index] = false;
    }

    public function handleSupplierSearch($value, $key): void
    {
        // Extract index from key (e.g., "supplierSearch.5" -> 5)
        $parts = explode('.', $key);
        if (count($parts) < 2) {
            return;
        }
        $index = (int)$parts[1];
        
        // Ensure dropdown is open
        if (!isset($this->supplierDropdownOpen[$index]) || !$this->supplierDropdownOpen[$index]) {
            $this->supplierDropdownOpen[$index] = true;
        }
        
        // Reset pagination and clear results
        $this->supplierPage[$index] = 1;
        $this->supplierSearchResults[$index] = [];
        
        // Perform search
        $this->searchSuppliers($index, $value ?? '', 1);
    }

    public function render(): View
    {
        return view('admin::Order.views.order-form', [
            'siteManagers' => $this->siteManagers,
            'transportManagers' => $this->transportManagers,
            'sites' => $this->sites,
            'products' => $this->products,
            'priorities' => PriorityEnum::cases(),
            'groupedProducts' => $this->groupedProducts,
        ])->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Order' : 'Create Order',
            'breadcrumb' => [
                ['Order', route('admin.orders.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }

    /**
     * Convert date format from one format to another
     */
    protected function convertDateFormat(?string $date, string $fromFormat, string $toFormat): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $carbon = Carbon::createFromFormat($fromFormat, $date);
            return $carbon->format($toFormat);
        } catch (\Exception $e) {
            Log::warning("Failed to convert date format: {$date} from {$fromFormat} to {$toFormat}", [
                'error' => $e->getMessage()
            ]);
            return $date; // Return original if conversion fails
        }
    }
}

