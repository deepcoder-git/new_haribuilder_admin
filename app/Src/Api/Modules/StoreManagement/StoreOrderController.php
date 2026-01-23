<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\StoreManagement;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Src\Api\Modules\StoreManagement\Resources\StoreManagerOrderResource;
use App\Src\Api\Modules\StoreManagement\Resources\WorkshopStoreManagerOrderResource;
use App\Src\Api\Modules\StoreManagement\Resources\StockResource;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Stock;
use Illuminate\Support\Facades\Validator;
use App\Services\StockService;
use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use Illuminate\Support\Facades\DB;
use App\Utility\Enums\PriorityEnum;
use Illuminate\Validation\Rule;
use App\Utility\Resource\PaginationResource;
use Illuminate\Database\Eloquent\Builder;
use App\Utility\Enums\OrderStatusEnum;
use App\Services\OrderCustomProductManager;
use App\Models\Product;
use App\Utility\Enums\StoreEnum;
use Illuminate\Support\Facades\Log;


class StoreOrderController extends Controller
{

    protected ?StockService $stockService = null;
    protected OrderCustomProductManager $customProductManager;

    public function __construct(
        protected AuthService $authService
    ) {
        $this->stockService = app(StockService::class);
        $this->customProductManager = new OrderCustomProductManager();
    }

    private function getOrderResourceClass($userRole): string
    {
        if ($userRole === RoleEnum::WorkshopStoreManager->value) {
            return WorkshopStoreManagerOrderResource::class;
        }
        return StoreManagerOrderResource::class;
    }

    public function getOrders(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            if ($request->has('order_status') && $request->order_status == '') {
                $request->merge(['order_status' => 'all']);
            }

            $request->validate([
                'per_page' => ['required', 'integer', 'max_digits:3', 'min_digits:1'],
                'page' => ['required', 'integer'],
                // Filter based on main order_status column
                'order_status' => ['nullable', 'string', Rule::in(['pending', 'approve', 'approved', 'rejected', 'in_transit', 'delivered', 'outfordelivery', 'all'])],
            ]);

            $user = $request->user();
            
            if (!$user) {
                return new ApiErrorResponse(
                    ['errors' => ['User not authenticated']],
                    'get orders failed',
                    401
                );
            }

            $orderStatus = $request->order_status;
            $role = $user?->getRole();
            $userRole = $role?->value ?? null;

            if (!$userRole) {
                return new ApiErrorResponse(
                    ['errors' => ['User role not found']],
                    'get orders failed',
                    403
                );
            }

            $userRole = trim((string) $userRole);

            Log::info('StoreOrderController::getOrders', [
                'user_id' => $user->id,
                'user_role' => $userRole,
                'role_enum_value' => RoleEnum::WorkshopStoreManager->value,
                'order_status_filter' => $orderStatus,
            ]);

            // Build query to show orders that belong to this store manager
            // For Workshop store managers: show orders with warehouse products OR custom products OR store_manager_role = workshop_store_manager
            // For hardware store managers: show orders with hardware products OR store_manager_role = store_manager
            $query = Order::with([
                    'site',
                    'products.category',
                    'products.productImages',
                    'products.materials',
                    'customProducts.images'
                ])
                ->where(function($q) use ($userRole) {
                    // Show orders assigned to this role
                    $q->where('store_manager_role', $userRole);
                    
                    // Also show orders that have products matching this store type
                    if ($userRole === RoleEnum::WorkshopStoreManager->value) {
                        // Workshop store manager: show orders with warehouse products or custom products
                        $q->orWhereHas('products', function($productQuery) {
                            $productQuery->where('store', StoreEnum::WarehouseStore->value);
                        })
                        ->orWhere('is_custom_product', 1);
                    } elseif ($userRole === RoleEnum::StoreManager->value) {
                        // Hardware store manager: show orders with hardware products
                        $q->orWhereHas('products', function($productQuery) {
                            $productQuery->where('store', StoreEnum::HardwareStore->value);
                        });
                    }
                })
                ->where(function($q) {
                    $q->where('is_lpo', 0)->orWhereNull('is_lpo');
                })
                // Apply filter based on main `status` column (enum OrderStatusEnum) derived from product_status
                ->when($orderStatus && $orderStatus !== 'all', function (Builder $query) use ($orderStatus) {
                    $normalizedStatus = trim(strtolower((string) $orderStatus));

                    // Normalize incoming values to stored enum values
                    if ($normalizedStatus === 'approve' || $normalizedStatus === 'approved') {
                        $normalizedStatus = OrderStatusEnum::Approved->value;
                    } elseif ($normalizedStatus === 'pending') {
                        $normalizedStatus = OrderStatusEnum::Pending->value;
                    } elseif ($normalizedStatus === 'rejected') {
                        $normalizedStatus = OrderStatusEnum::Rejected->value;
                    } elseif ($normalizedStatus === 'in_transit') {
                        $normalizedStatus = OrderStatusEnum::InTransit->value;
                    } elseif ($normalizedStatus === 'out_of_delivery' || $normalizedStatus === 'outofdelivery' || $normalizedStatus === 'outfordelivery') {
                        $normalizedStatus = OrderStatusEnum::OutOfDelivery->value;
                    } elseif ($normalizedStatus === 'delivery' || $normalizedStatus === 'delivered') {
                        $normalizedStatus = OrderStatusEnum::Delivery->value;
                    }

                    $query->where('status', $normalizedStatus);
                })
                ->orderByDesc('id');
            
            $baseQuery = Order::where('store_manager_role', $userRole);
            $totalWithRole = $baseQuery->count();
          

            $totalBeforePagination = $query->count();
            Log::info('StoreOrderController::getOrders - Query count', [
                'total_matching_orders' => $totalBeforePagination,
                'total_with_role_only' => $totalWithRole,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $getOrders = $query->paginate($request->per_page)
                ->appends($request->query());
              
            $resourceClass = $this->getOrderResourceClass($userRole);

            return new ApiResponse(
                data: [
                    'orders' => $resourceClass::collection($getOrders->items()),
                    'pagination' => new PaginationResource($getOrders),
                ],
                message: $getOrders->isEmpty() ? __('api.site-manager.empty_records') : __('api.site-manager.order_list'),
                code: 200,
                isError: false
            );
        } catch (\Illuminate\Validation\ValidationException $e){
            return new ApiErrorResponse(
                ['errors' => $e->errors()],
                'get orders failed',
                422
            );
        } catch (\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get orders failed',
                500
            );
        }
    }

    public function getOrderDetails(Request $request, int $orderId): ApiResponse|ApiErrorResponse
    {
        try {
            $user = $request->user();
            $role = $user?->getRole();
            $userRole = $role?->value ?? null;

            if (!$userRole) {
                return new ApiErrorResponse(
                    ['errors' => ['User role not found']],
                    'get orders failed',
                    403
                );
            }

            $orderExists = Order::where('id', $orderId)->first();
            
            Log::info('StoreOrderController::getOrderDetails', [
                'order_id' => $orderId,
                'user_id' => $user->id,
                'user_role' => $userRole,
                'order_exists' => $orderExists ? true : false,
                'order_store_manager_role' => $orderExists?->store_manager_role,
                'order_is_lpo' => $orderExists?->is_lpo,
                'order_is_custom_product' => $orderExists?->is_custom_product,
            ]);

            // Build query to show order if it belongs to this store manager
            // For Workshop store managers: show orders with warehouse products OR custom products OR store_manager_role = workshop_store_manager
            // For hardware store managers: show orders with hardware products OR store_manager_role = store_manager
            $order = Order::with([
                    'site',
                    'products.category',
                    'products.productImages',
                    'products.materials',
                    'customProducts.images'
                ])
                ->where('id', $orderId)
                ->where(function($q) use ($userRole) {
                    // Show orders assigned to this role
                    $q->where('store_manager_role', $userRole);
                    
                    // Also show orders that have products matching this store type
                    if ($userRole === RoleEnum::WorkshopStoreManager->value) {
                        // Workshop store manager: show orders with warehouse products or custom products
                        $q->orWhereHas('products', function($productQuery) {
                            $productQuery->where('store', StoreEnum::WarehouseStore->value);
                        })
                        ->orWhere('is_custom_product', 1);
                    } elseif ($userRole === RoleEnum::StoreManager->value) {
                        // Hardware store manager: show orders with hardware products
                        $q->orWhereHas('products', function($productQuery) {
                            $productQuery->where('store', StoreEnum::HardwareStore->value);
                        });
                    }
                })
                ->where(function($q) {
                    $q->where('is_lpo', 0)->orWhereNull('is_lpo');
                })
                ->first();

            if (!$order) {
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.store-manager.empty_records'),
                );
            }

            $resourceClass = $this->getOrderResourceClass($userRole);

            return new ApiResponse(
                data: new $resourceClass($order),
                message: __('api.store-manager.order_details'),
                code: 200,
                isError: false
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get order details failed',
                500
            );
        }
    }

    public function stockList(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $productWiseStock = Stock::with(['product','product.category'])->select('product_id', 
                    DB::raw('SUM(CASE WHEN adjustment_type = "in" THEN quantity ELSE 0 END) AS total_in'),
                    DB::raw('SUM(CASE WHEN adjustment_type = "out" THEN quantity ELSE 0 END) AS total_out'));
            
            if ($request->has('product_id')) {
                $productWiseStock->where('product_id', $request->input('product_id'));
            }
            if ($request->has('category_id')) {
                $productWiseStock->whereHas('product.category', function($query) use ($request){
                    $query->where('id', $request->input('category_id'));
                });
            }
            
            $productWiseStock = $productWiseStock->groupBy('product_id')->get();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: StockResource::collection($productWiseStock),
                message: ($productWiseStock->isEmpty()) ? __('api.store-manager.empty_records') : __('api.store-manager.stock_list'),
            );
        } catch (\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'stock list failed',
                500
            );
        }
    }

    public function updateStatus(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $request->validate([
                'order_id' => ['required', 'integer', 'exists:orders,id'],
                'delivery_status' => ['required', 'string', 'in:approved,rejected'],
                'rejected_note' => ['required_if:delivery_status,rejected', 'nullable', 'string'],
            ], [
                'order_id.required' => 'Order ID is required',
                'order_id.exists' => 'Order not found',
                'delivery_status.required' => 'Delivery status is required',
                'delivery_status.in' => 'Invalid delivery status value',
                'rejected_note.required_if' => 'Rejected note is required when rejecting an order.',
            ]);

            $user = $request->user();
            $role = $user?->getRole();

            if (
                !$user
                || !in_array($user->getRole(), [RoleEnum::StoreManager, RoleEnum::WorkshopStoreManager], true)
            ) {
                return new ApiErrorResponse(
                    ['errors' => [__('api.store-manager.invalid_approval_role')]],
                    'order status update failed',
                    403
                );
            }

            $order = Order::findOrFail($request->order_id);
            $userRole = $user->role?->value ?? null;

            if ($order->store_manager_role !== $userRole) {
                return new ApiErrorResponse(
                    ['errors' => ['Unauthorized access to this order']],
                    'order status update failed',
                    403
                );
            }

            // Exclude LPO orders from store management (is_lpo = 1)
            if ($order->is_lpo == 1) {
                return new ApiErrorResponse(
                    ['errors' => ['LPO orders are not accessible through store management']],
                    'order status update failed',
                    403
                );
            }

            $status = $request->delivery_status;
        

            if ($status === 'rejected') {
                return $this->handleRejection($order, $request->rejected_note, $user);
            }
            if ($status === 'approved') {
                return $this->handleApproval($order,$user);
            }


            $statusMessages = [
                'approved' => 'Order marked as approved',
                'rejected' => 'Order marked as rejected',
            ];

            $resourceClass = $this->getOrderResourceClass($userRole);

            return new ApiResponse(
                data: new $resourceClass($order->fresh([
                    'site',
                    'products.category',
                    'products.productImages',
                    'customProducts.images'
                ])),
                message: $statusMessages[$status] ?? 'Order status updated successfully',
                code: 200,
                isError: false
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return new ApiErrorResponse(
                ['errors' => $e->errors()],
                'order status update failed',
                422
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return new ApiErrorResponse(
                ['errors' => ['Order not found']],
                'order status update failed',
                404
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'order status update failed',
                500
            );
        }
    }


    private function handleRejection(Order $order, ?string $rejectedNote, $user): ApiResponse|ApiErrorResponse
    {
        if ($order->delivery_status === 'rejected') {
            return new ApiErrorResponse(
                ['errors' => [__('api.store-manager.order_already_rejected')]],
                'order rejection failed',
                403
            );
        }

        if (empty($rejectedNote) || trim($rejectedNote) === '') {
            return new ApiErrorResponse(
                ['errors' => ['Rejected note is required when rejecting an order.']],
                'order rejection failed',
                422
            );
        }

        $oldDeliveryStatus = $order->delivery_status;
        $wasApprovedOrInTransit = in_array($oldDeliveryStatus, ['approved', 'in_transit']);

        // Load products to check product types
        $order->load('products', 'customProducts');

        // Restore stock if order was previously approved/in_transit
        if ($wasApprovedOrInTransit) {
            try {
                $productsData = [];
                foreach ($order->products as $product) {
                    $productsData[$product->id] = [
                        'quantity' => (float)$product->pivot->quantity,
                    ];
                }
                $this->restoreStockForOrder($order, $productsData, null);
            } catch (\Exception $e) {
                Log::error("StoreOrderController: Failed to restore stock when rejecting order {$order->id}: " . $e->getMessage());
                // Continue with rejection even if stock restoration fails
            }
        }

        // Determine which product_status type(s) to update based on logged-in user's role and actual products in order
        $productStatusTypesToUpdate = [];
        
        // Get logged-in user's role
        $userRole = $user->getRole();
        $userRoleValue = $userRole?->value ?? null;
        
        // Check which product types actually exist in the order
        $hasHardwareProducts = false;
        $hasWarehouseProducts = false;
        $hasCustomProducts = $order->customProducts && $order->customProducts->isNotEmpty();
        
        foreach ($order->products as $product) {
            if ($product->store === StoreEnum::HardwareStore) {
                $hasHardwareProducts = true;
            } elseif ($product->store === StoreEnum::WarehouseStore) {
                $hasWarehouseProducts = true;
            }
        }
        
        // Determine product_status types to update based on logged-in user's role
        // Only update product_status for product types that exist in the order
        if ($userRoleValue === RoleEnum::StoreManager->value) {
            // Hardware Store Manager: update hardware product_status if hardware products exist
            if ($hasHardwareProducts) {
                $productStatusTypesToUpdate[] = 'hardware';
            }
        } elseif ($userRoleValue === RoleEnum::WorkshopStoreManager->value) {
            // Workshop store Manager: both warehouse and custom products use 'warehouse' key
            // Update warehouse product_status if warehouse products OR custom products exist
            if ($hasWarehouseProducts || $hasCustomProducts) {
                $productStatusTypesToUpdate[] = 'warehouse'; // Both use 'warehouse' key
            }
        }
        
        // Fallback: if no product types determined, check order's store_manager_role
        if (empty($productStatusTypesToUpdate)) {
            $orderStoreManagerRole = $order->store_manager_role;
            if ($orderStoreManagerRole === RoleEnum::StoreManager->value && $hasHardwareProducts) {
                $productStatusTypesToUpdate[] = 'hardware';
            } elseif ($orderStoreManagerRole === RoleEnum::WorkshopStoreManager->value) {
                // Both warehouse and custom products use 'warehouse' key
                if ($hasWarehouseProducts || $hasCustomProducts) {
                    $productStatusTypesToUpdate[] = 'warehouse';
                }
            }
        }

        // Update product_status for each type
        foreach ($productStatusTypesToUpdate as $type) {
            $order->updateProductStatus($type, 'rejected');
        }

        // Refresh order to get latest product_status
        $order->refresh();
        
        // Calculate and update order status based on product statuses
        $calculatedStatus = $order->calculateOrderStatusFromProductStatuses();
        
        // If calculated status matches one of the rules (pending or approved), use it
        // Otherwise, keep rejected status
        $finalStatus = in_array($calculatedStatus, ['pending', 'approved'], true) 
                       ? $calculatedStatus 
                       : OrderStatusEnum::Rejected->value;

        $order->update([
            'status' => $finalStatus,
            'rejected_note' => $rejectedNote,
        ]);

        // Sync parent/child order statuses
        Order::syncParentChildOrderStatuses($order);

        if ($order->siteManager) {
            $order->siteManager->notify(new \App\Notifications\OrderRejectedNotification($order));
        }

        // Use logged-in user's role for resource class, not order's store_manager_role
        $userRole = $user->getRole();
        $userRoleValue = $userRole?->value ?? null;
        $resourceClass = $this->getOrderResourceClass($userRoleValue);

        return new ApiResponse(
            isError: false,
            code: 200,
            data: new $resourceClass($order->fresh([
                'site',
                'products.category',
                'products.productImages',
                'customProducts.images'
            ])),
            message: __('api.store-manager.order_rejected'),
        );
    }
    private function handleApproval(Order $order,$user): ApiResponse|ApiErrorResponse
    {
        if ($order->delivery_status === 'approved') {
            return new ApiErrorResponse(
                ['errors' => [__('api.store-manager.order_already_approved')]],
                'order approval failed',
                403
            );
        }

        try {
            DB::beginTransaction();

            // Load products and custom products with their relationships
            $order->load([
                'products.productImages',
                'products.category',
                'customProducts.images'
            ]);
            
            // Prepare products data for stock deduction
            $productsData = [];
            foreach ($order->products as $product) {
                $productsData[$product->id] = [
                    'quantity' => (float)$product->pivot->quantity,
                ];
            }

            // Deduct stock when order is approved
            if (!empty($productsData)) {
                $this->deductStockForOrder($order, $productsData, null);
            }

            // Determine which product_status type(s) to update based on logged-in user's role and actual products in order
            $productStatusTypesToUpdate = [];
            
            // Get logged-in user's role
            $userRole = $user->getRole();
            $userRoleValue = $userRole?->value ?? null;
            
            // Check which product types actually exist in the order
            $hasHardwareProducts = false;
            $hasWarehouseProducts = false;
            $hasLpoProducts = false;
            $hasCustomProducts = $order->customProducts && $order->customProducts->isNotEmpty();
            
            foreach ($order->products as $product) {
                if ($product->store === StoreEnum::HardwareStore) {
                    $hasHardwareProducts = true;
                } elseif ($product->store === StoreEnum::WarehouseStore) {
                    $hasWarehouseProducts = true;
                } elseif ($product->store === StoreEnum::LPO) {
                    $hasLpoProducts = true;
                }
            }
            
            // Determine product_status types to update based on logged-in user's role
            // Only update product_status for product types that exist in the order
            if ($userRoleValue === RoleEnum::StoreManager->value) {
                // Hardware Store Manager: update hardware product_status if hardware products exist
                if ($hasHardwareProducts) {
                    $productStatusTypesToUpdate[] = 'hardware';
                }
            } elseif ($userRoleValue === RoleEnum::WorkshopStoreManager->value) {
                // Workshop store Manager: both warehouse and custom products use 'warehouse' key
                // Update warehouse product_status if warehouse products OR custom products exist
                if ($hasWarehouseProducts || $hasCustomProducts) {
                    $productStatusTypesToUpdate[] = 'warehouse'; // Both use 'warehouse' key
                }
            }
            
            // Fallback: if no product types determined, check order's store_manager_role
            // This handles edge cases where user role might not match
            if (empty($productStatusTypesToUpdate)) {
                $orderStoreManagerRole = $order->store_manager_role;
                if ($orderStoreManagerRole === RoleEnum::StoreManager->value && $hasHardwareProducts) {
                    $productStatusTypesToUpdate[] = 'hardware';
                } elseif ($orderStoreManagerRole === RoleEnum::WorkshopStoreManager->value) {
                    // Both warehouse and custom products use 'warehouse' key
                    if ($hasWarehouseProducts || $hasCustomProducts) {
                        $productStatusTypesToUpdate[] = 'warehouse';
                    }
                }
            }

            // Update product_status for each type
            foreach ($productStatusTypesToUpdate as $type) {
                $order->updateProductStatus($type, 'approved');
            }

            // Refresh order to get latest product_status
            $order->refresh();
            
            // Calculate and update order status based on product statuses
            $calculatedStatus = $order->calculateOrderStatusFromProductStatuses();
            
            $order->update([
                'status' => $calculatedStatus,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // Sync parent/child order statuses
            Order::syncParentChildOrderStatuses($order);

            DB::commit();

            if ($order->siteManager) {
                $order->siteManager->notify(new \App\Notifications\OrderApprovedNotification($order));
            }

            // Use logged-in user's role for resource class, not order's store_manager_role
            $userRoleValue = $userRole?->value ?? null;
            $resourceClass = $this->getOrderResourceClass($userRoleValue);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: new $resourceClass($order->fresh([
                    'site',
                    'products.category',
                    'products.productImages',
                    'customProducts.images'
                ])),
                message: __('api.store-manager.order_approved'),
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("StoreOrderController: Failed to approve order {$order->id}: " . $e->getMessage());
            
            // Extract cleaner error message
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Insufficient stock') !== false || strpos($errorMessage, 'Insufficient material stock') !== false) {
                $errorMessage = 'Insufficient stock for some products. Please check the stock availability.';
                return new ApiErrorResponse(
                            ['errors' => [$errorMessage]],
                            'Insufficient stock for some products. Please check the stock availability.',
                            422
                        );
            }else{
                return new ApiErrorResponse(
                    ['errors' => [$errorMessage]],
                    'Failed to approve order. Please try again.',
                    500
                );
            }
        }
    }

    public function deleteOrder(Request $request, int $orderId): ApiResponse|ApiErrorResponse
    {
        try {

            DB::beginTransaction();

            $order = Order::findOrFail($orderId);

            if (!$order) {
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.store-manager.empty_records'),
                );
            }

            // $user = $request->user();
            // $role = $user?->getRole();

            // // Check if order belongs to the store manager
            // if ($order->store_manager_id !== $user->id) {
            //     return new ApiErrorResponse([], 'Unauthorized access to this order', 403);
            // }

            // // Only Workshop Store Manager can manage custom-product orders
            // if ($role->value === RoleEnum::StoreManager->value && (bool) $order->is_custom_product) {
            //     return new ApiErrorResponse([], 'Custom product orders are only accessible for Workshop Store Manager', 403);
            // }

            // // Exclude LPO orders from store management (is_lpo = 1)
            // if ($order->is_lpo == 1) {
            //     return new ApiErrorResponse([], 'LPO orders are not accessible through store management', 403);
            // }

            $immutableStatuses = ['approved', 'in_transit', 'delivered'];

            if (in_array($order->delivery_status, $immutableStatuses, true)) {
                return new ApiErrorResponse(
                    ['errors' => ['Processed orders cannot be deleted.']],
                    'order deletion failed',
                    422
                );
            }

            $order->delete();

            DB::commit();
            return new ApiResponse(
                isError: false,
                code: 200,
                data: [],
                message: __('api.store-manager.order_deleted') ?? 'Order deleted successfully',
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'order deletion failed',
                500
            );
        }
    }

    /**
     * Deduct stock for order products when order is approved
     */
    private function deductStockForOrder($order, array $productsData, ?int $siteId = null): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        foreach ($productsData as $productId => $productInfo) {
            $product = Product::with('materials')->find($productId);
            
            // Skip if product not found
            if (!$product) {
                continue;
            }
            
            // Product store column is bypassed - use order-level store instead
            // Skip LPO orders only (checked at order level via is_lpo flag)
            if ($order->is_lpo == 1) {
                continue;
            }

            $quantity = (int)($productInfo['quantity'] ?? 0);
            
            if ($quantity > 0) {
                try {
                    // Deduct product stock
                    $this->stockService->adjustStock(
                        (int)$productId,
                        $quantity,
                        'out',
                        $siteId,
                        "Stock deducted for Order #{$order->id} (quantity: " . number_format($quantity, 2) . ")",
                        $order,
                        "Order #{$order->id} - Stock Deducted"
                    );
                } catch (\Exception $e) {
                    Log::error("StoreOrderController: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    throw $e;
                }
            }
        }
    }

    /**
     * Restore stock for order products when order is rejected after approval
     */
    private function restoreStockForOrder($order, array $productsData, ?int $siteId = null): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        foreach ($productsData as $productId => $productInfo) {
            $product = Product::with('materials')->find($productId);

            if (!$product) {
                continue;
            }

            // Product store column is bypassed - use order-level store instead
            // Skip LPO orders only (checked at order level via is_lpo flag)
            if ($order->is_lpo == 1) {
                continue;
            }

            $quantity = (int)($productInfo['quantity'] ?? 0);
            
            if ($quantity > 0) {
                try {
                    // 1) Restore finished product stock
                    $this->stockService->adjustStock(
                        (int)$productId,
                        $quantity,
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

                        $materialTotalQty = (int)($materialQtyPerUnit * $quantity);

                        $this->stockService->adjustMaterialStock(
                            (int)$material->id,
                            $materialTotalQty,
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
                    Log::error("StoreOrderController: Failed to restore stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    throw $e;
                }
            }
        }
    }

    /**
     * Calculate custom product quantity based on dimensions
     * Returns only the calculation result
     *
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function calculateCustomProduct(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $request->validate([
                'order_id' => ['required', 'integer', 'exists:orders,id'],
                'order_custom_product_id' => ['nullable', 'integer', 'exists:order_custom_products,id'],

                // Single-line (old behaviour)
                'actual_pcs' => ['nullable', 'integer', 'min:1'],

                // Product-wise + material-wise (new)
                'products' => ['sometimes', 'array', 'min:1'],
                'products.*.product_id' => ['required_with:products', 'integer'],
                'products.*.materials' => ['required_with:products.*.product_id', 'array', 'min:1'],
                'products.*.materials.*.material_id' => ['required_with:products.*.materials', 'integer'],
                'products.*.materials.*.actual_pcs' => ['nullable', 'integer', 'min:1'],

                // Multi-line material-wise calculation (legacy flat payload)
                'materials' => ['sometimes', 'array', 'min:1'],
                'materials.*.material_id' => ['required_with:materials', 'integer'],
                'materials.*.actual_pcs' => ['nullable', 'integer', 'min:1'],
                'materials.*.quantity' => ['nullable', 'numeric', 'min:0']
            ]);

            // CASE 1: product-wise + material-wise (preferred when products[] is present)
            if ($request->filled('products') && is_array($request->products)) {
                $productsResult = [];
                $grandTotalQuantity = 0;

                foreach ($request->products as $productIndex => $product) {
                    // Skip if no materials for this product
                    if (
                        !isset($product['materials'])
                        || !is_array($product['materials'])
                        || empty($product['materials'])
                    ) {
                        continue;
                    }

                    $productMaterialsResult = [];
                    $productTotalQuantity = 0;

                    foreach ($product['materials'] as $materialIndex => $material) {
                        $h1 = isset($material['h1']) && $material['h1'] !== '' ? (float) $material['h1'] : 0;
                        $h2 = isset($material['h2']) && $material['h2'] !== '' ? (float) $material['h2'] : 0;
                        $w1 = isset($material['w1']) && $material['w1'] !== '' ? (float) $material['w1'] : 0;
                        $w2 = isset($material['w2']) && $material['w2'] !== '' ? (float) $material['w2'] : 0;

                        // Use actual_pcs (support pcs for backward compatibility in materials)
                        $pcs = 0;
                        if (isset($material['actual_pcs']) && $material['actual_pcs'] !== '') {
                            $pcs = (int) $material['actual_pcs'];
                        } elseif (isset($material['pcs']) && $material['pcs'] !== '') {
                            $pcs = (int) $material['pcs'];
                        }

                        $sumOfDimensions = $h1 + $h2 + $w1 + $w2;
                        $calculatedQuantity = $sumOfDimensions * $pcs;
                        $productTotalQuantity += $calculatedQuantity;

                            $productMaterialsResult[] = [
                                'material_id' => $material['material_id'] ?? $materialIndex,
                                'calculated_quantity' => $calculatedQuantity,
                                'h1' => $h1,
                                'h2' => $h2,
                                'w1' => $w1,
                                'w2' => $w2,
                                'actual_pcs' => $pcs,
                            ];
                    }

                    $grandTotalQuantity += $productTotalQuantity;

                    $productsResult[] = [
                        'product_id' => $product['product_id'] ?? $productIndex,
                        'total_calculated_quantity' => $productTotalQuantity,
                        'materials' => $productMaterialsResult,
                    ];
                }

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [
                        'grand_total_calculated_quantity' => $grandTotalQuantity,
                        'products' => $productsResult,
                    ],
                    message: 'Product-wise material calculation completed successfully',
                );
            }

            // CASE 2: material-wise calculation (when flat materials[] is present)
            if ($request->filled('materials') && is_array($request->materials)) {
                $materialsResult = [];
                $totalQuantity = 0;

                foreach ($request->materials as $index => $material) {
                    $h1 = isset($material['h1']) && $material['h1'] !== '' ? (float) $material['h1'] : 0;
                    $h2 = isset($material['h2']) && $material['h2'] !== '' ? (float) $material['h2'] : 0;
                    $w1 = isset($material['w1']) && $material['w1'] !== '' ? (float) $material['w1'] : 0;
                    $w2 = isset($material['w2']) && $material['w2'] !== '' ? (float) $material['w2'] : 0;

                    // Use actual_pcs (support pcs for backward compatibility in materials)
                    $pcs = 0;
                    if (isset($material['actual_pcs']) && $material['actual_pcs'] !== '') {
                        $pcs = (int) $material['actual_pcs'];
                    } elseif (isset($material['pcs']) && $material['pcs'] !== '') {
                        $pcs = (int) $material['pcs'];
                    }

                    $sumOfDimensions = $h1 + $h2 + $w1 + $w2;
                    $calculatedQuantity = $sumOfDimensions * $pcs;
                    $totalQuantity += $calculatedQuantity;

                    $materialsResult[] = [
                        'material_id' => $material['material_id'] ?? $index,
                        'calculated_quantity' => $calculatedQuantity,
                        'h1' => $h1,
                        'h2' => $h2,
                        'w1' => $w1,
                        'w2' => $w2,
                        'actual_pcs' => $pcs,
                    ];
                }

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [
                        'total_calculated_quantity' => $totalQuantity,
                        'materials' => $materialsResult,
                    ],
                    message: 'Material-wise calculation completed successfully',
                );
            }

            // CASE 3: single-line calculation (backward compatible)
            $h1 = isset($request->h1) && $request->h1 !== '' ? (float) $request->h1 : 0;
            $h2 = isset($request->h2) && $request->h2 !== '' ? (float) $request->h2 : 0;
            $w1 = isset($request->w1) && $request->w1 !== '' ? (float) $request->w1 : 0;
            $w2 = isset($request->w2) && $request->w2 !== '' ? (float) $request->w2 : 0;

            // Use actual_pcs
            $actualPcs = 0;
            if (isset($request->actual_pcs) && $request->actual_pcs !== '') {
                $actualPcs = (int) $request->actual_pcs;
            }

            // Calculate quantity based on formula: (m1 + m2 + m3 + m4) * qty
            // Where m1=h1, m2=h2, m3=w1, m4=w2, and qty=actual_pcs
            $sumOfDimensions = $h1 + $h2 + $w1 + $w2;
            $calculatedQuantity = $sumOfDimensions * $actualPcs;

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [
                    'calculated_quantity' => $calculatedQuantity,
                    'h1' => $h1,
                    'h2' => $h2,
                    'w1' => $w1,
                    'w2' => $w2,
                    'actual_pcs' => $actualPcs,
                ],
                message: ($sumOfDimensions > 0 && $actualPcs > 0)
                    ? 'Calculation completed successfully'
                    : 'Calculation completed (result may be zero if dimensions or quantity is zero)',
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return new ApiErrorResponse(
                ['errors' => $e->errors()],
                'calculate custom product failed',
                422
            );
        } catch (\Exception $e) {
            Log::error('Calculate custom product failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return new ApiErrorResponse(
                ['errors' => ['Failed to calculate: ' . $e->getMessage()]],
                'calculate custom product failed',
                500
            );
        }
    }
        
}
