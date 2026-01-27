<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Src\Api\Modules\SiteManagement\Resources\UnitResource;
use App\Src\Api\Modules\SiteManagement\Resources\CategoryResource;
use App\Src\Api\Modules\SiteManagement\Resources\ProductResource;
use App\Src\Api\Modules\SiteManagement\Resources\SiteResource;
use App\Src\Api\Modules\SiteManagement\Resources\ModeratorResource;
use App\Src\Api\Modules\SiteManagement\Resources\OrderResource;
use App\Src\Api\Modules\SiteManagement\Validators\OrderRequestValidator;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Site;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Services\StockService;
use App\Models\Moderator;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\StoreEnum;
use App\Utility\Enums\StatusEnum;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderRejectedNotification;
use Illuminate\Support\Facades\DB;
use App\Utility\Enums\PriorityEnum;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\OrderCustomProduct;
use App\Models\OrderCustomProductImage;
use App\Services\OrderCustomProductManager;
use Carbon\Carbon;
use App\Utility\Resource\PaginationResource;
use Illuminate\Database\Eloquent\Builder;
use App\Src\Api\Modules\StoreManagement\Resources\StoreManagerOrderResource;
use App\Src\Api\Modules\StoreManagement\Resources\WorkshopStoreManagerOrderResource;
// use Illuminate\Support\Facades\Storage as FacadesStorage;


class OrderController extends Controller
{

    protected ?StockService $stockService = null;
    protected OrderCustomProductManager $customProductManager;

    public function __construct(
        protected AuthService $authService
    ) {
        $this->customProductManager = new OrderCustomProductManager();
        $this->stockService = app(StockService::class);
    }

    /**
     * Check if site is APL site by code
     * APL site can manage all products (is_product 0, 1, and 2)
     * Other sites can only manage products where is_product IN (1, 2)
     */
    protected function isAplSite(?int $siteId = null, ?int $userId = null): bool
    {
        // First check if site_id is provided directly
        if ($siteId) {
            $site = Site::find($siteId);
            // if ($site && strtoupper($site->code) === 'APL') {
                return true;
            // }
        }
        
        // If no site_id but user is provided, check user's sites
        if (!$siteId && $userId) {
            $userSites = Site::where('site_manager_id', $userId)->get();
            foreach ($userSites as $site) {
                // if ($site) {
                    return true;
                // }
            }
        }
        
        return false;
    }

    /**
     * Apply product filtering based on site
     * APL site: show all products (is_product 0, 1, and 2)
     * Other sites: only show products where is_product IN (1, 2)
     */
    protected function applyProductFilterBySite($query, ?int $siteId = null, ?int $userId = null): void
    {
        if (!$this->isAplSite($siteId, $userId)) {
            // For non-APL sites, only show products (is_product IN (1, 2))
            $query->whereIn('is_product', [1, 2]);
        }
        // For APL site, show all products (no filter needed)
    }

    public function products(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $siteId = $request->input('site_id');
            $user = $request->user();
            $userId = $user ? $user->id : null;
            
            $query = Product::with(['category', 'productImages', 'stocks', 'materials', 'materials.category', 'materials.productImages'])->active();
            
            // Apply product filter based on site
            $this->applyProductFilterBySite($query, $siteId, $userId);
            
            $products = $query->get();
        
            return new ApiResponse(
                isError: false,
                code: 200,
                data: ProductResource::collection($products),
                message: $products->isEmpty() ? __('api.site-manager.empty_records') : __('api.site-manager.records_found'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get products failed',
                500
            );
        }
    }

    public function productList(Request $request, ?string $store_id = null): ApiResponse|ApiErrorResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 20);
            $perPage = $perPage > 0 ? min($perPage, 100) : 20;
            
            $user = $request->user();
            // dd($user);
            $role = $user?->role ?? null;
            $userId = $user?->id ?? null;
            
            $query = Product::with(['category', 'productImages', 'stocks', 'materials', 'materials.category', 'materials.productImages'])->whereIn('is_product', [1, 2])->active();
            
            // Get site_id from request for product filtering
            $siteId = $request->input('site_id');
            
            // Apply product filter based on site
            $this->applyProductFilterBySite($query, $siteId, $userId);
            
            // Role-based filtering by store_manager_id
            if ($role && $role->value === RoleEnum::StoreManager->value) {
                // Store manager sees only their own products
                $query->where('store', StoreEnum::HardwareStore->value);
            } elseif ($role && $role->value === RoleEnum::WorkshopStoreManager->value) {
                // Workshop Store Manager sees only their own products
                $query->where('store', StoreEnum::WarehouseStore->value);
            } elseif ($store_id) {
                // If store_id is provided (for admin/other roles), filter by it
                $query->where('store_manager_id', (int) $store_id);
            }
            // Site managers and other roles see all products (no store filter)
            
            // Filter by category
            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            if($request->has('product_id') && $request->product_id) {
                $query->where('id', $request->product_id);
            }
            
            // Search by product name
            if ($request->has('search') && $request->search) {
                $query->where('product_name', 'like', '%' . $request->search . '%');
            }
            
            $products = $query->orderBy('product_name', 'asc')
                             ->paginate($perPage)
                             ->appends($request->query());
                            //  dd($products);
        
            return new ApiResponse(
                isError: false,
                code: 200,
                data: ProductResource::collection($products),
                message: $products->isEmpty() ? __('api.site-manager.empty_records') : __('api.site-manager.records_found'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get product list failed',
                500
            );
        }
    }

    public function metadata(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $perPage = (int) $request->get('per_page', 20);
            $perPage = $perPage > 0 ? min($perPage, 100) : 20;
            $user = $request->user();
            $role = $user?->role ?? null;
            $userId = $user?->id ?? null;
            // dd($role->value, $userId,RoleEnum::StoreManager->value, RoleEnum::WorkshopStoreManager->value);
            if ($role && $role->value === RoleEnum::StoreManager->value) {
                $productQuery = Product::with(['category', 'productImages', 'stocks', 'materials', 'materials.category', 'materials.productImages'])->where('store', StoreEnum::HardwareStore->value)->whereIn('is_product', [1, 2])->active();
            } elseif ($role && $role->value === RoleEnum::WorkshopStoreManager->value) {
                $productQuery = Product::with(['category', 'productImages', 'stocks', 'materials', 'materials.category', 'materials.productImages'])->where('store', StoreEnum::WarehouseStore->value)->whereIn('is_product', [1, 2])->active();
            } else {
                $productQuery = Product::with(['category', 'productImages', 'stocks', 'materials', 'materials.category', 'materials.productImages'])->whereIn('is_product', [1, 2])->active();
            }

            // Filter by category for products
            if ($request->has('category_id') && $request->category_id) {
                $productQuery->where('category_id', $request->category_id);
            }

            // Filter by product_id (for products only)
            if($request->has('product_id') && $request->product_id) {
                $productQuery->where('id', $request->product_id);
            }
            
            // Search by name
            if ($request->has('search') && $request->search) {
                $searchTerm = '%' . $request->search . '%';
                $productQuery->where('product_name', 'like', $searchTerm);
            }

            // Get all products (no pagination for dropdown)
            // $products = $productQuery->orderBy('product_name', 'asc')->get();

            $products = $productQuery->orderBy('product_name', 'asc')
            ->paginate($perPage)
            ->appends($request->query());


            return new ApiResponse(
                isError: false,
                code: 200,
                data: ProductResource::collection($products),
                message: $products->isEmpty() ? __('api.site-manager.empty_records') : __('api.site-manager.records_found'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get metadata failed',
                500
            );
        }
    }

    public function categorySiteList(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $user = $request->user();
            // Only return active sites so site supervisors cannot create orders for inactive sites
            $sites = Site::where('site_manager_id', $user->id)
                ->where('status', true)
                ->get();
            $categories = Category::active()->get();
        
            return new ApiResponse(
                isError: false,
                code: 200,
                data: [
                    "categoryList" => CategoryResource::collection($categories),
                    "siteList" => SiteResource::collection($sites)
                ],
                message: ($categories->isEmpty() && $sites->isEmpty()) ? __('api.site-manager.empty_records') : __('api.site-manager.records_found'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get category site list failed',
                500
            );
        }
    }

    public function orderCreateRequirements(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $user = $request->user();
            // Only allow creating orders for active sites
            $sites = Site::where('site_manager_id', $user->id)
                ->where('status', true)
                ->get();
            
            // Get site_id from request for product filtering
            $siteId = $request->input('site_id');
            
            $productQuery = Product::with(['category', 'productImages'])->active();
            
            // Apply product filter based on site
            $this->applyProductFilterBySite($productQuery, $siteId, $user->id);
            
            $products = $productQuery->get();
            
            $priorities = collect(PriorityEnum::cases())->map(fn ($priority) => [
                'value' => $priority->value,
                'name' => $priority->getName(),
            ])->values();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [
                    "sites" => SiteResource::collection($sites),
                    "products" => ProductResource::collection($products),
                    "priorities" => $priorities,
                ],
                message: ($sites->isEmpty() && $products->isEmpty()) ? __('api.site-manager.empty_records') : __('api.site-manager.records_found'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get order create requirements failed',
                500
            );
        }
    }

    public function orderRequestList(Request $request, int $site_id): ApiResponse|ApiErrorResponse
    {
        try{
            $orderStatus = $request->get('order_status' ,'all');
            $request->validate([
                'per_page' => ['required', 'integer', 'max_digits:3', 'min_digits:1'],
                'page' => ['required', 'integer'],
                'order_status' => ['nullable', 'string', Rule::in(['pending', 'approved', 'rejected', 'in_transit', 'delivered', 'outfordelivery', 'all'])],
            ]);

            $user = $request->user();

            // Verify site exists
            $site = Site::find($site_id);
            if (!$site) {
                return new ApiErrorResponse(
                    ['errors' => ['Site not found']],
                    'Site not found',
                    404
                );
            }

            // Build query - check orders where user is site manager AND site matches
            $query = Order::with([
                    'site',
                    'products.category', 
                    'products.productImages',
                    'customProducts.images'
                ])
                ->where('site_manager_id', $user->id)
                ->where('site_id', $site_id)
                // Filter by ORDER STATUS column (enum) based on order_status query param
                ->when($orderStatus && $orderStatus !== 'all', function (Builder $query) use ($orderStatus) {
                    // Filter by main ORDER STATUS enum column
                    if ($orderStatus === 'delivered') {
                        $query->where('status', \App\Utility\Enums\OrderStatusEnum::Delivery->value);
                    } else {
                        // For other values, match directly against status enum value
                        $query->where('status', $orderStatus);
                    }
                })
                ->orderByDesc('id');

            // Debug logging
            $totalCount = $query->count();
            Log::info('Order Request List Query', [
                'user_id' => $user->id,
                'user_role' => $user->role?->value ?? null,
                'site_id' => $site_id,
                'site_manager_id' => $site->site_manager_id,
                'order_status_filter' => $orderStatus,
                'total_orders_found' => $totalCount,
                'orders_with_site_manager' => Order::where('site_manager_id', $user->id)->count(),
                'orders_with_site' => Order::where('site_id', $site_id)->count(),
            ]);

            $getOrders = $query->paginate($request->per_page)
                ->appends($request->query());

            return new ApiResponse(
                data: [
                    'orders' => OrderResource::collection($getOrders),
                    'pagination' => new PaginationResource($getOrders),
                ],
                message: ($getOrders->isEmpty()) ? __('api.site-manager.empty_records') : __('api.site-manager.order_list'),
                code: 200,
                isError: false
            );
        } catch (\Illuminate\Validation\ValidationException $e){
            return new ApiErrorResponse(
                ['errors' => $e->errors()],
                'get order request list failed',
                422
            );
        } catch (\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get order request list failed',
                500
            );
        }
    }
    
    public function orderRequestDetails(Request $request, string|int $orderId): ApiResponse|ApiErrorResponse
    {
        try{
            $user = $request->user();
            $role = $user?->getRole();
            $userRole = $role?->value ?? null;
            
            $order = Order::with([
                    'site',
                    'products.category',
                    'products.productImages',
                    'products.materials',
                    'customProducts.images'
                ])
                ->where('id', $orderId)
                ->first();

            if (! $order) {
                return new ApiErrorResponse(
                    ['errors' => ['Order not found']],
                    'Order operation failed',
                    404
                );
            }

            // Use appropriate resource based on user role
            // Store managers should use StoreManagerOrderResource or WorkshopStoreManagerOrderResource
            if ($userRole === RoleEnum::StoreManager->value) {
                $resourceClass = \App\Src\Api\Modules\StoreManagement\Resources\StoreManagerOrderResource::class;
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: new $resourceClass($order),
                    message: __('api.store-manager.order_details'),
                );
            } elseif ($userRole === RoleEnum::WorkshopStoreManager->value) {
                $resourceClass = \App\Src\Api\Modules\StoreManagement\Resources\WorkshopStoreManagerOrderResource::class;
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: new $resourceClass($order),
                    message: __('api.store-manager.order_details'),
                );
            }

            // For other roles (site managers, admin, etc.), use OrderResource
            return new ApiResponse(
                isError: false,
                code: 200,
                data: [new OrderResource($order)],
                message: __('api.site-manager.order_details'),
            );
        } catch (\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get order request details failed',
                500
            );
        }
    }

    public function mySiteList(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $user = $request->user();
            // Site supervisors should only see active sites for order placement
            $sites = Site::where('site_manager_id', $user->id)
                ->where('status', true)
                ->get();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: SiteResource::collection($sites),
                message: ($sites->isEmpty()) ? __('api.site-manager.empty_records') : __('api.site-manager.site_list'),
            );

        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get my site list failed',
                500
            );
        }
    }

    public function storeList(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $orders =  Moderator::whereIn('role', [
                RoleEnum::StoreManager->value,
                RoleEnum::WorkshopStoreManager->value,
            ])->get();
            return new ApiResponse(
                isError: false,
                code: 200,
                data: ModeratorResource::collection($orders),
                message: ($orders->isEmpty()) ? __('api.site-manager.empty_records') : __('api.site-manager.store_list'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get store list failed',
                500
            );
        }
    }

    /**
     * Update order using multipart form-data
     * 
     * This endpoint is specifically designed for multipart/form-data requests
     * with proper file upload handling for customer images and custom product images.
     * 
     * Route: PUT|POST /api/v1/sites/order-request/form-data/update
     * Supports both PUT and POST methods for flexibility.
     * 
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function updateOrderWithFormData(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            // For PUT/POST requests with form-data, use input() which works better for form-data than all()
            $formData = $this->getFormDataFromRequest($request);
            
            // Prepare and normalize form data
            $formData = $this->prepareUpdateFormData($request, $formData);

            // Validate the request
            $validator = Validator::make(
                $formData,
                $this->getUpdateFormDataValidationRules($request)
            );

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'Validation failed',
                    422
                );
            }

            $user = $request->user();
            $orderId = (int) $formData['order_id']; // Ensure integer

            // First check if order exists at all
            $orderExists = Order::where('id', $orderId)->exists();
            
            if (!$orderExists) {
                return new ApiErrorResponse(
                    ['errors' => ['Order not found']],
                    'Order operation failed',
                    404
                );
            }

            // Then check if order belongs to the current user
            $userRole = $user->role?->value ?? null;
            $orderDetails = Order::where('id', $orderId)->first();

            // Ensure order exists and belongs to the current user/role
            Log::info('Order Details', ['orderDetails' => $orderDetails, 'userRole' => $userRole, 'user' => $user]);
            if (!$orderDetails) {
                return new ApiErrorResponse(
                    [],
                    'Order not found or you do not have permission to access this order',
                    404
                );
            }

            // Determine which product_status key we should update for this action
            // Pass user to determine product_status based on logged-in user's role and actual products
            $productStatusType = $this->resolveProductStatusTypeForOrder(
                $orderDetails,
                $formData['type_name'] ?? null,
                $user
            );

            // Get current status for validation using product_status
            $currentProductStatus = $orderDetails->getProductStatus($productStatusType);
            
            // If action_type is provided, handle status change restrictions
            if (!empty($formData['action_type'])) {
                // RESTRICTION: If already approved, cannot change to pending or rejected
                // Can only change to: outfordelivery, in_transit, or delivered
                if (in_array($currentProductStatus, ['approved', 'outfordelivery', 'in_transit', 'delivered'], true)) {
                    // if (in_array($formData['action_type'], ['pending', 'rejected'], true)) {
                    //     return new ApiErrorResponse(
                    //         ['errors' => ['Cannot change status from approved/outfordelivery/in_transit/delivered to pending or rejected. You can only change to outfordelivery, in_transit, or delivered.']],
                    //         'order action failed',
                    //         422
                    //     );
                    // }
                }
            }

            // Update basic order fields
            $updateData = [];
            if (array_key_exists('priority', $formData) && $formData['priority'] !== null) {
                $updateData['priority'] = $formData['priority'];
            }
            if (array_key_exists('note', $formData) && $formData['note'] !== null) {
                $updateData['note'] = $formData['note'];
            }
            if (array_key_exists('expected_delivery_date', $formData) && $formData['expected_delivery_date'] !== null) {
                $updateData['expected_delivery_date'] = $this->parseDeliveryDate($formData['expected_delivery_date']);
            }

            // Process customer image if provided
            if ($request->hasFile('customer_image')) {
                $customerImagePath = $this->processCustomerImage($request);
                if ($customerImagePath) {
                    $updateData['customer_image'] = $customerImagePath;
                }
            }

            // Use product_status to determine if order is approved/in_transit
            $isApprovedOrInTransit = in_array($currentProductStatus, ['approved', 'in_transit'], true);

            $oldProductsData = [];
            if ($isApprovedOrInTransit) {
                $orderDetails->load('products', 'customProducts.images');
                $oldProductsData = $this->extractProductsData($orderDetails);
            }

            // Update order basic fields
            if (!empty($updateData)) {
                $orderDetails->update($updateData);
            }

            // Handle product updates
            $productUpdated = false;
            $productDeleted = false;

            if (!empty($formData['products']) && is_array($formData['products'])) {
                $result = $this->processProductUpdates($request, $orderDetails, $formData['products'], $user);
                $productUpdated = $result['updated'];
                
                // Update product_status and order flags based on current products
                if ($productUpdated) {
                    $this->updateOrderProductStatus($orderDetails);
                }
            }

            // Handle deleted_id - comma-separated product IDs to remove
            $deletionDetails = null;
            if (!empty($formData['deleted_id'])) {
                $deletedIds = $this->parseDeletedIds($formData['deleted_id']);
                
                if (!empty($deletedIds)) {
                    $deleteResult = $this->processProductDeletions($orderDetails, $deletedIds, $isApprovedOrInTransit);
                    $productDeleted = $deleteResult['deleted'];
                    $deletionDetails = $deleteResult;
                    
                    if ($productDeleted) {
                        $productUpdated = true;
                        $orderDetails->refresh();
                        $this->updateOrderProductStatus($orderDetails);
                    }
                }
            }

            // Handle stock adjustments for approved/in_transit orders
            if ($isApprovedOrInTransit && $productUpdated) {
                $orderDetails->refresh();
                $orderDetails->load('products', 'customProducts.images');
                $newProductsData = [];
                
                $newProductsData = $this->extractProductsData($orderDetails);

                if (!empty($oldProductsData) || !empty($newProductsData)) {
                    try {
                        $this->adjustStockForProductChanges($orderDetails, $oldProductsData, $newProductsData, $orderDetails->site_id);
                    } catch (\Exception $e) {
                        Log::error("OrderController: Failed to adjust stock when updating order #{$orderDetails->id}: " . $e->getMessage());
                        throw $e;
                    }
                }
            }

            // Handle action_type if provided (similar to orderAction method)
            if (!empty($formData['action_type'])) {
                $actionType = $formData['action_type'];
                $orderDetails->refresh();
                
                // Get current status again after potential product updates
                $currentProductStatus = $orderDetails->getProductStatus($productStatusType);
                
                if ($actionType === 'delivered') {
                    $error = $this->handleDeliveredAction($orderDetails, $currentProductStatus, $productStatusType);
                    if ($error) {
                        return $error;
                    }
                } else if ($actionType === 'rejected') {
                    $error = $this->handleRejectedAction($orderDetails, $currentProductStatus, $productStatusType, $formData['rejected_note'] ?? '');
                    if ($error) {
                        return $error;
                    }
                } else if ($actionType === 'approved') {
                    $error = $this->handleApprovedAction($orderDetails, $currentProductStatus, $productStatusType);
                    if ($error) {
                        return $error;
                    }
                } else if ($actionType === 'received') {
                    if (empty($formData['store_id'])) {
                        return new ApiErrorResponse(
                            ['errors' => ['Store ID is required for received action.']],
                            'order action failed',
                            422
                        );
                    }
                    $error = $this->handleReceivedAction($orderDetails, $productStatusType, $formData['store_id']);
                    if ($error) {
                        return $error;
                    }
                } else {
                    // Fallback for other action types (e.g., outfordelivery)
                    $this->handleDefaultAction($orderDetails, $actionType, $productStatusType);
                }
            }

            // Reload with relationships for response - use $orderId to ensure correct order
            $orderDetails = Order::with(['site', 'products.category', 'products.productImages', 'customProducts.images'])
                ->where('id', $orderId)
                ->first();

            // Generate response message
            $message = $this->generateUpdateMessage($updateData, $productUpdated, $productDeleted);
            
            // If action_type was provided, update message accordingly
            if (!empty($formData['action_type'])) {
                $actionType = $formData['action_type'];
                $actionMessages = [
                    'delivered' => __('api.site-manager.order_received'),
                    'rejected' => __('api.site-manager.order_rejected'),
                    'approved' => __('api.site-manager.order_approved'),
                    'received' => __('api.site-manager.order_received'),
                    'outfordelivery' => __('api.site-manager.order_approved'),
                ];
                
                if (isset($actionMessages[$actionType])) {
                    if ($productUpdated || $productDeleted || !empty($updateData)) {
                        $message = $actionMessages[$actionType] . ' ' . $message;
                    } else {
                        $message = $actionMessages[$actionType];
                    }
                }
            }
            
            // If deletion was attempted but failed, append details to message
            if (isset($deletionDetails) && !$productDeleted && !empty($deletionDetails['requested_ids'])) {
                $message .= ' Requested product IDs (' . implode(', ', $deletionDetails['requested_ids']) . ') were not found in this order.';
            }

                if($userRole === RoleEnum::StoreManager->value) {
                    return new ApiResponse(
                        isError: false,
                        code: 200,
                        data: StoreManagerOrderResource::collection([$orderDetails]),
                        message: $message
                    );
                }elseif($userRole === RoleEnum::WorkshopStoreManager->value) {   
                    return new ApiResponse(
                        isError: false,
                        code: 200,
                        data: WorkshopStoreManagerOrderResource::collection([$orderDetails]),
                        message: $message
                    );
                }else{
                // Ensure consistent response format - wrap single order in array for collection
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: OrderResource::collection([$orderDetails]),
                    message: $message
                );
            }
        } catch (\Exception $e) {
            Log::error('Order update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['customer_image', 'products'])
            ]);

            // Extract cleaner error message
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Insufficient stock') !== false || strpos($errorMessage, 'Insufficient material stock') !== false) {
                $errorMessage = 'Insufficient stock for some products. Please check the stock availability.';
            } else {
                $errorMessage = 'Failed to update order. Please try again.';
            }

            return new ApiErrorResponse(
                ['errors' => [$errorMessage]],
                'Order update failed',
                500
            );
        }
    }

     public function updateCustomProduct(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $data = $request->all();

            // Convert measurements array to m1, m2, m3 fields before validation
            if (isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
                foreach ($data['products'] as $productIndex => $productData) {
                    if (isset($productData['materials']) && is_array($productData['materials']) && !empty($productData['materials'])) {
                        foreach ($productData['materials'] as $materialIndex => $material) {
                            // If measurements array is provided, convert it to m1, m2, m3 format
                            if (isset($material['measurements']) && is_array($material['measurements'])) {
                                // Convert measurements array [1200, 800] to m1=1200, m2=800, etc.
                                foreach ($material['measurements'] as $idx => $measurementValue) {
                                    $mKey = 'm' . ($idx + 1);
                                    $data['products'][$productIndex]['materials'][$materialIndex][$mKey] = (float) $measurementValue;
                                }
                                // Remove measurements array after conversion
                                unset($data['products'][$productIndex]['materials'][$materialIndex]['measurements']);
                            }
                        }
                    }
                }
            }

            // Check if custom_product_id is provided in products array but not in root
            $hasCustomProductIdInRoot = isset($data['custom_product_id']) || isset($data['order_custom_product_id']);
            $hasCustomProductIdInProducts = false;
            if (isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
                // Check if any product has custom_product_id
                foreach ($data['products'] as $product) {
                    if (isset($product['custom_product_id'])) {
                        $hasCustomProductIdInProducts = true;
                        break;
                    }
                }
            }

            // Build base validation rules
            $rules = [
                'order_id' => 'required|integer|exists:orders,id',
                'priority' => ['sometimes', 'nullable', Rule::enum(PriorityEnum::class)],
                'note' => 'sometimes|nullable|string',
                'expected_delivery_date' => 'sometimes|nullable|date_format:d/m/Y',
                // Support products array format
                'products' => 'sometimes|array|min:1',
                'products.*.is_custom' => 'sometimes|nullable|boolean',
                'products.*.product_id' => 'sometimes|nullable|integer|exists:products,id',
                'products.*.actual_pcs' => 'sometimes|nullable|numeric|min:0',
                'products.*.quantity' => 'sometimes|nullable|numeric|min:0',
                'products.*.calqty' => 'sometimes|nullable|numeric|min:0',
                'products.*.unit_id' => 'sometimes|nullable|integer|exists:units,id',
                'products.*.custom_note' => 'sometimes|nullable|string',
                'products.*.custom_product_id' => 'sometimes|nullable|integer|exists:order_custom_products,id',
                // Materials validation
                'products.*.materials' => 'sometimes|nullable|array',
                // For individual updates, custom_product_id is required (either in root or products array)
                'order_custom_product_id' => ($hasCustomProductIdInRoot || $hasCustomProductIdInProducts) ? 'nullable' : 'required|integer|exists:order_custom_products,id',
                'custom_product_id' => ($hasCustomProductIdInRoot || $hasCustomProductIdInProducts) ? 'nullable' : 'required|integer|exists:order_custom_products,id', // Legacy support
            ];

            // Dynamically add validation rules for materials fields (actual_pcs, cal_qty, calqty, m1, m2, m3...)
            if (isset($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as $productIndex => $product) {
                    if (isset($product['materials']) && is_array($product['materials'])) {
                        foreach ($product['materials'] as $materialIndex => $material) {
                            // Validate material_id, actual_pcs, cal_qty, calqty
                            // Materials are stored in products table with is_product IN (0, 2)
                            $rules["products.{$productIndex}.materials.{$materialIndex}.material_id"] = [
                                'sometimes',
                                'nullable',
                                'integer',
                                Rule::exists('products', 'id')->where(function ($query) {
                                    $query->whereIn('is_product', [0, 2]);
                                })
                            ];
                            $rules["products.{$productIndex}.materials.{$materialIndex}.actual_pcs"] = 'sometimes|nullable|numeric|min:0';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.cal_qty"] = 'sometimes|nullable|numeric|min:0';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.calqty"] = 'sometimes|nullable|numeric|min:0';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.measurements"] = 'sometimes|nullable|array';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.measurements.*"] = 'sometimes|nullable|numeric|min:0';
                            
                            // Dynamically validate m* fields (m1, m2, m3, etc.)
                            foreach ($material as $key => $value) {
                                if (preg_match('/^m\d+$/', $key)) {
                                    $rules["products.{$productIndex}.materials.{$materialIndex}.{$key}"] = 'sometimes|nullable|numeric|min:0';
                                }
                            }
                        }
                    }
                }
            }

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'Validation failed',
                    422
                );
            }

            // Ensure custom_product_id is set after validation (from root or products array)
            $customProductIdRaw = $data['order_custom_product_id'] ?? $data['custom_product_id'] ?? null;
            
            // If not in root, try to get from products array
            if (!$customProductIdRaw && isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
                foreach ($data['products'] as $product) {
                    if (isset($product['custom_product_id'])) {
                        $customProductIdRaw = $product['custom_product_id'];
                        break;
                    }
                }
            }
            
            $customProductId = $customProductIdRaw ? (int) $customProductIdRaw : null;
            
            // For individual updates, order_custom_product_id is required
            if (! $customProductId) {
                return new ApiErrorResponse(
                    ['order_custom_product_id' => ['order_custom_product_id is required for updates']],
                    'Validation failed',
                    422
                );
            }

            $user = $request->user();
            $orderId = (int) $data['order_id'];

            // Validate order_id is not empty or zero
            if (empty($orderId) || $orderId <= 0) {
                return new ApiErrorResponse(
                    ['order_id' => ['Order ID is required and must be a positive integer']],
                    'Validation failed',
                    422
                );
            }

            // Ensure order exists and belongs to current manager (site or store)
            $order = Order::where('id', $orderId)->first();
            if (! $order) {
                return new ApiErrorResponse(
                    ['errors' => ['Order not found']],
                    'order operation failed',
                    404
                );
            }

            // Update basic order fields (priority, note, expected_delivery_date)
            $updateData = [];
            if (array_key_exists('priority', $data) && $data['priority'] !== null) {
                $updateData['priority'] = $data['priority'];
            }
            if (array_key_exists('note', $data) && $data['note'] !== null) {
                $updateData['note'] = $data['note'];
            }
            if (array_key_exists('expected_delivery_date', $data) && $data['expected_delivery_date'] !== null) {
                $updateData['expected_delivery_date'] = $this->parseDeliveryDate($data['expected_delivery_date']);
            }
            if (! empty($updateData)) {
                $order->update($updateData);
            }

            // Check if custom product exists
            $existingCustomProduct = OrderCustomProduct::where('id', $customProductId)->first();
            
            if (!$existingCustomProduct) {
                return new ApiErrorResponse(
                    ['order_custom_product_id' => ['Custom product with ID ' . $customProductId . ' does not exist.']],
                    'Custom product not found.',
                    404
                );
            }

            // Use the existing custom product's order_id for order_products operations
            $customProductOrderId = $existingCustomProduct->order_id;

            // Process products array - handle multiple products
            $productsToProcess = [];
            $allProductIds = [];
            $updatePayload = [];
            
            // Get existing product_details from custom product
            $existingProductDetails = $existingCustomProduct->product_details ?? [];
            $existingProductIds = [];
            if (isset($existingProductDetails['product_id'])) {
                if (is_array($existingProductDetails['product_id'])) {
                    $existingProductIds = $existingProductDetails['product_id'];
                } else {
                    $existingProductIds = [$existingProductDetails['product_id']];
                }
            }

            // Process products array if provided
            if (isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
                foreach ($data['products'] as $productIndex => $productData) {
                    
                    // CASE 1: If this product has materials, calculate calqty from materials
                    // Formula per material line: (sum of all m* fields) * actual_pcs
                    if (
                        isset($productData['materials'])
                        && is_array($productData['materials'])
                        && !empty($productData['materials'])
                    ) {
                        $productTotalQuantity = 0;

                        // Normalize materials array: convert pcs to actual_pcs for storage
                        $normalizedMaterials = [];
                        foreach ($productData['materials'] as $material) {
                            // Use actual_pcs (support pcs for backward compatibility in materials)
                            $mpcs = 0;
                            if (isset($material['actual_pcs']) && $material['actual_pcs'] !== '') {
                                $mpcs = (int) $material['actual_pcs'];
                            } elseif (isset($material['pcs']) && $material['pcs'] !== '') {
                                $mpcs = (int) $material['pcs'];
                            }

                            $lineQuantity = 0;
                            $sumOfMeasurements = 0;

                            // Priority 1: Calculate from measurements array if provided
                            if (isset($material['measurements']) && is_array($material['measurements']) && !empty($material['measurements'])) {
                                // Sum all values in measurements array
                                foreach ($material['measurements'] as $measurement) {
                                    if (is_numeric($measurement)) {
                                        $sumOfMeasurements += (float) $measurement;
                                    }
                                }
                                // Calculate quantity: (sum of measurements) * actual_pcs
                                $lineQuantity = $sumOfMeasurements * $mpcs;
                            } else {
                                // Priority 2: Sum all m* fields (m1, m2, m3, etc.) dynamically
                                $sumOfMFields = 0;
                                $mFields = [];
                                foreach ($material as $key => $value) {
                                    // Match m* fields (m1, m2, m3, m10, m99, etc.)
                                    if (preg_match('/^m\d+$/', $key)) {
                                        $mValue = isset($value) && $value !== '' ? (float) $value : 0;
                                        $sumOfMFields += $mValue;
                                        $mFields[$key] = $mValue;
                                    }
                                }

                                // Calculate quantity: (sum of all m* fields) * actual_pcs
                                $lineQuantity = $sumOfMFields * $mpcs;
                            }

                            $productTotalQuantity += $lineQuantity;

                            // Normalize material: store material_id, actual_pcs, cal_qty, and measurements/m* fields
                            $normalizedMaterial = [
                                'material_id' => $material['material_id'] ?? null,
                                'actual_pcs' => $mpcs,
                                'cal_qty' => $lineQuantity, // Always store calculated quantity
                            ];
                            
                            // If cal_qty or calqty is explicitly provided, use it (but we already calculated above)
                            // This allows override if needed
                            if (isset($material['cal_qty']) && $material['cal_qty'] !== '') {
                                $normalizedMaterial['cal_qty'] = (float) $material['cal_qty'];
                            } elseif (isset($material['calqty']) && $material['calqty'] !== '') {
                                $normalizedMaterial['cal_qty'] = (float) $material['calqty'];
                            }
                            
                            // Add measurements array if provided
                            if (isset($material['measurements']) && is_array($material['measurements'])) {
                                $normalizedMaterial['measurements'] = $material['measurements'];
                            }
                            
                            // Add all m* fields to normalized material (if measurements not used)
                            if (!isset($material['measurements']) || empty($material['measurements'])) {
                                $mFields = [];
                                foreach ($material as $key => $value) {
                                    if (preg_match('/^m\d+$/', $key)) {
                                        $mValue = isset($value) && $value !== '' ? (float) $value : 0;
                                        $mFields[$key] = $mValue;
                                    }
                                }
                                foreach ($mFields as $mKey => $mValue) {
                                    $normalizedMaterial[$mKey] = $mValue;
                                }
                            }
                            
                            $normalizedMaterials[] = $normalizedMaterial;
                        }
                        
                        // Replace materials array with normalized version
                        $productData['materials'] = $normalizedMaterials;

                        // Expose the calculated total so the rest of the logic
                        // (calqty/quantity handling) remains backwards compatible
                        $productData['calqty'] = $productTotalQuantity;
                    }

                    // Map calqty → quantity if quantity is not already set
                    if (isset($productData['calqty']) && !isset($productData['quantity'])) {
                        $productData['quantity'] = $productData['calqty'];
                    }

                    // Collect product_id if provided (even if no materials)
                    if (isset($productData['product_id']) && $productData['product_id'] !== null && $productData['product_id'] !== '') {
                        $productId = (int) $productData['product_id'];
                        $allProductIds[] = $productId;
                        
                        // Store product data with quantity for order_products sync
                        // If materials were provided, calqty is already calculated; otherwise use provided quantity
                        $productQuantity = $productData['calqty'] ?? $productData['quantity'] ?? 0;
                        
                        $productsToProcess[$productId] = [
                            'product_id' => $productId,
                            'quantity' => $productQuantity,
                        ];
                    }

                    // Merge product fields into update payload (use first product's data for custom product)
                    if (empty($updatePayload)) {
                        // Include materials so they can be stored in product_details JSON
                        $fieldsToMerge = ['actual_pcs', 'quantity', 'calqty', 'unit_id', 'custom_note', 'materials'];
                        foreach ($fieldsToMerge as $field) {
                            if (isset($productData[$field]) && $productData[$field] !== null && $productData[$field] !== '') {
                                $updatePayload[$field] = $productData[$field];
                            }
                        }
                        
                        // Map calqty to quantity if calqty is provided
                        if (isset($productData['calqty']) && !isset($updatePayload['quantity'])) {
                            $updatePayload['quantity'] = $productData['calqty'];
                        }
                    }
                }
            } else {
                // Handle root level fields (backward compatibility)
                // Map calqty → quantity if quantity is not already set
                if (array_key_exists('calqty', $data) && !array_key_exists('quantity', $data)) {
                    $data['quantity'] = $data['calqty'];
                }

                $allowedFields = ['product_id', 'actual_pcs', 'quantity', 'calqty', 'custom_note'];
                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                        $updatePayload[$field] = $data[$field];
                    }
                }
                
                // Map calqty to quantity if calqty is provided
                if (isset($data['calqty']) && !isset($updatePayload['quantity'])) {
                    $updatePayload['quantity'] = $data['calqty'];
                }

                // Handle product_id from root level
                if (isset($updatePayload['product_id']) && $updatePayload['product_id'] !== null && $updatePayload['product_id'] !== '') {
                    $productIdRaw = $updatePayload['product_id'];
                    if (is_array($productIdRaw)) {
                        $allProductIds = array_map('intval', array_filter($productIdRaw));
                    } elseif (is_string($productIdRaw) && strpos($productIdRaw, ',') !== false) {
                        $allProductIds = array_map('intval', array_filter(array_map('trim', explode(',', $productIdRaw))));
                    } else {
                        $allProductIds = [(int) $productIdRaw];
                    }
                    
                    foreach ($allProductIds as $productId) {
                        $productsToProcess[$productId] = [
                            'product_id' => $productId,
                            'quantity' => $updatePayload['calqty'] ?? $updatePayload['quantity'] ?? 0,
                        ];
                    }
                }
            }

            // Validate all product IDs exist
            if (!empty($allProductIds)) {
                $validProductIds = DB::table('products')
                    ->whereIn('id', $allProductIds)
                    ->pluck('id')
                    ->toArray();

                $invalidIds = array_diff($allProductIds, $validProductIds);
                if (!empty($invalidIds)) {
                    return new ApiErrorResponse(
                        ['product_id' => ['Invalid product IDs: ' . implode(', ', $invalidIds)]],
                        'Validation failed',
                        422
                    );
                }
            }

            // Get all existing order_products for this order (to check what exists)
            $allExistingOrderProducts = DB::table('order_products')
                ->where('order_id', $customProductOrderId)
                ->get()
                ->keyBy('product_id')
                ->toArray();

            // Get the original product IDs from this custom product before update
            // This helps us track which products were previously in this custom product
            $originalCustomProductIds = [];
            $originalCustomProductQuantities = [];
            if ($existingCustomProduct) {
                $originalProductIds = $existingCustomProduct->getAllProductIds();
                $originalCustomProductIds = $originalProductIds;
                
                // For each original product, get the quantity that this custom product contributed
                // We'll use this to properly replace quantities when updating
                $originalProductDetails = $existingCustomProduct->product_details ?? [];
                $originalProductDetailsQty = (int) ($originalProductDetails['quantity'] ?? 0);
                
                foreach ($originalProductIds as $originalProductId) {
                    // For single product custom products, use product_details quantity
                    // For multiple products, we'll need to estimate or use order_products as fallback
                    $productDetailsProductId = $originalProductDetails['product_id'] ?? null;
                    
                    if ($productDetailsProductId && !is_array($productDetailsProductId) && $productDetailsProductId == $originalProductId) {
                        // Single product - use product_details quantity directly
                        $originalCustomProductQuantities[$originalProductId] = $originalProductDetailsQty;
                    } else {
                        // Multiple products - check if we can get from order_products
                        // This is an approximation: if product exists, assume it was added by this custom product
                        // In a perfect world, we'd track per-product quantities, but for now this works
                        $originalOrderProduct = $allExistingOrderProducts[$originalProductId] ?? null;
                        if ($originalOrderProduct) {
                            // Store current quantity as what this custom product contributed
                            // Note: This assumes the product was added by this custom product
                            $originalCustomProductQuantities[$originalProductId] = (int) ($originalOrderProduct->quantity ?? 0);
                        } else {
                            $originalCustomProductQuantities[$originalProductId] = 0;
                        }
                    }
                }
            }

            // Sync order_products table
            // 1. Add or update products that are in the request
            foreach ($productsToProcess as $productId => $productInfo) {
                $existingOrderProduct = $allExistingOrderProducts[$productId] ?? null;
                $newQuantity = (int) ($productInfo['quantity'] ?? 0);

                if ($existingOrderProduct) {
                    // Product already exists in order_products
                    $existingQuantity = (int) ($existingOrderProduct->quantity ?? 0);
                    $wasInOriginalCustomProduct = in_array($productId, $originalCustomProductIds);
                    $originalCustomQty = $originalCustomProductQuantities[$productId] ?? 0;
                    
                    if ($wasInOriginalCustomProduct && $originalCustomQty > 0) {
                        // This product was previously in this custom product
                        // Replace: subtract the old quantity that was added by this custom product, add new quantity
                        // We assume the originalCustomQty represents what this custom product contributed
                        // Calculate base quantity (what exists outside of this custom product)
                        $baseQuantity = max(0, $existingQuantity - $originalCustomQty);
                        $totalQuantity = $baseQuantity + $newQuantity;
                    } else {
                        // This product exists from regular order (or wasn't in this custom product before)
                        // Add the new custom product quantity to existing quantity
                        $totalQuantity = $existingQuantity + $newQuantity;
                    }
                    
                    // Update existing order_product quantity with aggregated total
                    DB::table('order_products')
                        ->where('order_id', $customProductOrderId)
                        ->where('product_id', $productId)
                        ->update([
                            'quantity' => $totalQuantity,
                            'updated_at' => now(),
                        ]);
                } else {
                    // Create new order_product entry
                    DB::table('order_products')->insert([
                        'order_id' => $customProductOrderId,
                        'product_id' => $productId,
                        'quantity' => $newQuantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 2. Remove products that were previously linked to this custom product but are NOT in the request
            // Only remove if products array was provided with product_ids (meaning we want to sync/replace)
            // This handles the case where user wants to replace old products with new ones
            if (isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
                // Check if any product in the array has product_id (meaning we're syncing products)
                $hasProductIdsInRequest = false;
                foreach ($data['products'] as $product) {
                    if (isset($product['product_id']) && $product['product_id'] !== null && $product['product_id'] !== '') {
                        $hasProductIdsInRequest = true;
                        break;
                    }
                }
                
                // Only remove if we have product_ids in the request (indicating we want to replace)
                if ($hasProductIdsInRequest && !empty($existingProductIds)) {
                    $productsToKeep = array_keys($productsToProcess);
                    $productsToRemove = array_diff($existingProductIds, $productsToKeep);
                    
                    // Remove products that were in the old list but not in the new list
                    // Subtract the quantity that was added by this custom product
                    if (!empty($productsToRemove)) {
                        foreach ($productsToRemove as $productIdToRemove) {
                            $wasInOriginalCustomProduct = in_array($productIdToRemove, $originalCustomProductIds);
                            $originalCustomQty = $originalCustomProductQuantities[$productIdToRemove] ?? 0;
                            
                            if ($wasInOriginalCustomProduct && $originalCustomQty > 0) {
                                // Get current quantity in order_products
                                $currentOrderProduct = DB::table('order_products')
                                    ->where('order_id', $customProductOrderId)
                                    ->where('product_id', $productIdToRemove)
                                    ->first();
                                
                                if ($currentOrderProduct) {
                                    $currentQuantity = (int) ($currentOrderProduct->quantity ?? 0);
                                    // Subtract the quantity that was added by this custom product
                                    $newQuantity = max(0, $currentQuantity - $originalCustomQty); // Don't go below 0
                                    
                                    // Check if this product is used by other custom products
                                    $otherCustomProducts = OrderCustomProduct::where('order_id', $customProductOrderId)
                                        ->where('id', '!=', $customProductId)
                                        ->get();
                                    
                                    $isUsedByOthers = false;
                                    foreach ($otherCustomProducts as $otherCustomProduct) {
                                        $otherProductIds = $otherCustomProduct->getAllProductIds();
                                        if (in_array($productIdToRemove, $otherProductIds)) {
                                            $isUsedByOthers = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($newQuantity > 0) {
                                        // Update quantity (subtract the custom product quantity)
                                        DB::table('order_products')
                                            ->where('order_id', $customProductOrderId)
                                            ->where('product_id', $productIdToRemove)
                                            ->update([
                                                'quantity' => $newQuantity,
                                                'updated_at' => now(),
                                            ]);
                                    } else {
                                        // Quantity would be 0 or negative
                                        // Only delete if not used by other custom products
                                        if (!$isUsedByOthers) {
                                            DB::table('order_products')
                                                ->where('order_id', $customProductOrderId)
                                                ->where('product_id', $productIdToRemove)
                                                ->delete();
                                        } else {
                                            // Keep it but set to 0 (other custom product might add to it later)
                                            DB::table('order_products')
                                                ->where('order_id', $customProductOrderId)
                                                ->where('product_id', $productIdToRemove)
                                                ->update([
                                                    'quantity' => 0,
                                                    'updated_at' => now(),
                                                ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Update product_id in update payload (for product_details)
            if (!empty($allProductIds)) {
                $updatePayload['product_id'] = count($allProductIds) > 1 ? $allProductIds : $allProductIds[0];
            }

            // Also update product_ids column (separate column, like admin panel)
            if (array_key_exists('product_ids', $data)) {
                $updatePayload['product_ids'] = $data['product_ids'];
            } elseif (!empty($allProductIds)) {
                // If product_ids not explicitly provided but we have product IDs from products array, use them
                $updatePayload['product_ids'] = $allProductIds;
            }

            // If no fields to update, return error
            if (empty($updatePayload) && empty($allProductIds)) {
                return new ApiErrorResponse(
                    [],
                    'No fields provided to update.',
                    422
                );
            }

            // Update the custom product with only the provided fields
            try {
                $this->customProductManager->update(
                    $existingCustomProduct->id,
                    $updatePayload,
                    null,
                    null
                );
            } catch (\Exception $e) {
                Log::error("Failed to update custom product {$customProductId}: " . $e->getMessage());
                return new ApiErrorResponse(
                    [],
                    'Failed to update custom product: ' . $e->getMessage(),
                    500
                );
            }

            // Reload order with relationships for response (use custom product's order_id)
            $order = Order::with(['site', 'products.category', 'products.productImages', 'customProducts.images'])
                ->where('id', $customProductOrderId)
                ->first();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: new OrderResource($order),
                message: 'Custom product updated successfully.',
            );
        } catch (\Exception $e) {
            Log::error('Custom product update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return new ApiErrorResponse(
                [],
                'Failed to update custom product: ' . $e->getMessage(),
                500
            );
        }
    }


    /**
     * Store custom products for an order
     * 
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function storeCustomProduct(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $data = $request->all();

            // Build base validation rules
            $rules = [
                'order_id' => 'required|integer|exists:orders,id',
                'products' => 'required|array|min:1',
                'products.*.is_custom' => 'required|boolean',
                'products.*.product_id' => 'sometimes|nullable|integer|exists:products,id',
                'products.*.actual_pcs' => 'sometimes|nullable|numeric|min:0',
                'products.*.unit_id' => 'sometimes|nullable|integer|exists:units,id',
                'products.*.custom_note' => 'sometimes|nullable|string',
                // Materials validation
                'products.*.materials' => 'sometimes|nullable|array',
            ];

            // Dynamically add validation rules for materials fields (actual_pcs, cal_qty, calqty, m1, m2, m3...)
            if (isset($data['products']) && is_array($data['products'])) {
                foreach ($data['products'] as $productIndex => $product) {
                    if (isset($product['materials']) && is_array($product['materials'])) {
                        foreach ($product['materials'] as $materialIndex => $material) {
                            // Validate material_id, actual_pcs, cal_qty, calqty
                            // Materials are stored in products table with is_product IN (0, 2)
                            $rules["products.{$productIndex}.materials.{$materialIndex}.material_id"] = [
                                'sometimes',
                                'nullable',
                                'integer',
                                Rule::exists('products', 'id')->where(function ($query) {
                                    $query->whereIn('is_product', [0, 2]);
                                })
                            ];
                            $rules["products.{$productIndex}.materials.{$materialIndex}.actual_pcs"] = 'sometimes|nullable|numeric|min:0';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.cal_qty"] = 'sometimes|nullable|numeric|min:0';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.measurements"] = 'sometimes|nullable|array';
                            $rules["products.{$productIndex}.materials.{$materialIndex}.measurements.*"] = 'sometimes|nullable|numeric|min:0';
                            
                            // Dynamically validate m* fields (m1, m2, m3, etc.)
                            foreach ($material as $key => $value) {
                                if (preg_match('/^m\d+$/', $key)) {
                                    $rules["products.{$productIndex}.materials.{$materialIndex}.{$key}"] = 'sometimes|nullable|numeric|min:0';
                                }
                            }
                        }
                    }
                }
            }

            // Validate the request
            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'Validation failed',
                    422
                );
            }

            $orderId = (int) $data['order_id'];

            // Verify order exists
            $order = Order::find($orderId);
            if (!$order) {
                return new ApiErrorResponse(
                    ['errors' => ['Order not found']],
                    'order operation failed',
                    404
                );
            }

            $createdProducts = [];

            // Process each product
            foreach ($data['products'] as $index => $productData) {
                // Ensure is_custom is 1
                if (!isset($productData['is_custom']) || (int) $productData['is_custom'] !== 1) {
                    continue;
                }

                // Collect and store custom images
                $customImages = $this->collectCustomImages($request, $productData, $index);
                $customImagePaths = $this->storeCustomImages($customImages);

                // Create custom product using OrderCustomProductManager
                $customProduct = $this->customProductManager->create(
                    $orderId,
                    $productData,
                    $customImagePaths
                );

                $createdProducts[] = $customProduct;
            }

            // Fetch all custom products for this order (including the newly created ones)
            $customProducts = OrderCustomProduct::where('order_id', $orderId)
                ->with('images')
                ->get();

            // Format the response
            $formattedProducts = [];
            foreach ($customProducts as $customProduct) {
                $productDetails = $customProduct->product_details ?? [];
                $imageUrls = [];

                // Get image URLs
                foreach ($customProduct->images as $image) {
                    if ($image->image_path) {
                        if (preg_match('#^https?://#i', $image->image_path)) {
                            $imageUrls[] = $image->image_path;
                        } else {
                            $imageUrls[] = Storage::disk('public')->exists($image->image_path)
                                ? url(Storage::url($image->image_path))
                                : url('storage/' . $image->image_path);
                        }
                    }
                }

                $formattedProducts[] = [
                    'id' => $customProduct->id,
                    'custom_product_id' => $customProduct->id,
                    'order_id' => $customProduct->order_id,
                    'is_custom' => 1,
                    'product_id' => $productDetails['product_id'] ?? null,
                    'product_name' => $customProduct->product?->product_name ?? 'Custom Product',
                    'actual_pcs' => $productDetails['actual_pcs'] ?? null,
                    'quantity' => $productDetails['quantity'] ?? null,
                    'unit_id' => $productDetails['unit_id'] ?? null,
                    'unit_type' => $customProduct->unit?->name ?? null,
                    'custom_note' => $customProduct->custom_note ?? null,
                    'custom_images' => $imageUrls,
                    'product_details' => $productDetails, // Include full JSON for reference
                ];
            }

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [
                    'order_id' => $orderId,
                    'custom_products' => $formattedProducts,
                ],
                message: 'Custom products stored successfully',
            );
        } catch (\Exception $e) {
            Log::error('Custom product store failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return new ApiErrorResponse(
                [],
                'Failed to store custom product: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get custom products by order_id
     * 
     * @param Request $request
     * @param string|int $orderId
     * @return ApiResponse|ApiErrorResponse
     */
    public function getCustomProducts(Request $request, string|int $orderId): ApiResponse|ApiErrorResponse
    {
        try {
            // Cast to int for database query
            $orderId = (int) $orderId;
            
            // Verify order exists
            $order = Order::find($orderId);
            if (!$order) {
                return new ApiErrorResponse(
                    ['errors' => ['Order not found']],
                    'order operation failed',
                    404
                );
            }

            // Fetch all custom products for this order
            $customProducts = OrderCustomProduct::where('order_id', $orderId)
                ->with('images')
                ->get();

            // Format the response
            $formattedProducts = [];
            foreach ($customProducts as $customProduct) {
                $productDetails = $customProduct->product_details ?? [];
                $imageUrls = [];

                // Get image URLs
                foreach ($customProduct->images as $image) {
                    if ($image->image_path) {
                        if (preg_match('#^https?://#i', $image->image_path)) {
                            $imageUrls[] = $image->image_path;
                        } else {
                            $imageUrls[] = Storage::disk('public')->exists($image->image_path)
                                ? url(Storage::url($image->image_path))
                                : url('storage/' . $image->image_path);
                        }
                    }
                }

                $formattedProducts[] = [
                    'id' => $customProduct->id,
                    'custom_product_id' => $customProduct->id,
                    'order_id' => $customProduct->order_id,
                    'is_custom' => 1,
                    'product_id' => $productDetails['product_id'] ?? null,
                    'product_name' => $customProduct->product?->product_name ?? 'Custom Product',
                    'actual_pcs' => $productDetails['actual_pcs'] ?? null,
                    'calQty' => $cusQty ?? null,
                    'unit_id' => $productDetails['unit_id'] ?? null,
                    'unit_type' => $customProduct->unit?->name ?? null,
                    'custom_note' => $customProduct->custom_note ?? null,
                    'custom_images' => $imageUrls,
                    'product_details' => $productDetails, // Include full JSON for reference
                ];
            }

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [
                    'order_id' => $orderId,
                    'custom_products' => $formattedProducts,
                ],
                message: count($formattedProducts) > 0 ? 'Custom products retrieved successfully' : 'No custom products found',
            );
        } catch (\Exception $e) {
            Log::error('Get custom products failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'order_id' => $orderId,
            ]);

            return new ApiErrorResponse(
                [],
                'Failed to get custom products: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Find existing custom product matching product_id and dimensions
     *
     * @param int $orderId
     * @param int|null $productId
     * @param array $productPayload
     * @return OrderCustomProduct|null
     */
    private function findMatchingCustomProduct(int $orderId, ?int $productId, array $productPayload): ?OrderCustomProduct
    {
        // Get all custom products for this order
        $customProducts = OrderCustomProduct::where('order_id', $orderId)->get();

        // Extract actual_pcs from payload
        $pcs = isset($productPayload['actual_pcs']) ?  (float) $productPayload['actual_pcs'] : null;

        foreach ($customProducts as $customProduct) {
            $details = $customProduct->product_details ?? [];
            
            // Match product_id
            $existingProductId = $details['product_id'] ?? null;
            if ($productId !== null && (int) $existingProductId !== $productId) {
                continue;
            }
            if ($productId === null && $existingProductId !== null) {
                continue;
            }

            // Existing quantity in product_details is computed; for matching, compare pieces (actual_pcs)
            $existingPcs = isset($details['actual_pcs']) ?  (int) $details['actual_pcs'] : null;

            // Compare actual_pcs (handle null values)
            if ($pcs !== $existingPcs) {
                continue;
            }

            // Found a match
            return $customProduct;
        }

        return null;
    }

    /**
     * Get form data from request (handles PUT/PATCH form-data properly)
     */
    private function getFormDataFromRequest(Request $request): array
    {
        // Try input() first (works for POST requests)
        $data = $request->input();
        
        // If empty, try all() (works for POST)
        if (empty($data)) {
            $data = $request->all();
        }
        
        // Try request parameter bag (sometimes has data for PUT)
        if (empty($data) && method_exists($request, 'request')) {
            $requestBag = $request->request->all();
            if (!empty($requestBag)) {
                $data = $requestBag;
            }
        }
        
        // For PUT/PATCH requests with multipart/form-data, Laravel doesn't parse automatically
        // We need to manually parse the multipart data
        if (empty($data) && in_array($request->method(), ['PUT', 'PATCH'])) {
            $contentType = $request->header('Content-Type', '');
            
            if (strpos($contentType, 'multipart/form-data') !== false) {
                // Parse multipart/form-data manually
                $parsed = $this->parseMultipartFormData($request);
                if (!empty($parsed)) {
                    $data = $parsed;
                }
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                // Parse form-urlencoded
                parse_str($request->getContent(), $parsed);
                if (!empty($parsed)) {
                    $data = $parsed;
                }
            }
        }
        
        return $data;
    }

    /**
     * Parse multipart/form-data manually for PUT/PATCH requests
     */
    private function parseMultipartFormData(Request $request): array
    {
        $data = [];
        
        try {
            // Use Symfony's Request to parse multipart data
            $symfonyRequest = $request->duplicate();
            
            // Try to get parsed data from Symfony's ParameterBag
            // For PUT requests, we need to manually parse
            $content = $request->getContent();
            
            if (empty($content)) {
                return $data;
            }
            
            // Get boundary from Content-Type header
            $contentType = $request->header('Content-Type', '');
            preg_match('/boundary=(.+?)(?:\s|$)/', $contentType, $matches);
            
            if (empty($matches[1])) {
                return $data;
            }
            
            $boundary = '--' . trim($matches[1]);
            $parts = explode($boundary, $content);
            
            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part) || $part === '--') {
                    continue;
                }
                
                // Split headers and body
                $headerEnd = strpos($part, "\r\n\r\n");
                if ($headerEnd === false) {
                    $headerEnd = strpos($part, "\n\n");
                    if ($headerEnd === false) {
                        continue;
                    }
                    $headers = substr($part, 0, $headerEnd);
                    $body = substr($part, $headerEnd + 2);
                } else {
                    $headers = substr($part, 0, $headerEnd);
                    $body = substr($part, $headerEnd + 4);
                }
                
                // Extract field name from Content-Disposition header
                if (preg_match('/name="([^"]+)"/', $headers, $nameMatches)) {
                    $fieldName = $nameMatches[1];
                    $value = rtrim($body, "\r\n");
                    
                    // Skip file uploads (they have filename in header)
                    if (strpos($headers, 'filename=') !== false) {
                        continue; // Files are handled separately
                    }
                    
                    // Handle nested array notation (e.g., products[0][product_id])
                    if (preg_match('/^(.+?)\[(\d+)\]\[(.+?)\]$/', $fieldName, $arrayMatches)) {
                        $arrayName = $arrayMatches[1];
                        $index = (int)$arrayMatches[2];
                        $key = $arrayMatches[3];
                        
                        if (!isset($data[$arrayName])) {
                            $data[$arrayName] = [];
                        }
                        if (!isset($data[$arrayName][$index])) {
                            $data[$arrayName][$index] = [];
                        }
                        $data[$arrayName][$index][$key] = $value;
                    } elseif (preg_match('/^(.+?)\[(\d+)\]$/', $fieldName, $arrayMatches)) {
                        // products[0] format
                        $arrayName = $arrayMatches[1];
                        $index = (int)$arrayMatches[2];
                        
                        if (!isset($data[$arrayName])) {
                            $data[$arrayName] = [];
                        }
                        $data[$arrayName][$index] = $value;
                    } else {
                        // Simple field
                        $data[$fieldName] = $value;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse multipart form-data: ' . $e->getMessage());
        }
        
        return $data;
    }

    /**
     * Prepare and normalize form data for update request
     */
    private function prepareUpdateFormData(Request $request, array $formData = null): array
    {
        // Use provided formData or get from request
        if ($formData === null) {
            $data = $this->getFormDataFromRequest($request);
        } else {
            $data = $formData;
        }

        // Normalize field names: map notes → note (for consistency with order creation)
        if (array_key_exists('notes', $data) && !array_key_exists('note', $data)) {
            $data['note'] = $data['notes'];
        }

        // Ensure products is an array if provided
        if (isset($data['products']) && !is_array($data['products'])) {
            $data['products'] = [];
        }

        // Normalize products array
        if (!empty($data['products']) && is_array($data['products'])) {
            foreach ($data['products'] as $index => $product) {
                if (!is_array($product)) {
                    continue;
                }

                // Update product reference
                $product = $data['products'][$index];

                // Normalize is_custom
                $isCustom = filter_var(
                    $product['is_custom'] ?? null,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );

                if ($isCustom === null) {
                    $hasCustomFields = !empty($product['custom_note']) || 
                                      !empty($product['custom_images']) ||
                                      !empty($product['product_id']) || // Custom can have product_id
                                      !empty($product['actual_pcs']) ||
                                      !empty($product['quantity']) ||
                                      $this->hasCustomImageFiles($request, $index);
                    $isCustom = $hasCustomFields;
                }

                $data['products'][$index]['is_custom'] = $isCustom ? 1 : 0;

                // Ensure custom_images is array if provided
                if (isset($product['custom_images']) && !is_array($product['custom_images'])) {
                    $data['products'][$index]['custom_images'] = [$product['custom_images']];
                }

                // Parse existing_images - supports: JSON string, comma-separated string, or array
                if (isset($product['existing_images'])) {
                    $existingImages = $product['existing_images'];
                    
                    if (is_array($existingImages)) {
                        // Already array - check if first element is comma-separated or JSON
                        $firstElement = $existingImages[0] ?? null;
                        if ($firstElement && is_string($firstElement)) {
                            // Check if JSON array
                            if (str_starts_with(trim($firstElement), '[')) {
                                $decoded = json_decode($firstElement, true);
                                if (is_array($decoded)) {
                                    $existingImages = $decoded;
                                }
                            }
                            // Check if comma-separated
                            elseif (str_contains($firstElement, ',')) {
                                $existingImages = array_map('trim', explode(',', $firstElement));
                            }
                        }
                    } elseif (is_string($existingImages)) {
                        // Check if JSON array
                        if (str_starts_with(trim($existingImages), '[')) {
                            $decoded = json_decode($existingImages, true);
                            $existingImages = is_array($decoded) ? $decoded : [$existingImages];
                        }
                        // Check if comma-separated
                        elseif (str_contains($existingImages, ',')) {
                            $existingImages = array_map('trim', explode(',', $existingImages));
                        } else {
                            $existingImages = [$existingImages];
                        }
                    } else {
                        $existingImages = [$existingImages];
                    }
                    
                    // Filter out empty values
                    $data['products'][$index]['existing_images'] = array_values(array_filter($existingImages));
                }
            }
        }

        return $data;
    }

    /**
     * Get validation rules for update form-data request
     */
    private function getUpdateFormDataValidationRules(Request $request): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'priority' => ['sometimes', 'nullable', Rule::enum(PriorityEnum::class)],
            'expected_delivery_date' => 'sometimes|nullable|date_format:d/m/Y',
            'customer_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'deleted_id' => 'sometimes|nullable|string', // Comma-separated product IDs or custom product IDs
            'action_type' => 'sometimes|nullable|in:delivered,reorder,cancel,delete,received,rejected,approved,outfordelivery|string',
            'store_id' => 'sometimes|nullable|required_if:action_type,received,outfordelivery|integer|exists:moderators,id',
            'rejected_note' => 'sometimes|nullable|required_if:action_type,rejected|string',
            'type_name' => 'sometimes|nullable|string',
            'products' => 'sometimes|array',
            'products.*.product_id' => 'sometimes|nullable|integer|exists:products,id',
            'products.*.quantity' => 'sometimes|nullable|numeric|min:0',
            'products.*.is_custom' => 'sometimes|nullable|boolean',
            'products.*.id' => 'sometimes|nullable|integer|exists:order_custom_products,id',
            'products.*.custom_product_id' => 'sometimes|nullable|integer|exists:order_custom_products,id',
            'products.*.order_custom_product_id' => 'sometimes|nullable|integer|exists:order_custom_products,id',
            'products.*.custom_note' => 'sometimes|nullable|string',
            // Custom product detail fields (stored in JSON)
            'products.*.actual_pcs' => 'sometimes|nullable|numeric|min:0',
            'products.*.unit_id' => 'sometimes|nullable|integer|exists:units,id',
            'products.*.custom_images' => 'sometimes|nullable|array',
            'products.*.custom_images.*' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'products.*.existing_images' => 'sometimes|nullable|array',
        ];
    }

    /**
     * Process product updates
     */
    /**
     * Process product updates for an order
     * Simplified version that focuses on the single order being updated
     */
    private function processProductUpdates(
        Request $request,
        Order $orderDetails,
        array $products,
        $user
    ): array {
        $productUpdated = false;

        // Process each product in the request
        foreach ($products as $index => $productChange) {
            $isCustom = filter_var($productChange['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);

            if ($isCustom) {
                // Handle custom product update/create
                $result = $this->processCustomProductUpdate(
                    $request,
                    $productChange,
                    $index,
                    $orderDetails
                );
                if ($result['updated']) {
                    $productUpdated = true;
                }
            } else {
                // Handle regular product update/add
                if (empty($productChange['product_id'])) {
                    continue;
                }

                $result = $this->processRegularProductUpdate(
                    $productChange,
                    $orderDetails
                );
                if ($result['updated']) {
                    $productUpdated = true;
                }
            }
        }

        return [
            'deleted' => false,
            'updated' => $productUpdated,
        ];
    }

    /**
     * Process custom product update or create new custom product
     * If custom_product_id provided, update that custom product's details; otherwise create new
     */
    /**
     * Process custom product update or create
     * Logic: If custom_product_id provided → UPDATE, otherwise → INSERT (create new)
     */
    private function processCustomProductUpdate(
        Request $request,
        array $productChange,
        int $index,
        Order $order
    ): array {
        // Check for custom product ID in multiple possible fields
        $customProductId = null;
        if (isset($productChange['id']) && $productChange['id'] != null && $productChange['id'] !== '') {
            $customProductId = (int) $productChange['id'];
        } elseif (isset($productChange['custom_product_id']) && $productChange['custom_product_id'] != null && $productChange['custom_product_id'] !== '') {
            $customProductId = (int) $productChange['custom_product_id'];
        } elseif (isset($productChange['order_custom_product_id']) && $productChange['order_custom_product_id'] != null && $productChange['order_custom_product_id'] !== '') {
            $customProductId = (int) $productChange['order_custom_product_id'];
        }
        
        $customImages = $this->collectCustomImages($request, $productChange, $index);
        $customImagePaths = $this->storeCustomImages($customImages);

        $updateData = [];
        if (isset($productChange['product_id'])) $updateData['product_id'] = $productChange['product_id'];
        if (isset($productChange['pcs'])) $updateData['pcs'] = $productChange['pcs'];
        if (isset($productChange['actual_pcs'])) $updateData['actual_pcs'] = $productChange['actual_pcs'];
        if (isset($productChange['quantity'])) $updateData['quantity'] = $productChange['quantity'];
        if (isset($productChange['unit_id'])) $updateData['unit_id'] = $productChange['unit_id'];
        if (array_key_exists('custom_note', $productChange)) {
            $updateData['custom_note'] = trim($productChange['custom_note'] ?? '');
        }
        // Handle product_ids (separate column, like admin panel)
        if (array_key_exists('product_ids', $productChange)) {
            $updateData['product_ids'] = $productChange['product_ids'];
        }

        // Handle images
        $imagePathsToUse = !empty($customImagePaths) ? $customImagePaths : null;
        $existingImagesToKeep = !empty($productChange['existing_images']) 
            ? (array) $productChange['existing_images'] 
            : null;

        // CASE 1: custom_product_id provided → UPDATE existing custom product
        if ($customProductId) {
            // Find custom product directly by ID and verify it belongs to this order
            $customProduct = OrderCustomProduct::where('id', $customProductId)
                ->where('order_id', $order->id)
                ->first();
            
            if ($customProduct) {
                // Update existing custom product
                $this->customProductManager->update(
                    $customProductId,
                    $updateData,
                    $imagePathsToUse,
                    $existingImagesToKeep
                );
                return ['deleted' => false, 'updated' => true];
            }
            
            // If custom_product_id provided but not found in this order, log warning and skip
            Log::warning("Custom product ID {$customProductId} not found in order {$order->id}", [
                'custom_product_id' => $customProductId,
                'order_id' => $order->id,
                'product_change' => $productChange
            ]);
            return ['deleted' => false, 'updated' => false];
        }

        // CASE 2: custom_product_id NOT provided → Check for duplicate before INSERT
        // Find the target order (custom product order or main order)
        $targetOrder = $this->findCustomProductOrder($order);
        
        // Check if identical custom product already exists
        $existingCustomProduct = $this->findDuplicateCustomProduct($targetOrder, $productChange);
        
        if ($existingCustomProduct) {
            // Duplicate found - update it instead of creating new
            $this->customProductManager->update(
                $existingCustomProduct->id,
                $updateData,
                $imagePathsToUse,
                $existingImagesToKeep
            );
            return ['deleted' => false, 'updated' => true];
        }

        // No duplicate found - create new custom product
        $this->customProductManager->create(
            $targetOrder->id,
            $productChange,
            $customImagePaths ?? []
        );
        
        return ['deleted' => false, 'updated' => true];
    }

    /**
     * Find the appropriate order for custom products
     * Returns custom product order if exists, otherwise the main order
     */
    private function findCustomProductOrder(Order $order): Order
    {
        // Check if current order is a custom product order
        if ($order->is_custom_product) {
            return $order;
        }
        
        // Return the order directly (no parent/child relationships)
        return $order;
    }

    /**
     * Check if custom product has actual changes
     */
    private function hasCustomProductChanges(
        OrderCustomProduct $customProduct,
        array $updateData,
        ?array $imagePathsToUse,
        ?array $existingImagesToKeep
    ): bool {
        $currentDetails = $customProduct->product_details ?? [];
        
        // Check product details changes
        $fieldsToCheck = ['product_id', 'quantity', 'unit_id', 'actual_pcs'];
        foreach ($fieldsToCheck as $field) {
            if (isset($updateData[$field])) {
                $currentValue = $currentDetails[$field] ?? null;
                $newValue = $updateData[$field];
                
                // Normalize values for comparison
                if (is_numeric($currentValue)) $currentValue = (float) $currentValue;
                if (is_numeric($newValue)) $newValue = (float) $newValue;
                
                if ($currentValue != $newValue) {
                    return true;
                }
            }
        }
        
        // Check custom_note changes
        if (array_key_exists('custom_note', $updateData)) {
            $currentNote = $customProduct->custom_note ?? '';
            $newNote = trim($updateData['custom_note'] ?? '');
            if ($currentNote !== $newNote) {
                return true;
            }
        }
        
        // Check image changes
        if ($imagePathsToUse !== null || $existingImagesToKeep !== null) {
            // Images are being updated
            return true;
        }
        
        return false;
    }

    /**
     * Find duplicate custom product by comparing product details
     * Also handles case where only note is being updated (flexible matching)
     */
    private function findDuplicateCustomProduct(Order $order, array $productChange): ?OrderCustomProduct
    {
        // Get all custom products for this order
        $existingCustomProducts = OrderCustomProduct::where('order_id', $order->id)->get();
        
        if ($existingCustomProducts->isEmpty()) {
            return null;
        }
        
        // If only one custom product exists and we're updating (has custom_note or other fields),
        // treat it as an update to that product
        if ($existingCustomProducts->count() === 1) {
            $onlyProduct = $existingCustomProducts->first();
            
            // Check if this looks like an update (has custom_note or other update fields)
            $hasUpdateFields = isset($productChange['custom_note']) || 
                              isset($productChange['product_id']) || 
                              isset($productChange['quantity']) ||
                              isset($productChange['actual_pcs']);
            
            if ($hasUpdateFields) {
                // This is likely an update to the only custom product
                return $onlyProduct;
            }
        }
        
        // Prepare the new product details for comparison
        $newProductId = $productChange['product_id'] ?? null;
        $newQuantity = isset($productChange['quantity']) ? (float) $productChange['quantity'] : null;
        $newPcs = isset($productChange['actual_pcs']) ? (float) $productChange['actual_pcs'] : null;
        $newUnitId = isset($productChange['unit_id']) ? (int) $productChange['unit_id'] : null;
        $newCustomNote = isset($productChange['custom_note']) ? trim($productChange['custom_note']) : '';
        
        // Check if this is a note-only update (only custom_note is provided)
        $isNoteOnlyUpdate = !empty($newCustomNote) && 
                           $newProductId === null && 
                           $newQuantity === null && 
                           $newPcs === null && 
                           $newUnitId === null;
        
        foreach ($existingCustomProducts as $existing) {
            $existingDetails = $existing->product_details ?? [];
            
            // For note-only updates, match any custom product (prefer exact match if multiple exist)
            if ($isNoteOnlyUpdate) {
                // If only one custom product exists, return it
                if ($existingCustomProducts->count() === 1) {
                    return $existing;
                }
                // If multiple exist, try to match by product_id if provided
                // Otherwise, return first one (will be updated)
                $existingProductId = $existingDetails['product_id'] ?? null;
                if ($newProductId === null || $existingProductId == $newProductId) {
                    return $existing;
                }
                continue;
            }
            
            // Compare product_id
            $existingProductId = $existingDetails['product_id'] ?? null;
            if ($existingProductId != $newProductId) {
                continue;
            }
            
            // Compare quantity and actual_pcs
            $existingQuantity = isset($existingDetails['quantity']) ? (float) $existingDetails['quantity'] : null;
            $existingPcs = isset($existingDetails['actual_pcs']) ? (float) $existingDetails['actual_pcs'] : null;
            $existingUnitId = isset($existingDetails['unit_id']) ? (int) $existingDetails['unit_id'] : null;
            
            // For updates, allow matching even if note differs (note can be updated)
            // Only require product_id, quantity, actual_pcs, and unit_id to match
            if ($existingQuantity != $newQuantity || $existingPcs != $newPcs ||
                $existingUnitId != $newUnitId) {
                continue;
            }
            
            // If we have matching product details, this is the same custom product
            // (Note can differ - that's what we're updating)
            return $existing;
        }
        
        return null;
    }

    /**
     * Process regular product update or add new product to order
     * Handles LPO products by routing them to the appropriate LPO order
     */
    private function processRegularProductUpdate(
        array $productChange,
        Order $order
    ): array {
        // Validate required fields
        if (empty($productChange['product_id'])) {
            return ['deleted' => false, 'updated' => false];
        }

        $productId = (int) $productChange['product_id'];
        $quantity = isset($productChange['quantity']) ? (int) $productChange['quantity'] : 0;

        // Get product to check if it's an LPO product
        $product = Product::find($productId);
        if (!$product) {
            return ['deleted' => false, 'updated' => false];
        }

        // Determine target order: if product is LPO, use/create LPO order; otherwise use current order
        // Use the current order directly (no parent/child relationships)
        $targetOrder = $order;
        
        // Note: Single order can contain products from all store types (hardware, warehouse, LPO)
        // No need to validate store matching as all products are managed in one order

        // Check if product already exists in the target order
        $existingProduct = DB::table('order_products')
            ->where('order_id', $targetOrder->id)
            ->where('product_id', $productId)
            ->first();

        // If product exists and quantity hasn't changed, no update needed
        if ($existingProduct && (float) $existingProduct->quantity === $quantity) {
            return ['deleted' => false, 'updated' => false];
        }

        // Use updateOrInsert - if found, update; otherwise create
        DB::table('order_products')->updateOrInsert(
            [
                'order_id' => $targetOrder->id,
                'product_id' => $productId,
            ],
            [
                'quantity' => $quantity,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return [
            'deleted' => false,
            'updated' => true,
        ];
    }

    /**
     * Parse comma-separated deleted IDs string into array of integers
     * Handles quoted strings, JSON-encoded strings, and regular comma-separated values
     * 
     * @param string|array $deletedIds
     * @return array
     */
    private function parseDeletedIds($deletedIds): array
    {
        if (is_array($deletedIds)) {
            return array_map('intval', array_filter($deletedIds, 'is_numeric'));
        }

        if (!is_string($deletedIds) || empty($deletedIds)) {
            return [];
        }

        // Remove surrounding quotes and whitespace
        $deletedIds = trim(trim(trim($deletedIds), '"'), "'");
        
        // Try JSON decode if it looks like JSON
        if (($deletedIds[0] ?? '') === '[' || ($deletedIds[0] ?? '') === '{') {
            $decoded = json_decode($deletedIds, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_map('intval', array_filter($decoded, 'is_numeric'));
            }
        }
        
        // Split by comma, clean and convert to integers
        $ids = explode(',', $deletedIds);
        $result = [];
        
        foreach ($ids as $id) {
            $id = trim(trim(trim($id), '"'), "'");
            if (!empty($id) && is_numeric($id)) {
                $result[] = (int) $id;
            }
        }
        
        return $result;
    }

    /**
     * Process product deletions based on comma-separated IDs
     * Determines if each ID is a regular product_id or custom_product_id and deletes accordingly
     * 
     * @param Order $order
     * @param array $deletedIds
     * @param bool $isApprovedOrInTransit
     * @return array
     */
    private function processProductDeletions(Order $order, array $deletedIds, bool $isApprovedOrInTransit): array
    {
        // Normalize IDs to integers
        $deletedIds = array_map('intval', array_filter($deletedIds, 'is_numeric'));
        
        if (empty($deletedIds)) {
            return ['deleted' => false, 'message' => 'No valid IDs provided for deletion.', 'requested_ids' => []];
        }

        // Check immutable statuses
        $immutableStatuses = ['approved', 'in_transit', 'delivered', 'rejected'];
        $currentStatus = $order->status?->value ?? $order->status ?? 'pending';
        
        if (in_array($currentStatus, $immutableStatuses, true)) {
            Log::warning("Cannot delete products from order #{$order->id} - order status is '{$currentStatus}'", [
                'order_id' => $order->id,
                'status' => $currentStatus,
                'requested_ids' => $deletedIds
            ]);
            return [
                'deleted' => false, 
                'message' => "Processed orders cannot be modified. Order status is '{$currentStatus}'.",
                'requested_ids' => $deletedIds
            ];
        }

        DB::beginTransaction();

        try {
            // Fetch product IDs in single queries and convert to arrays with integer keys for fast lookup
            $orderProductIds = DB::table('order_products')
                ->where('order_id', $order->id)
                ->whereIn('product_id', $deletedIds)
                ->pluck('product_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
            
            $orderCustomProductIds = OrderCustomProduct::where('order_id', $order->id)
                ->whereIn('id', $deletedIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->toArray();

            // Use array_intersect for efficient ID matching (only check IDs that exist in order)
            $deletedProductIds = array_values(array_intersect($deletedIds, $orderProductIds));
            $deletedCustomProductIds = array_values(array_intersect($deletedIds, $orderCustomProductIds));
            
            // Log invalid IDs (not found in either table)
            $validIds = array_merge($deletedProductIds, $deletedCustomProductIds);
            $invalidIds = array_diff($deletedIds, $validIds);
            
            if (!empty($invalidIds)) {
                Log::warning("Some deleted IDs not found in order #{$order->id}", [
                    'order_id' => $order->id,
                    'invalid_ids' => array_values($invalidIds),
                    'total_requested' => count($deletedIds)
                ]);
            }

            // Delete regular products from order_products table
            if (!empty($deletedProductIds)) {
                $affectedRows = DB::table('order_products')
                    ->where('order_id', $order->id)
                    ->whereIn('product_id', $deletedProductIds)
                    ->delete();

                if ($affectedRows > 0) {
                    $deleted = true;
                }
            }

            // Delete custom products from order_custom_products table
            if (!empty($deletedCustomProductIds)) {
                $affectedRows = OrderCustomProduct::where('order_id', $order->id)
                    ->whereIn('id', $deletedCustomProductIds)
                    ->delete();

                if ($affectedRows > 0) {
                    $deleted = true;
                }
            }

            DB::commit();

            // Check if order is empty (only log, don't auto-delete order)
            $hasProducts = DB::table('order_products')->where('order_id', $order->id)->exists();
            $hasCustomProducts = OrderCustomProduct::where('order_id', $order->id)->exists();
            
            if (!$hasProducts && !$hasCustomProducts) {
                Log::info("Order #{$order->id} has no remaining products after deletion.");
            }

            // Generate response message
            $deletedCount = count($deletedProductIds) + count($deletedCustomProductIds);
            $message = $deleted 
                ? "Successfully removed {$deletedCount} product(s) from the order."
                : 'No products were deleted. The provided IDs may not exist in this order.';

            // Log only if deletion occurred or there were issues
            if ($deleted) {
                Log::info("Deleted {$deletedCount} product(s) from order #{$order->id}", [
                    'regular_products' => count($deletedProductIds),
                    'custom_products' => count($deletedCustomProductIds)
                ]);
            }

            return [
                'deleted' => $deleted,
                'message' => $message,
                'deleted_product_ids' => $deletedProductIds,
                'deleted_custom_product_ids' => $deletedCustomProductIds,
                'requested_ids' => $deletedIds
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'deleted_ids' => $deletedIds
            ]);
            throw $e;
        }
    }

    /**
     * Update order product_status and flags based on current products
     */
    private function updateOrderProductStatus(Order $order): void
    {
        $order->refresh();
        $order->load('products', 'customProducts.images');

        $hasHardwareProducts = false;
        $hasWarehouseProducts = false;
        $hasLpoProducts = false;
        $hasCustomProducts = $order->customProducts->isNotEmpty();
        
        // Check regular products
        foreach ($order->products as $product) {
            if ($product->store === StoreEnum::LPO) {
                $hasLpoProducts = true;
            } elseif ($product->store === StoreEnum::HardwareStore) {
                $hasHardwareProducts = true;
            } elseif ($product->store === StoreEnum::WarehouseStore) {
                $hasWarehouseProducts = true;
            }
        }
        
        // Update product_status
        $productStatus = $order->product_status ?? $order->initializeProductStatus();
        
        if ($hasHardwareProducts && !isset($productStatus['hardware'])) {
            $productStatus['hardware'] = 'pending';
        }
        if (($hasWarehouseProducts || $hasCustomProducts) && !isset($productStatus['warehouse'])) {
            $productStatus['warehouse'] = 'pending';
        }
        if ($hasLpoProducts && !isset($productStatus['lpo'])) {
            $productStatus['lpo'] = 'pending';
        }
        if ($hasCustomProducts && !isset($productStatus['custom'])) {
            $productStatus['custom'] = 'pending';
        }
        
        // Update order flags
        $updateData = [
            'product_status' => $productStatus,
            'is_lpo' => $hasLpoProducts,
            'is_custom_product' => $hasCustomProducts,
        ];
        
        // Update store if needed (priority: Hardware > Warehouse > LPO)
        if ($hasHardwareProducts && $order->store !== StoreEnum::HardwareStore->value) {
            $updateData['store'] = StoreEnum::HardwareStore->value;
            // $updateData['store_manager_role'] = RoleEnum::StoreManager->value;
        } elseif (($hasWarehouseProducts || $hasCustomProducts) && $order->store !== StoreEnum::WarehouseStore->value && !$hasHardwareProducts) {
            $updateData['store'] = StoreEnum::WarehouseStore->value;
            // $updateData['store_manager_role'] = RoleEnum::WorkshopStoreManager->value;
        } elseif ($hasLpoProducts && !$hasHardwareProducts && !$hasWarehouseProducts && !$hasCustomProducts) {
            $updateData['store'] = StoreEnum::LPO->value;
            // $updateData['store_manager_role'] = null;
        }
        
        $order->update($updateData);
        $order->syncOrderStatusFromProductStatuses();
    }

    /**
     * Generate update response message
     */
    private function generateUpdateMessage(array $updateData, bool $productUpdated, bool $productDeleted): string
    {
        if ($productDeleted && $productUpdated && !empty($updateData)) {
            return 'Order updated, products modified and removed successfully.';
        } elseif ($productDeleted && $productUpdated) {
            return 'Products updated and removed successfully.';
        } elseif ($productDeleted && !empty($updateData)) {
            return 'Order updated and products removed successfully.';
        } elseif ($productDeleted) {
            return 'Products removed successfully.';
        } elseif ($productUpdated && !empty($updateData)) {
            return 'Order and products updated successfully.';
        } elseif ($productUpdated) {
            return 'Products updated successfully.';
        } elseif (!empty($updateData)) {
            return 'Order updated successfully.';
        } else {
            return 'No changes applied.';
        }
    }

    /**
     * Extract products data from order for stock operations
     * 
     * @param Order $order
     * @return array Array of [product_id => ['quantity' => int]]
     */
    private function extractProductsData(Order $order): array
    {
        $productsData = [];
        
        if (!$order->relationLoaded('products')) {
            $order->load('products');
        }
        
        foreach ($order->products as $product) {
            $pivot = $product->pivot;
            if ($pivot && !empty($pivot->quantity)) {
                $productsData[$product->id] = [
                    'quantity' => (int)$pivot->quantity,
                ];
            }
        }
        
        return $productsData;
    }

    /**
     * Send notifications to store managers and transport manager
     * 
     * @param Order $order
     * @param string $notificationClass The notification class to send
     * @return void
     */
    private function sendOrderNotifications(Order $order, string $notificationClass): void
    {
        $storeManagers = Moderator::where('role', RoleEnum::StoreManager->value)
            ->where('status', 'active')
            ->get();
            
        foreach ($storeManagers as $storeManager) {
            try {
                $storeManager->notify(new $notificationClass($order));
            } catch (\Exception $e) {
                Log::error("Failed to send notification to Store Manager: {$storeManager->email} - {$e->getMessage()}");
            }
        }

        if ($order->transport_manager_id) {
            $transportManager = Moderator::find($order->transport_manager_id);
            if ($transportManager) {
                try {
                    $transportManager->notify(new $notificationClass($order));
                } catch (\Exception $e) {
                    Log::error("Failed to send notification to Transport Manager: {$transportManager->email} - {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Validate status transition restrictions
     * 
     * @param string $currentStatus Current product status
     * @param string $newActionType New action type to transition to
     * @return ApiErrorResponse|null Returns error response if transition is invalid, null otherwise
     */
    private function validateStatusTransition(string $currentStatus, string $newActionType): ?ApiErrorResponse
    {
        // RESTRICTION: If already approved, cannot change to pending or rejected
        // Can only change to: outfordelivery, in_transit, or delivered
        if (in_array($currentStatus, ['approved', 'outfordelivery', 'in_transit', 'delivered'], true)) {
            if (in_array($newActionType, ['pending', 'rejected'], true)) {
                return new ApiErrorResponse(
                    ['errors' => ['Cannot change status from approved/outfordelivery/in_transit/delivered to pending or rejected. You can only change to outfordelivery, in_transit, or delivered.']],
                    'order action failed',
                    422
                );
            }
        }
        
        return null;
    }

    /**
     * Handle delivered action
     * 
     * @param Order $order
     * @param string $currentStatus
     * @param string $productStatusType
     * @return ApiErrorResponse|null Returns error response if validation fails, null on success
     */
    private function handleDeliveredAction(Order $order, string $currentStatus, string $productStatusType): ?ApiErrorResponse
    {
        if ($currentStatus === 'delivered') {
            return new ApiErrorResponse(
                ['errors' => [__('api.site-manager.order_already_received')]],
                'order action failed',
                403
            );
        }

        $order->update([
            'status' => OrderStatusEnum::Delivery->value,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $order->updateProductStatus($productStatusType, 'delivered');
        $this->sendOrderNotifications($order, \App\Notifications\OrderReceivedNotification::class);
        
        return null;
    }

    /**
     * Handle rejected action
     * 
     * @param Order $order
     * @param string $currentStatus
     * @param string $productStatusType
     * @param string $rejectedNote
     * @return ApiErrorResponse|null Returns error response if validation fails, null on success
     */
    private function handleRejectedAction(Order $order, string $currentStatus, string $productStatusType, string $rejectedNote): ?ApiErrorResponse
    {
        if ($currentStatus === 'rejected') {
            return new ApiErrorResponse(
                ['errors' => [__('api.site-manager.order_already_rejected')]],
                'order action failed',
                403
            );
        }

        if ($currentStatus === 'delivered') {
            return new ApiErrorResponse(
                ['errors' => [__('api.site-manager.order_already_delivered')]],
                'order action failed',
                403
            );
        }

        if (empty($rejectedNote) || trim($rejectedNote) === '') {
            return new ApiErrorResponse(
                ['errors' => ['Rejected note is required when rejecting an order.']],
                'order action failed',
                422
            );
        }

        $wasApprovedOrInTransit = in_array($currentStatus, ['approved', 'in_transit']);

        $order->update([
            'status' => OrderStatusEnum::Rejected->value,
            'rejected_note' => $rejectedNote,
        ]);

        $order->updateProductStatus($productStatusType, 'rejected');
        $order->refresh();
        $order->syncOrderStatusFromProductStatuses();
        Order::syncParentChildOrderStatuses($order);

        if ($wasApprovedOrInTransit) {
            $productsData = $this->extractProductsData($order);
            if (!empty($productsData)) {
                try {
                    $this->restoreStockForOrder($order, $productsData, $order->site_id);
                } catch (\Exception $e) {
                    Log::error("OrderController: Failed to restore stock when rejecting order #{$order->id}: " . $e->getMessage());
                }
            }
        }

        $this->sendOrderNotifications($order, OrderRejectedNotification::class);
        
        return null;
    }

    /**
     * Handle approved action
     * 
     * @param Order $order
     * @param string $currentStatus
     * @param string $productStatusType
     * @return ApiErrorResponse|null Returns error response if validation fails, null on success
     */
    private function handleApprovedAction(Order $order, string $currentStatus, string $productStatusType): ?ApiErrorResponse
    {
        $oldDeliveryStatus = $currentStatus;
        
        DB::beginTransaction();
        
        try {
            $order->updateProductStatus($productStatusType, 'approved');
            $order->refresh();
            $order->syncOrderStatusFromProductStatuses();
            Order::syncParentChildOrderStatuses($order);

            if ($oldDeliveryStatus !== 'approved' && $oldDeliveryStatus !== 'in_transit') {
                $productsData = $this->extractProductsData($order);
                
                if (!empty($productsData)) {
                    $stockError = $this->deductStockForOrder($order, $productsData, $order->site_id);
                    if ($stockError) {
                        $order->updateProductStatus($productStatusType, $oldDeliveryStatus);
                        DB::rollBack();
                        return $stockError;
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("OrderController: Failed to approve order #{$order->id}: " . $e->getMessage(), [
                'order_id' => $order->id,
                'product_status_type' => $productStatusType,
                'old_delivery_status' => $oldDeliveryStatus,
                'trace' => $e->getTraceAsString()
            ]);

            $order->updateProductStatus($productStatusType, $oldDeliveryStatus);

            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'Order approval failed. Please try again.',
                422
            );
        }
        
        return null;
    }

    /**
     * Handle received action
     * 
     * @param Order $order
     * @param string $productStatusType
     * @param int $storeId
     * @return ApiErrorResponse|null Returns error response if validation fails, null on success
     */
    private function handleReceivedAction(Order $order, string $productStatusType, int $storeId): ?ApiErrorResponse
    {
        $storeManager = Moderator::find($storeId);
        
        if (!$storeManager) {
            return new ApiErrorResponse(
                ['errors' => ['Store manager not found.']],
                'order action failed',
                404
            );
        }

        $storeUpdateData = [];
        $role = $storeManager->getRole();
        
        if ($role === RoleEnum::StoreManager) {
            $storeUpdateData['store_manager_role'] = RoleEnum::StoreManager->value;
            $storeUpdateData['store'] = StoreEnum::HardwareStore->value;
        } elseif ($role === RoleEnum::WorkshopStoreManager) {
            $storeUpdateData['store_manager_role'] = RoleEnum::WorkshopStoreManager->value;
            $storeUpdateData['store'] = StoreEnum::WarehouseStore->value;
        }

        if (!empty($storeUpdateData)) {
            $order->update($storeUpdateData);
        }

        $order->updateProductStatus($productStatusType, 'received');
        $storeManager->notify(new \App\Notifications\OrderReceivedNotification($order));
        
        return null;
    }

    /**
     * Handle default action types (e.g., outfordelivery)
     * 
     * @param Order $order
     * @param string $actionType
     * @param string $productStatusType
     * @return void
     */
    private function handleDefaultAction(Order $order, string $actionType, string $productStatusType): void
    {
        $order->updateProductStatus($productStatusType, $actionType);
        $order->refresh();
        
        $calculatedStatus = $order->calculateOrderStatusFromProductStatuses();
        if (in_array($calculatedStatus, ['pending', 'approved'], true)) {
            $order->update(['status' => $calculatedStatus]);
        }
    }

    public function orderAction(Request $request): ApiResponse|ApiErrorResponse
    {   
        $validator = Validator::make($request->all(),[
            'order_id' => 'required|integer|exists:orders,id',
            'action_type' => 'required|in:delivered,reorder,cancel,delete,received,rejected,approved,outfordelivery|string',
            'store_id' => 'required_if:action_type,received,outfordelivery|integer|exists:moderators,id',
            'rejected_note' => 'required_if:action_type,rejected|string',
            'type_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'order action failed',
                422
            );
        }

        try{
            $data = $request->all();
            $user = $request->user();

            $orderDetails = Order::where('id', $data['order_id'])->first();

            if( !$orderDetails ){
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    
                    message: __('api.site-manager.empty_records'),
                );
            }

            // Determine which product_status key we should update for this action
            // Pass user to determine product_status based on logged-in user's role and actual products
            $productStatusType = $this->resolveProductStatusTypeForOrder(
                $orderDetails,
                $data['type_name'] ?? null,
                $user
            );

            // Get current status for validation
            $currentStatus = $orderDetails->getProductStatus($productStatusType);
            
            // Validate status transition
            $transitionError = $this->validateStatusTransition($currentStatus, $data['action_type']);
            if ($transitionError) {
                return $transitionError;
            }

            if($data['action_type'] == 'delivered') {
                $error = $this->handleDeliveredAction($orderDetails, $currentStatus, $productStatusType);
                if ($error) {
                    return $error;
                }

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.site-manager.order_received'),
                );
            } else if($data['action_type'] == 'reorder') {
                $productsData = [];
                $customProductsData = [];
                
                $orderProductsData = DB::table('order_products')
                    ->where('order_id', $orderDetails->id)
                    ->get();

                foreach ($orderProductsData as $orderProduct) {
                    $product = $orderDetails->products->firstWhere('id', $orderProduct->product_id);
                    if ($product && !empty($orderProduct->quantity)) {
                        $productsData[$product->id] = [
                            'quantity' => $orderProduct->quantity,
                        ];
                    }
                }

                // Load custom products from order_custom_products table
                $existingCustomProducts = OrderCustomProduct::where('order_id', $orderDetails->id)->get();
                foreach ($existingCustomProducts as $customProduct) {
                    // Get images from new table
                    $imagePaths = DB::table('order_custom_product_images')
                        ->where('order_custom_product_id', $customProduct->id)
                        ->orderBy('sort_order')
                        ->pluck('image_path')
                        ->toArray();
                    
                    $customProductsData[] = [
                        'custom_note' => $customProduct->custom_note ?? '',
                        'custom_image_paths' => $imagePaths,
                    ];
                }

                // Validate site is active before reordering
                $siteId = $orderDetails->site_id;
                if ($siteId) {
                    $site = Site::find($siteId);
                    if (!$site) {
                        return new ApiErrorResponse(
                            ['errors' => ['Site not found.']],
                            'Validation failed',
                            422
                        );
                    }
                    if (!$site->status) {
                        return new ApiErrorResponse(
                            ['errors' => ['Cannot reorder for inactive site. The site must be active to place orders.']],
                            'Site is inactive',
                            422
                        );
                    }
                }

                $order = new Order();
                $order->site_manager_id = $request->user()->id;
                $order->site_id     = $orderDetails->site_id;
                $order->priority    = $orderDetails->priority;
                $order->sale_date   = $orderDetails->sale_date;
                $order->note        = $orderDetails->note;
                $order->customer_image = $orderDetails->customer_image;
                $order->save();

                if (!empty($productsData)) {
                    $order->products()->sync($productsData);
                }
                
                if (!empty($customProductsData)) {
                    foreach ($customProductsData as $customProduct) {
                        $imagePaths = $customProduct['custom_image_paths'] ?? [];
                        
                        if (!is_array($imagePaths)) {
                            $imagePaths = [];
                            Log::warning('Invalid custom_image_paths format, converting to array', [
                                'order_id' => $order->id,
                                'custom_image_paths_type' => gettype($customProduct['custom_image_paths'] ?? null),
                            ]);
                        }
                        
                        Log::info('Creating custom product with images', [
                            'order_id' => $order->id,
                            'image_paths_count' => count($imagePaths),
                            'image_paths' => $imagePaths,
                        ]);
                        
                        $this->customProductManager->create(
                            $order->id,
                            $customProduct,
                            $imagePaths
                        );
                    }
                }

                $order->refresh(); // Refresh to load relationships

                // Notify ALL active store managers about new order
                $storeManagers = Moderator::where('role', RoleEnum::StoreManager->value)
                    ->where('status', 'active')
                    ->get();

                if ($storeManagers->isEmpty()) {
                    Log::warning('No active Store Managers found to notify about Order #' . $order->id.' from the application');
                    return new ApiErrorResponse(
                        ['errors' => ['No active Store Managers found to notify about Order #' . $order->id]],
                        'order reorder failed',
                        422
                    );
                } else {
                    foreach ($storeManagers as $storeManager) {
                        try {
                            $storeManager->notify(new OrderCreatedNotification($order));
                        } catch (\Exception $e) {
                            Log::error('Failed to send notification to Store Manager: ' . $storeManager->email . ' - ' . $e->getMessage());
                            return new ApiErrorResponse(
                                ['errors' => ['Failed to send notification to Store Manager: ' . $storeManager->email . ' - ' . $e->getMessage()]],
                                'order reorder failed',
                                422
                            );
                        }
                    }
                }

                $orderDetails = Order::with(['site','products.category', 'products.productImages', 'customProducts.images'])->where('id',$order->id)->first();

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: OrderResource::collection([$orderDetails]),
                    message: __('api.site-manager.order_create'),
                );
            } else if($data['action_type'] == 'cancel') {
                $oldDeliveryStatus = $orderDetails->getProductStatus($productStatusType);

                if ($oldDeliveryStatus === 'rejected') {
                    return new ApiErrorResponse(
                        ['errors' => [__('api.site-manager.cancel_rejected_order')]],
                        'order action failed',
                        403
                    );
                }

                if ($oldDeliveryStatus === 'cancelled') {
                    return new ApiErrorResponse(
                        ['errors' => [__('api.site-manager.cancel_cancelled_order')]],
                        'order action failed',
                        403
                    );
                }

                $wasApprovedOrInTransit = in_array($oldDeliveryStatus, ['approved', 'in_transit']);

                $orderDetails->load('products', 'customProducts.images');

                $orderDetails->update([
                    'status' => false,
                ]);

                // Update product_status JSON for this product type
                $orderDetails->updateProductStatus($productStatusType, 'cancelled');

                if ($wasApprovedOrInTransit) {
                    $productsData = $this->extractProductsData($orderDetails);
                    if (!empty($productsData)) {
                        try {
                            $this->restoreStockForOrder($orderDetails, $productsData, $orderDetails->site_id);
                        } catch (\Exception $e) {
                            Log::error("OrderController: Failed to restore stock when cancelling order #{$orderDetails->id}: " . $e->getMessage());
                        }
                    }
                }

                $this->sendOrderNotifications($orderDetails, \App\Notifications\OrderCancelledNotification::class);

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.site-manager.order_cancel'),
                );
            } else if ($data['action_type'] == 'delete') {
                $immutableStatuses = ['approved', 'in_transit', 'delivered', 'rejected'];

                // Block delete if the current product type is already processed
                if (in_array($orderDetails->getProductStatus($productStatusType), $immutableStatuses, true)) {
                    return new ApiErrorResponse(
                        ['errors' => ['Processed orders cannot be deleted.']],
                        'order deletion failed',
                        422
                    );
                }

                // Delete only the single order (no parent/child relationships)
                $ordersToDelete = collect([$orderDetails])
                    ->filter(fn ($item) => $item->site_manager_id === $user->id)
                    ->unique('id')
                    ->values();

                foreach ($ordersToDelete as $item) {
                    if (in_array($item->getProductStatus($productStatusType), $immutableStatuses, true)) {
                        return new ApiErrorResponse(
                            ['errors' => ['One or more related orders are already processed and cannot be deleted.']],
                            'order deletion failed',
                            422
                        );
                    }
                }

                DB::beginTransaction();

                foreach ($ordersToDelete as $item) {
                    DB::table('order_products')->where('order_id', $item->id)->delete();
                    $item->delete();
                }

                DB::commit();

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: 'Order deleted successfully.',
                );
            }else if($data['action_type'] == 'received') {
                $error = $this->handleReceivedAction($orderDetails, $productStatusType, $data['store_id']);
                if ($error) {
                    return $error;
                }
 
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.site-manager.order_received'),
                );
            } else if($data['action_type'] == 'rejected') {
                $error = $this->handleRejectedAction($orderDetails, $currentStatus, $productStatusType, $data['rejected_note'] ?? '');
                if ($error) {
                    return $error;
                }

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.site-manager.order_rejected'),
                );
            } else if($data['action_type'] == 'approved') {
                $error = $this->handleApprovedAction($orderDetails, $currentStatus, $productStatusType);
                if ($error) {
                    return $error;
                }

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.site-manager.order_approved'),
                );
            }

            // Fallback for other action types (e.g., outfordelivery)
            $this->handleDefaultAction($orderDetails, $data['action_type'], $productStatusType);
            
            return new ApiResponse(
                isError: false,
                code: 200,
                data: [],
                message: __('api.site-manager.order_approved'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'order action failed',
                500
            );
        }
    } 

    /**
     * Resolve which product_status key should be used for the given order/action.
     * Both warehouse and custom products use the "warehouse" key in product_status JSON.
     * 
     * @param Order $order The order to resolve product_status type for
     * @param string|null $typeName Explicit type name from request (optional)
     * @param mixed $user The logged-in user (optional, for role-based resolution)
     * @return string The product_status key to update ('hardware', 'warehouse', or 'lpo')
     */
    private function resolveProductStatusTypeForOrder(Order $order, ?string $typeName = null, $user = null): string
    {
        $typeName = $typeName ? strtolower($typeName) : null;
        $validTypes = ['hardware', 'warehouse', 'lpo', 'custom'];

        if ($typeName && in_array($typeName, $validTypes, true)) {
            // Treat "custom" as "warehouse" in product_status JSON
            return $typeName === 'custom' ? 'warehouse' : $typeName;
        }

        // If user is provided, determine based on user's role and actual products in order
        if ($user) {
            $userRole = $user->getRole();
            $userRoleValue = $userRole?->value ?? null;
            
            // Load products if not already loaded
            if (!$order->relationLoaded('products')) {
                $order->load('products', 'customProducts');
            }
            
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
            
            // Determine product_status type based on logged-in user's role
            if ($userRoleValue === RoleEnum::StoreManager->value) {
                // Hardware Store Manager: update hardware product_status if hardware products exist
                if ($hasHardwareProducts) {
                    return 'hardware';
                }
            } elseif ($userRoleValue === RoleEnum::WorkshopStoreManager->value) {
                // Workshop store Manager: both warehouse and custom products use 'warehouse' key
                // Update warehouse product_status if warehouse products OR custom products exist
                if ($hasWarehouseProducts || $hasCustomProducts) {
                    return 'warehouse'; // Both warehouse and custom products use 'warehouse' key
                }
            }
        }

        // Infer from order flags if type_name not provided and user-based resolution didn't work
        if ($order->is_lpo) {
            return 'lpo';
        }

        if ($order->is_custom_product) {
            return 'warehouse';
        }

        $store = $order->store;

        // Handle both raw string values and StoreEnum casts
        if ($store === StoreEnum::HardwareStore->value || $store === StoreEnum::HardwareStore) {
            return 'hardware';
        }

        if ($store === StoreEnum::WarehouseStore->value || $store === StoreEnum::WarehouseStore) {
            return 'warehouse';
        }

        // Default to hardware if we cannot determine
        return 'hardware';
    }

    private function normalizeProductsRequest(Request $request): void
    {
        $requestData = $request->all();

        if (empty($requestData['products']) || !is_array($requestData['products'])) {
            return;
        }

        foreach ($requestData['products'] as $index => $product) {
            $isCustom = filter_var(
                $product['is_custom'] ?? null,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($isCustom === null) {
                $hasCustomFields = !empty($product['custom_note']) || !empty($product['custom_images']);
                $isCustom = $hasCustomFields;
            }

            $requestData['products'][$index]['is_custom'] = $isCustom ? 1 : 0;

            if (!empty($product['custom_images']) && !is_array($product['custom_images'])) {
                $requestData['products'][$index]['custom_images'] = [$product['custom_images']];
            }
        }

        $request->merge($requestData);
    }

    private function collectCustomImages(Request $request, array $product, int $index): array
    {
        $customImages = [];
        
        // Try multiple methods to get files (PUT requests sometimes don't parse files automatically)
        $allFiles = $request->allFiles();
        
        // For PUT/PATCH requests, Laravel doesn't parse files automatically - try multiple methods
        // Also check for POST requests in case files aren't parsed correctly
        if (empty($allFiles) && in_array($request->method(), ['PUT', 'PATCH', 'POST'])) {
            // Method A: Try Symfony Request files bag
            try {
                if (method_exists($request, 'files')) {
                    $symfonyFiles = $request->files->all();
                    if (!empty($symfonyFiles)) {
                        $allFiles = $symfonyFiles;
                        Log::info('Got files from Symfony Request files bag', [
                            'file_keys' => array_keys($allFiles),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get files from Symfony Request', ['error' => $e->getMessage()]);
            }
            
            // Method B: Manually parse files from multipart/form-data content
            if (empty($allFiles)) {
                $parsedFiles = $this->parseFilesFromMultipartContent($request, $index);
                if (!empty($parsedFiles)) {
                    $allFiles = $parsedFiles;
                    Log::info('Parsed files from multipart content', [
                        'file_keys' => array_keys($allFiles),
                        'file_count' => count($allFiles),
                    ]);
                }
            }
        }

        // If no files found yet, try alternative methods for POST requests too
        if (empty($allFiles) && $request->method() === 'POST') {
            try {
                if (method_exists($request, 'files')) {
                    $symfonyFiles = $request->files->all();
                    if (!empty($symfonyFiles)) {
                        $allFiles = $symfonyFiles;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get files from Symfony Request for POST', ['error' => $e->getMessage()]);
            }
        }

        // Debug: log all file keys for troubleshooting
        $allFilesStructure = [];
        foreach ($allFiles as $key => $value) {
            if (is_array($value)) {
                $allFilesStructure[$key] = array_keys($value);
            } else {
                $allFilesStructure[$key] = gettype($value);
            }
        }
        
        Log::info('Collecting custom images - Files detection', [
            'index' => $index,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'file_keys_from_allFiles' => array_keys($request->allFiles()),
            'file_keys_detected' => !empty($allFiles) ? array_keys($allFiles) : [],
            'file_structure' => $allFilesStructure,
            'file_count' => count($allFiles),
            'has_files_method' => method_exists($request, 'files'),
        ]);

        // Method 0: Handle when Laravel groups files under "products" key
        // This happens when files are sent as products[5][custom_images][]
        if (isset($allFiles['products']) && is_array($allFiles['products'])) {
            $productsFiles = $allFiles['products'];
            
            // Log detailed structure for debugging
            $detailedStructure = [];
            foreach ($productsFiles as $idx => $pf) {
                if (is_array($pf)) {
                    $detailedStructure[$idx] = array_keys($pf);
                    if (isset($pf['custom_images'])) {
                        $detailedStructure[$idx]['custom_images_count'] = is_array($pf['custom_images']) 
                            ? count($pf['custom_images']) 
                            : 1;
                    }
                }
            }
            Log::info('Products files structure details', [
                'requested_index' => $index,
                'available_indices' => array_keys($productsFiles),
                'detailed_structure' => $detailedStructure,
            ]);
            
            // First try the specific index
            if (isset($productsFiles[$index]) && is_array($productsFiles[$index])) {
                $productFiles = $productsFiles[$index];
                
                // Check for custom_images key
                if (isset($productFiles['custom_images'])) {
                    $customImageFiles = $productFiles['custom_images'];
                    $files = is_array($customImageFiles) ? $customImageFiles : [$customImageFiles];
                    
                    foreach ($files as $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                            $this->addUploadedFileIfNew($customImages, $file);
                            Log::info('Added file from products array structure (specific index)', [
                                'index' => $index,
                                'filename' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                            ]);
                        }
                    }
                }
            }
            
            // If nothing found at specific index, check all indices
            // This handles cases where file index doesn't match product index
            if (empty($customImages)) {
                Log::info('No files found at specific index, checking all indices', [
                    'requested_index' => $index,
                ]);
                foreach ($productsFiles as $fileIndex => $productFiles) {
                    if (is_array($productFiles) && isset($productFiles['custom_images'])) {
                        $customImageFiles = $productFiles['custom_images'];
                        $files = is_array($customImageFiles) ? $customImageFiles : [$customImageFiles];
                        
                        foreach ($files as $file) {
                            if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                                $this->addUploadedFileIfNew($customImages, $file);
                                Log::info('Added file from products array structure (all indices)', [
                                    'requested_index' => $index,
                                    'file_index' => $fileIndex,
                                    'filename' => $file->getClientOriginalName(),
                                    'size' => $file->getSize(),
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Method 1: Check for products[index][custom_images][n] pattern (with array indices)
        // This handles: products[0][custom_images][0], products[0][custom_images][1], products[0][custom_images][]
        foreach ($allFiles as $key => $file) {
            // Match patterns like: products[0][custom_images][0], products[0][custom_images][], products[0][custom_images]
            if (preg_match('/^products\[(\d+)\]\[custom_images\](?:\[.*\])?$/', $key, $matches)) {
                $fileIndex = (int) $matches[1];
                if ($fileIndex === $index) {
                    // Handle both single file and array of files
                    if (is_array($file)) {
                        foreach ($file as $f) {
                            if ($f instanceof \Illuminate\Http\UploadedFile && $f->isValid()) {
                                $this->addUploadedFileIfNew($customImages, $f);
                                Log::debug('Added file from array', [
                                    'key' => $key,
                                    'filename' => $f->getClientOriginalName(),
                                ]);
                            }
                        }
                    } elseif ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $this->addUploadedFileIfNew($customImages, $file);
                        Log::debug('Added single file', [
                            'key' => $key,
                            'filename' => $file->getClientOriginalName(),
                        ]);
                    }
                }
            }
        }
        
        // Method 1b: Special handling for when Laravel groups multiple files with same key
        // Check if there's a products[index][custom_images] key that contains an array
        $groupedKey = "products[{$index}][custom_images]";
        if (isset($allFiles[$groupedKey]) && is_array($allFiles[$groupedKey])) {
            foreach ($allFiles[$groupedKey] as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $this->addUploadedFileIfNew($customImages, $file);
                    Log::debug('Added file from grouped key', [
                        'key' => $groupedKey,
                        'filename' => $file->getClientOriginalName(),
                    ]);
                }
            }
        }

        // Method 2: Check for products.index.custom_images pattern (dot notation)
        $fileKey = "products.{$index}.custom_images";
        if ($request->hasFile($fileKey)) {
            $uploadedFiles = $request->file($fileKey);
            if (is_array($uploadedFiles)) {
                foreach ($uploadedFiles as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $this->addUploadedFileIfNew($customImages, $file);
                        Log::debug('Added file from dot notation (array)', [
                            'key' => $fileKey,
                            'filename' => $file->getClientOriginalName(),
                        ]);
                    }
                }
            } elseif ($uploadedFiles instanceof \Illuminate\Http\UploadedFile && $uploadedFiles->isValid()) {
                $this->addUploadedFileIfNew($customImages, $uploadedFiles);
                Log::debug('Added file from dot notation (single)', [
                    'key' => $fileKey,
                    'filename' => $uploadedFiles->getClientOriginalName(),
                ]);
            }
        }
        
        // Method 2b: Try direct file access for PUT requests with bracket notation
        // Try: products[0][custom_images][0], products[0][custom_images][1], etc.
        for ($i = 0; $i < 10; $i++) { // Check up to 10 files
            $bracketKey = "products[{$index}][custom_images][{$i}]";
            $dotKey = "products.{$index}.custom_images.{$i}";
            
            if ($request->hasFile($bracketKey)) {
                $file = $request->file($bracketKey);
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $this->addUploadedFileIfNew($customImages, $file);
                    Log::debug('Added file from bracket notation with index', [
                        'key' => $bracketKey,
                        'filename' => $file->getClientOriginalName(),
                    ]);
                }
            } elseif ($request->hasFile($dotKey)) {
                $file = $request->file($dotKey);
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $this->addUploadedFileIfNew($customImages, $file);
                    Log::debug('Added file from dot notation with index', [
                        'key' => $dotKey,
                        'filename' => $file->getClientOriginalName(),
                    ]);
                }
            }
        }

        // Method 3: Check for files with pattern products[index][custom_images] (without array brackets)
        foreach ($allFiles as $key => $file) {
            if (preg_match('/^products\[(\d+)\]\[custom_images\]$/', $key, $matches)) {
                $fileIndex = (int) $matches[1];
                if ($fileIndex === $index) {
                    if (is_array($file)) {
                        foreach ($file as $f) {
                            if ($f instanceof \Illuminate\Http\UploadedFile && $f->isValid()) {
                                $this->addUploadedFileIfNew($customImages, $f);
                            }
                        }
                    } elseif ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $this->addUploadedFileIfNew($customImages, $file);
                    }
                }
            }
        }

        // Method 4: Check in product data array (for base64 or already uploaded files)
        if (isset($product['custom_images']) && !empty($product['custom_images'])) {
            $additionalImages = is_array($product['custom_images'])
                ? $product['custom_images']
                : [$product['custom_images']];

            foreach ($additionalImages as $img) {
                if ($img instanceof \Illuminate\Http\UploadedFile && $img->isValid()) {
                    $this->addUploadedFileIfNew($customImages, $img);
                } elseif (is_string($img) && !empty($img) && !in_array($img, $customImages, true)) {
                    $customImages[] = $img;
                }
            }
        }

        Log::info('Collected custom images result', [
            'index' => $index,
            'total_images_collected' => count($customImages),
        ]);

        return $customImages;
    }

    /**
     * Parse files from multipart/form-data content for PUT requests
     * This is a fallback when Laravel doesn't automatically parse files
     */
    private function parseFilesFromMultipartContent(Request $request, int $index): array
    {
        $files = [];
        
        try {
            $contentType = $request->header('Content-Type', '');
            if (strpos($contentType, 'multipart/form-data') === false) {
                return $files;
            }
            
            // Try to get files using direct access - sometimes this works even when allFiles() doesn't
            // Check for products[0][custom_images][0], products[0][custom_images][1], etc.
            for ($i = 0; $i < 20; $i++) { // Check up to 20 files
                $keys = [
                    "products[{$index}][custom_images][{$i}]",
                    "products.{$index}.custom_images.{$i}",
                    "products[{$index}][custom_images][]",
                ];
                
                foreach ($keys as $key) {
                    if ($request->hasFile($key)) {
                        $file = $request->file($key);
                        if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                            if (!isset($files[$key])) {
                                $files[$key] = $file;
                            }
                        } elseif (is_array($file)) {
                            foreach ($file as $f) {
                                if ($f instanceof \Illuminate\Http\UploadedFile && $f->isValid()) {
                                    $fileKey = $key . '_' . count($files);
                                    $files[$fileKey] = $f;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse files from multipart content', [
                'error' => $e->getMessage(),
                'index' => $index,
            ]);
        }
        
        return $files;
    }

    private function addUploadedFileIfNew(array &$customImages, \Illuminate\Http\UploadedFile $file): void
    {
        foreach ($customImages as $existingFile) {
            if ($existingFile instanceof \Illuminate\Http\UploadedFile &&
                $existingFile->getRealPath() === $file->getRealPath()) {
                return;
            }
        }

        $customImages[] = $file;
    }

    private function storeCustomImages(array $customImages): array
    {
        $customImagePaths = [];

        foreach ($customImages as $customImage) {
            if ($customImage instanceof \Illuminate\Http\UploadedFile) {
                $customImagePaths[] = $customImage->store('orders/custom-products', 'public');
            } elseif (is_string($customImage)) {
                if (preg_match('/^data:image\/(\w+);base64,/', $customImage, $matches)) {
                    $imageData = base64_decode(substr($customImage, strpos($customImage, ',') + 1));
                    $extension = $matches[1] ?? 'png';
                    $filename = 'custom_' . uniqid() . '.' . $extension;
                    $path = 'orders/custom-products/' . $filename;
                    Storage::disk('public')->put($path, $imageData);
                    $customImagePaths[] = $path;
                } elseif (!empty($customImage)) {
                    $customImagePaths[] = $customImage;
                }
            }
        }

        return $customImagePaths;
    }

    /**
     * Save custom product images to the new images table
     * 
     * @param int $customProductId
     * @param array $imagePaths Array of image paths
     * @return void
     */
    private function saveCustomProductImages(int $customProductId, array $imagePaths): void
    {
        if (empty($imagePaths)) {
            return;
        }

        $sortOrder = 0;
        foreach ($imagePaths as $imagePath) {
            if (!empty($imagePath) && is_string($imagePath)) {
                OrderCustomProductImage::create([
                    'order_custom_product_id' => $customProductId,
                    'image_path' => $imagePath,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }
    }

    /**
     * Create order using multipart form-data
     * 
     * This endpoint is specifically designed for multipart/form-data requests
     * with proper file upload handling for customer images and custom product images.
     * 
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function createOrderWithFormData(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            // Normalize and prepare form data
            $formData = $this->prepareFormData($request);

            // Validate the request
            $validator = Validator::make(
                $formData,
                $this->getFormDataValidationRules($request)
            );

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'Validation failed',
                    422
                );
            }

            // Process products
            $productsData = [];
            $customProductsData = [];
            $supplierMapping = [];

            foreach ($formData['products'] as $index => $product) {
                $isCustom = filter_var($product['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);

                if ($isCustom) {
                    $customProduct = $this->processCustomProduct($request, $product, $index);
                    if ($customProduct) {
                        $customProductsData[] = $customProduct;
                    }
                } else {
                    if (!empty($product['product_id']) && !empty($product['quantity'])) {
                        $productId = (int) $product['product_id'];
                        $productsData[$productId] = [
                            'quantity' => (int) $product['quantity'],
                        ];
                        
                        // Collect supplier_id for LPO products
                        if (!empty($product['supplier_id'])) {
                            $productModel = Product::find($productId);
                            if ($productModel && $productModel->store === StoreEnum::LPO) {
                                $supplierMapping[(string)$productId] = (int)$product['supplier_id'];
                            }
                        }
                    }
                }
            }

            if (empty($productsData) && empty($customProductsData)) {
                return new ApiErrorResponse(
                    ['errors' => ['No products selected for order creation.']],
                    'Validation failed',
                    422
                );
            }

            // Validate site is active
            $siteId = $formData['site_id'] ?? null;
            if ($siteId) {
                $site = Site::find($siteId);
                if (!$site) {
                    return new ApiErrorResponse(
                        ['errors' => ['Site not found.']],
                        'Validation failed',
                        422
                    );
                }
                if (!$site->status) {
                    return new ApiErrorResponse(
                        ['errors' => ['Cannot place order for inactive site. The site must be active to place orders.']],
                        'Site is inactive',
                        422
                    );
                }
            }

            // // Validate stock availability for non-custom products
            // $stockErrors = [];
            // foreach ($productsData as $productId => $productInfo) {
            //     $product = Product::find($productId);
                
            //     if (!$product) {
            //         $stockErrors[] = "Product ID {$productId} not found.";
            //         continue;
            //     }

            //     // Skip stock validation for LPO products
            //     if ($product->store === StoreEnum::LPO) {
            //         continue;
            //     }

            //     $requestedQuantity = (int) ($productInfo['quantity'] ?? 0);
            //     $availableQty = (int) ($product->available_qty ?? 0);

            //     if ($requestedQuantity > $availableQty) {
            //         $stockErrors[] = "Insufficient stock for product '{$product->product_name}' (ID: {$productId}). Available: {$availableQty}, Requested: {$requestedQuantity}.";
            //     }
            // }

            // if (!empty($stockErrors)) {
            //     return new ApiErrorResponse(
            //         ['errors' => $stockErrors],
            //         'Stock not available for some products. Please check the stock availability.',
            //         422
            //     );
            // }

            // Process customer image
            $customerImagePath = $this->processCustomerImage($request);

            // Parse dates
            $saleDate = now()->toDateString();
            $expectedDeliveryDate = $this->parseDeliveryDate($formData['expected_delivery_date'] ?? null);

            // Create a single order with all products (hardware, warehouse, LPO, custom)
            $order = $this->createSingleOrder(
                $request,
                $productsData,
                $customProductsData,
                $formData,
                $customerImagePath,
                $saleDate,
                $expectedDeliveryDate,
                $supplierMapping
            );

            if (!$order) {
                return new ApiErrorResponse(
                    ['errors' => ['No order created.']],
                    'Order creation failed',
                    422
                );
            }

            // Send notifications
            $this->sendOrderCreatedNotifications([$order]);

            // Return response - ensure consistent format
            $orderDetails = Order::with(['site', 'products.category', 'products.productImages', 'customProducts.images'])
                ->where('id', $order->id)
                ->first();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [new OrderResource($orderDetails)],
                message: 'Order created successfully'
            );

        } catch (\Exception $e) {
            Log::error('Order creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['customer_image', 'products'])
            ]);

            return new ApiErrorResponse(
                ['errors' => ['Failed to create order: ' . $e->getMessage()]],
                'Order creation failed',
                500
            );
        }
    }

    /**
     * Prepare and normalize form data from request
     */
    private function prepareFormData(Request $request): array
    {
        $data = $request->all();

        // Ensure products is an array
        if (empty($data['products']) || !is_array($data['products'])) {
            $data['products'] = [];
        }


        // Normalize products array
        foreach ($data['products'] as $index => $product) {
            // Normalize is_custom
            $isCustom = filter_var(
                $product['is_custom'] ?? null,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($isCustom === null) {
                $hasCustomFields = !empty($product['custom_note']) || 
                                  !empty($product['custom_images']) ||
                                  $this->hasCustomImageFiles($request, $index);
                $isCustom = $hasCustomFields;
            }

            $data['products'][$index]['is_custom'] = $isCustom ? 1 : 0;

            // Ensure custom_images is array if provided
            if (isset($product['custom_images']) && !is_array($product['custom_images'])) {
                $data['products'][$index]['custom_images'] = [$product['custom_images']];
            }
        }

        return $data;
    }

    /**
     * Check if custom image files exist for product index
     */
    private function hasCustomImageFiles(Request $request, int $index): bool
    {
        $allFiles = $request->allFiles();
        
        // Check for products[index][custom_images][*] pattern
        foreach ($allFiles as $key => $file) {
            if (preg_match('/^products\[(\d+)\]\[custom_images\](?:\[.*\])?$/', $key, $matches)) {
                $fileIndex = (int) $matches[1];
                if ($fileIndex === $index) {
                    return true;
                }
            }
        }

        // Check for dot notation
        $fileKey = "products.{$index}.custom_images";
        return $request->hasFile($fileKey);
    }

    /**
     * Get validation rules for form-data request
     */
    private function getFormDataValidationRules(Request $request): array
    {
        return [
            'site_id' => 'required|integer|exists:sites,id',
            'expected_delivery_date' => 'nullable|date_format:d/m/Y',
            'priority' => ['required', Rule::enum(PriorityEnum::class)],
            'customer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'products' => 'required|array|min:0',
            'products.*.product_id' => [
                'required_without:products.*.is_custom',
                'nullable',
                'integer',
                'exists:products,id',
            ],
            'products.*.quantity' => [
                'required_without:products.*.is_custom',
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    $index = (int) explode('.', $attribute)[1];
                    $products = $request->input('products', []);
                    $isCustom = filter_var($products[$index]['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    
                    // Skip stock validation for custom products
                    if ($isCustom) {
                        return;
                    }

                    // // Stock availability validation for non-custom products
                    // if (!empty($value)) {
                    //     $productId = $products[$index]['product_id'] ?? null;
                        
                    //     if ($productId) {
                    //         $product = Product::find($productId);
                            
                    //         // Skip stock validation for LPO products
                    //         if ($product && $product->store === StoreEnum::LPO) {
                    //             return; // No stock validation for LPO products
                    //         }

                    //         // Check stock availability
                    //         $siteId = $request->input('site_id');
                            
                    //         // Get general stock (site_id = null)
                    //         $generalStock = $this->stockService->getCurrentStock((int)$productId, null);
                            
                    //         // Get site-specific stock if site_id is provided
                    //         $siteStock = 0.0;
                    //         if ($siteId) {
                    //             $siteStock = $this->stockService->getCurrentStock((int)$productId, (int)$siteId);
                    //         }
                            
                    //         // Total available stock
                    //         $currentStock = $generalStock + $siteStock;
                            
                    //         // Validate quantity against available stock
                    //         if ((float)$value > $currentStock) {
                    //             $fail("Insufficient stock for product. Available: " . number_format($currentStock, 2) . ", Requested: " . number_format((float)$value, 2) . ".");
                    //         }
                    //     }
                    // }
                }
            ],
            'products.*.is_custom' => 'nullable|boolean',
            'products.*.custom_note' => 'nullable|string',
            // Custom product detail fields (stored in JSON)
            'products.*.unit_id' => 'nullable|integer|exists:units,id',
            'products.*.custom_images' => 'nullable|array',
            'products.*.custom_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ];
    }

    /**
     * Process custom product from form data
     */
    private function processCustomProduct(Request $request, array $product, int $index): ?array
    {
        $customNote = trim($product['custom_note'] ?? '');
        // Use the same image collection method as the working orderRequest method
        $customImages = $this->collectCustomImages($request, $product, $index);
        $customImagePaths = $this->storeCustomImages($customImages);
        
        Log::info('Processed custom product images', [
            'index' => $index,
            'images_collected' => count($customImages),
            'image_paths_stored' => count($customImagePaths),
            'image_paths' => $customImagePaths,
        ]);

        // Extract product fields
        $productId = !empty($product['product_id']) ? (int) $product['product_id'] : null;
        $pcs = isset($product['actual_pcs']) && $product['actual_pcs'] !== '' ? (float) $product['actual_pcs'] : null;
        $quantity = isset($product['quantity']) && $product['quantity'] !== '' ? (float) $product['quantity'] : null;
        // $unitId = !empty($product['unit_id']) ? (int) $product['unit_id'] : null;

        // Handle product_ids (for warehouse products connected to custom product)
        $productIds = null;
        if (isset($product['product_ids'])) {
            $productIds = $product['product_ids'];
        }

        // Handle products array (for syncing to order_products with quantities)
        $products = null;
        if (isset($product['products']) && is_array($product['products']) && !empty($product['products'])) {
            $products = $product['products'];
        }

        // Allow custom product if there's at least one field filled (note, images, product_id, quantity)
        if (empty($customNote) && empty($customImagePaths) && empty($productId) && empty($quantity)) {
            return null;
        }

        $result = [
            'product_id' => $productId,
            'actual_pcs' => $pcs,
            'quantity' => $quantity,
            'is_custom' => 1,
            'custom_note' => $customNote ?: null,
            'custom_image_paths' => $customImagePaths, // Store paths to save to new table
        ];

        // Include product_ids if provided
        if ($productIds !== null) {
            $result['product_ids'] = $productIds;
        }

        // Include products array if provided (for syncing warehouse products to order_products)
        if ($products !== null) {
            $result['products'] = $products;
        }

        return $result;
    }

    /**
     * Collect custom images from form-data request
     */
    private function collectCustomImagesFromFormData(Request $request, int $index): array
    {
        $customImages = [];
        $allFiles = $request->allFiles();

        // Log for debugging (can be removed in production)
        if (empty($allFiles)) {
            Log::debug('No files found in request', [
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'all_input_keys' => array_keys($request->all()),
            ]);
        }

        // Check for products[index][custom_images][n] or products[index][custom_images][] patterns
        // This matches: products[1][custom_images][0], products[1][custom_images][], products[1][custom_images]
        foreach ($allFiles as $key => $file) {
            // Match patterns like: products[1][custom_images][0], products[1][custom_images][], products[1][custom_images]
            if (preg_match('/^products\[(\d+)\]\[custom_images\](?:\[.*\])?$/', $key, $matches)) {
                $fileIndex = (int) $matches[1];
                if ($fileIndex === $index) {
                    $files = is_array($file) ? $file : [$file];
                    foreach ($files as $f) {
                        if ($f instanceof \Illuminate\Http\UploadedFile && $f->isValid()) {
                            $this->addUploadedFileIfNew($customImages, $f);
                        }
                    }
                }
            }
        }

        // Check for products.index.custom_images pattern (dot notation)
        $fileKey = "products.{$index}.custom_images";
        if ($request->hasFile($fileKey)) {
            $uploadedFiles = $request->file($fileKey);
            $files = is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles];
            foreach ($files as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $this->addUploadedFileIfNew($customImages, $file);
                }
            }
        }

        // Check in product data array (for base64 or already uploaded files)
        $products = $request->input('products', []);
        if (isset($products[$index]['custom_images']) && !empty($products[$index]['custom_images'])) {
            $additionalImages = is_array($products[$index]['custom_images'])
                ? $products[$index]['custom_images']
                : [$products[$index]['custom_images']];

            foreach ($additionalImages as $img) {
                if ($img instanceof \Illuminate\Http\UploadedFile && $img->isValid()) {
                    $this->addUploadedFileIfNew($customImages, $img);
                }
            }
        }

        return $customImages;
    }

    /**
     * Separate LPO products from regular products based on product's store field
     */
    private function separateLpoProducts(array $productsData): array
    {
        $lpoProductsData = [];
        $regularProductsData = [];

        if (empty($productsData)) {
            return [$lpoProductsData, $regularProductsData];
        }

        $productIds = array_keys($productsData);
        
        // Get all products with their store information
        $products = Product::whereIn('id', $productIds)
            ->select('id', 'store')
            ->get()
            ->keyBy('id');

        // Separate products based on store field
        foreach ($productsData as $productId => $productData) {
            $product = $products->get($productId);
            
            // Check if product's store is LPO
            if ($product && $product->store === StoreEnum::LPO) {
                $lpoProductsData[$productId] = $productData;
            } else {
                $regularProductsData[$productId] = $productData;
            }
        }

        return [$lpoProductsData, $regularProductsData];
    }

    /**
     * Separate products by store type (Hardware, Warehouse, LPO)
     * Custom products are grouped with Warehouse
     * 
     * @param array $productsData
     * @param array $customProductsData
     * @return array Returns grouped products: ['hardware' => [...], 'warehouse' => [...], 'lpo' => [...]]
     */
    private function separateProductsByStoreType(array $productsData, array $customProductsData = []): array
    {
        $groupedProducts = [
            'hardware' => [],
            'warehouse' => [],
            'lpo' => [],
        ];

        // Separate regular products by store type
        if (!empty($productsData)) {
            $productIds = array_keys($productsData);
            
            // Get all products with their store information
            $products = Product::whereIn('id', $productIds)
                ->select('id', 'store')
                ->get()
                ->keyBy('id');

            foreach ($productsData as $productId => $productData) {
                $product = $products->get($productId);
                
                if ($product && $product->store) {
                    if ($product->store === StoreEnum::LPO) {
                        $groupedProducts['lpo'][$productId] = $productData;
                    } elseif ($product->store === StoreEnum::HardwareStore) {
                        $groupedProducts['hardware'][$productId] = $productData;
                    } elseif ($product->store === StoreEnum::WarehouseStore) {
                        $groupedProducts['warehouse'][$productId] = $productData;
                    } else {
                        // Default to warehouse for unknown store types
                        $groupedProducts['warehouse'][$productId] = $productData;
                    }
                } else {
                    // If product not found or no store, default to warehouse
                    $groupedProducts['warehouse'][$productId] = $productData;
                }
            }
        }

        // Custom products are always grouped with Warehouse
        if (!empty($customProductsData)) {
            $groupedProducts['warehouse']['custom_products'] = $customProductsData;
        }

        return $groupedProducts;
    }


    /**
     * Resolve store manager from regular products (exclude LPO)
     */
    private function resolveStoreManager(array $regularProductsData, array $customProductsData = []): ?int
    {
        // If we have only custom products (no regular products), assign Warehouse/Workshop Store Manager
        if (empty($regularProductsData) && !empty($customProductsData)) {
            $warehouseManager = Moderator::where('role', RoleEnum::WorkshopStoreManager->value)
                ->where('status', StatusEnum::Active->value)
                ->first();

            if ($warehouseManager) {
                return (int) $warehouseManager->id;
            }
            return $this->getDefaultStoreManager();
        }

        // If we have regular products, try to resolve store manager from them
        if (!empty($regularProductsData)) {
            $productIds = array_keys($regularProductsData);
            
            // First, try to get store manager directly from products
            $storeManagerId = Product::whereIn('id', $productIds)
                ->where('store', '!=', StoreEnum::LPO)
                ->whereNotNull('store_manager_id')
                ->value('store_manager_id');
            
            if ($storeManagerId) {
                return (int) $storeManagerId;
            }
            
            // If no direct store_manager_id, resolve from store enum
            $products = Product::whereIn('id', $productIds)
                ->where('store', '!=', StoreEnum::LPO)
                ->select('id', 'store')
                ->get();
            
            foreach ($products as $product) {
                if ($product->store) {
                    $resolvedManagerId = $this->resolveStoreManagerFromStoreEnum($product->store);
                    if ($resolvedManagerId) {
                        return $resolvedManagerId;
                    }
                }
            }
        }
        
        // If we have regular products or custom products, always return a default store manager
        // This ensures orders always have a store_manager_id assigned
        if (!empty($regularProductsData) || !empty($customProductsData)) {
            return $this->getDefaultStoreManager();
        }
        
        return null;
    }
    
    /**
     * Resolve store manager ID from store enum
     */
    private function resolveStoreManagerFromStoreEnum(StoreEnum $store): ?int
    {
        $role = match($store) {
            StoreEnum::HardwareStore => RoleEnum::StoreManager,
            StoreEnum::WarehouseStore => RoleEnum::WorkshopStoreManager,
            StoreEnum::LPO => null, // LPO orders don't have store manager
        };
        
        if (!$role) {
            return null;
        }
        
        $manager = Moderator::where('role', $role->value)
            ->where('status', StatusEnum::Active->value)
            ->first();
        
        return $manager?->id;
    }
    
    /**
     * Get default active store manager as fallback
     */
    private function getDefaultStoreManager(): ?int
    {
        // Try Hardware Store Manager first
        $manager = Moderator::where('role', RoleEnum::StoreManager->value)
            ->where('status', StatusEnum::Active->value)
            ->first();
        
        if ($manager) {
            return $manager->id;
        }
        
        // Fallback to Workshop Store Manager
        $manager = Moderator::where('role', RoleEnum::WorkshopStoreManager->value)
            ->where('status', StatusEnum::Active->value)
            ->first();
        
        return $manager?->id;
    }

    /**
     * Process customer image from form-data
     */
    private function processCustomerImage(Request $request): ?string
    {
        if ($request->hasFile('customer_image')) {
            $file = $request->file('customer_image');
            if ($file->isValid()) {
                return $file->store('orders/customer-images', 'public');
            }
        }

        return null;
    }

    /**
     * Parse delivery date from dd/MM/yyyy format
     */
    private function parseDeliveryDate(?string $dateString): string
    {
        if (empty($dateString)) {
            return now()->toDateString();
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $dateString)->toDateString();
        } catch (\Exception $e) {
            Log::warning('Invalid date format: ' . $dateString);
            return now()->toDateString();
        }
    }

    /**
     * Create a single order with all products (hardware, warehouse, LPO, custom)
     * All products are managed under one order
     * 
     * @param Request $request
     * @param array $productsData Regular products array [product_id => ['quantity' => ...]]
     * @param array $customProductsData Custom products array
     * @param array $formData
     * @param string|null $customerImagePath
     * @param string $saleDate
     * @param string $expectedDeliveryDate
     * @param array $supplierMapping Supplier mapping for LPO products [product_id => supplier_id]
     * @return Order|null
     */
    private function createSingleOrder(
        Request $request,
        array $productsData,
        array $customProductsData,
        array $formData,
        ?string $customerImagePath,
        string $saleDate,
        string $expectedDeliveryDate,
        array $supplierMapping = []
    ): ?Order {
        $user = $request->user();
        if (!$user) {
            throw new \RuntimeException('User not authenticated.');
        }

        // Determine if order has LPO products
        $hasLpoProducts = false;
        $hasHardwareProducts = false;
        $hasWarehouseProducts = false;
        $hasCustomProducts = !empty($customProductsData);

        if (!empty($productsData)) {
            $productIds = array_keys($productsData);
            $products = Product::whereIn('id', $productIds)
                ->select('id', 'store')
                ->get();

            foreach ($products as $product) {
                if ($product->store === StoreEnum::LPO) {
                    $hasLpoProducts = true;
                } elseif ($product->store === StoreEnum::HardwareStore) {
                    $hasHardwareProducts = true;
                } elseif ($product->store === StoreEnum::WarehouseStore) {
                    $hasWarehouseProducts = true;
                }
            }
        }

        // Determine primary store and store manager role
        // Priority: Hardware > Warehouse > LPO
        $storeManagerRole = null;
        $store = null;
        
        if ($hasHardwareProducts) {
            $storeManagerRole = RoleEnum::StoreManager->value;
            $store = StoreEnum::HardwareStore->value;
        } elseif ($hasWarehouseProducts || $hasCustomProducts) {
            $storeManagerRole = RoleEnum::WorkshopStoreManager->value;
            $store = StoreEnum::WarehouseStore->value;
        } elseif ($hasLpoProducts) {
            $storeManagerRole = null;
            $store = StoreEnum::LPO->value;
        } else {
            // Default to warehouse if no products
            $storeManagerRole = RoleEnum::WorkshopStoreManager->value;
            $store = StoreEnum::WarehouseStore->value;
        }

        // Initialize product_status based on product groups
        $productStatus = [
            'hardware' => $hasHardwareProducts ? 'pending' : null,
            'warehouse' => ($hasWarehouseProducts || $hasCustomProducts) ? 'pending' : null,
            'lpo' => [], // Supplier-wise: {supplier_id: status}
            'custom' => $hasCustomProducts ? 'pending' : null,
        ];
        
        // Initialize LPO status with supplier-specific statuses
        if ($hasLpoProducts && !empty($supplierMapping)) {
            foreach ($supplierMapping as $productId => $supplierId) {
                $productStatus['lpo'][(string)$supplierId] = 'pending';
            }
        }

        // Create single order
        $order = new Order();
        $order->site_manager_id = $user->id;
        $order->site_id = $formData['site_id'];
        $order->priority = $formData['priority'];
        $order->sale_date = $saleDate;
        $order->expected_delivery_date = $expectedDeliveryDate;
        $order->note = $formData['notes'] ?? null;
        $order->customer_image = $customerImagePath;
        $order->is_lpo = $hasLpoProducts;
        $order->is_custom_product = $hasCustomProducts;
        $order->status = OrderStatusEnum::Pending;
        $order->is_completed = false;
        $order->store_manager_role = $storeManagerRole;
        $order->store = $store;
        $order->product_status = $productStatus;
        $order->supplier_id = $supplierMapping;
        $order->save();

        // Attach all regular products to the single order
        if (!empty($productsData)) {
            $order->products()->sync($productsData);
        }

        // Attach custom products
        if (!empty($customProductsData)) {
            foreach ($customProductsData as $customProduct) {
                $imagePaths = $customProduct['custom_image_paths'] ?? [];
                
                if (!is_array($imagePaths)) {
                    $imagePaths = [];
                    Log::warning('Invalid custom_image_paths format, converting to array', [
                        'order_id' => $order->id,
                        'custom_image_paths_type' => gettype($customProduct['custom_image_paths'] ?? null),
                    ]);
                }
                
                Log::info('Creating custom product with images', [
                    'order_id' => $order->id,
                    'image_paths_count' => count($imagePaths),
                    'image_paths' => $imagePaths,
                    'custom_product_data_keys' => array_keys($customProduct),
                ]);
                
                $this->customProductManager->create(
                    $order->id,
                    $customProduct,
                    $imagePaths
                );
            }
        }

        $order->refresh();
        return $order;
    }

    /**
     * Create orders (regular and/or LPO)
     * NOTE: This method is deprecated, use createOrdersByStoreType instead
     */
    private function createOrders(
        Request $request,
        array $regularProductsData,
        array $customProductsData,
        array $lpoProductsData,
        array $formData,
        ?string $customerImagePath,
        string $saleDate,
        string $expectedDeliveryDate,
        ?int $storeManagerId
    ): array {
        $createdOrders = [];

        // Create regular order (if we have regular products)
        if (!empty($regularProductsData)) {
            // Resolve role and store from storeManagerId if provided
            $storeManagerRole = null;
            $store = null;
            if ($storeManagerId) {
                $manager = Moderator::find($storeManagerId);
                if ($manager) {
                    $storeManagerRole = $manager->role;
                    // Determine store based on role
                    if ($manager->role === RoleEnum::StoreManager->value) {
                        $store = StoreEnum::HardwareStore->value;
                    } elseif ($manager->role === RoleEnum::WorkshopStoreManager->value) {
                        $store = StoreEnum::WarehouseStore->value;
                    }
                }
            }
            
            $regularOrder = $this->createRegularOrder(
                $request,
                $formData,
                $customerImagePath,
                $saleDate,
                $expectedDeliveryDate,
                $storeManagerRole,
                $store,
                false
            );

            if (!empty($regularProductsData)) {
                $regularOrder->products()->sync($regularProductsData);
            }

            $regularOrder->refresh();
            $createdOrders[] = $regularOrder;
        }

        // Create LPO order (if we have LPO products)
        if (!empty($lpoProductsData)) {
            $lpoOrder = $this->createLpoOrder(
                $request,
                $formData,
                $customerImagePath,
                $saleDate,
                $expectedDeliveryDate,
                false,
                StoreEnum::LPO->value
            );

            if (!empty($lpoProductsData)) {
                $lpoOrder->products()->sync($lpoProductsData);
            }

            $lpoOrder->refresh();
            $createdOrders[] = $lpoOrder;
        }

        // Create pure custom-product order whenever there are custom products
        if (!empty($customProductsData)) {
            $customOrder = $this->createCustomOrder(
                $request,
                $formData,
                $customerImagePath,
                $saleDate,
                $expectedDeliveryDate
            );

            foreach ($customProductsData as $customProduct) {
                $this->customProductManager->create(
                    $customOrder->id,
                    $customProduct,
                    $customProduct['custom_image_paths'] ?? []
                );
            }

            $customOrder->refresh();
            $createdOrders[] = $customOrder;
        }
        return $createdOrders;
    }

    /**
     * Create regular order
     */
    private function createRegularOrder(
        Request $request,
        array $formData,
        ?string $customerImagePath,
        string $saleDate,
        string $expectedDeliveryDate,
        ?string $storeManagerRole,
        ?string $store,
        bool $isCustomProduct = false
    ): Order {
        $user = $request->user();
        if (!$user) {
            throw new \RuntimeException('User not authenticated.');
        }

        $order = new Order();
        $order->site_manager_id = $user->id;
        $order->site_id = $formData['site_id'];
        $order->priority = $formData['priority'];
        $order->sale_date = $saleDate;
        $order->expected_delivery_date = $expectedDeliveryDate;
        $order->note = $formData['notes'] ?? null;
        $order->customer_image = $customerImagePath;
        $order->is_lpo = false;
        $order->is_custom_product = $isCustomProduct;
        $order->status = OrderStatusEnum::Pending;
        $order->is_completed = false;

        // Set store_manager_role and store
        $order->store_manager_role = $storeManagerRole;
        $order->store = $store;

        $order->save();
        return $order;
    }

    /**
     * Create LPO order
     */
    private function createLpoOrder(
        Request $request,
        array $formData,
        ?string $customerImagePath,
        string $saleDate,
        string $expectedDeliveryDate,
        bool $isCustomProduct = false,
        ?string $store = null
    ): Order {
        $user = $request->user();
        if (!$user) {
            throw new \RuntimeException('User not authenticated.');
        }

        $order = new Order();
        $order->site_manager_id = $user->id;
        $order->site_id = $formData['site_id'];
        $order->priority = $formData['priority'];
        $order->sale_date = $saleDate;
        $order->expected_delivery_date = $expectedDeliveryDate;
        $order->note = $formData['notes'] ?? null;
        $order->customer_image = $customerImagePath;
        $order->is_lpo = true;
        $order->is_custom_product = $isCustomProduct;
        $order->status = OrderStatusEnum::Pending;
        $order->is_completed = false;

        // LPO orders don't have store_manager_role, but have store
        $order->store_manager_role = null;
        $order->store = $store;

        $order->save();
        return $order;
    }

    /**
     * Create a pure custom-product order (no regular or LPO products)
     */
    private function createCustomOrder(
        Request $request,
        array $formData,
        ?string $customerImagePath,
        string $saleDate,
        string $expectedDeliveryDate
    ): Order {
        $user = $request->user();
        if (!$user) {
            throw new \RuntimeException('User not authenticated.');
        }

        $order = new Order();
        $order->site_manager_id = $user->id;
        $order->site_id = $formData['site_id'];
        $order->priority = $formData['priority'];
        $order->sale_date = $saleDate;
        $order->expected_delivery_date = $expectedDeliveryDate;
        $order->note = $formData['notes'] ?? null;
        $order->customer_image = $customerImagePath;
        $order->is_lpo = false;
        $order->is_custom_product = true;
        $order->status = OrderStatusEnum::Pending;
        $order->is_completed = false;

        // Custom products are assigned to Warehouse/Workshop Store Manager
        $order->store_manager_role = RoleEnum::WorkshopStoreManager->value;
        $order->store = StoreEnum::WarehouseStore->value;

        $order->save();
        return $order;
    }

    /**
     * Send notifications to store managers for created orders
     */
    private function sendOrderCreatedNotifications(array $createdOrders): void
    {
        $storeManagers = Moderator::where('role', RoleEnum::StoreManager->value)
            ->where('status', 'active')
            ->get();

        if ($storeManagers->isEmpty()) {
            Log::warning('No active Store Managers found to notify about orders');
            return;
        }

        foreach ($createdOrders as $order) {
            foreach ($storeManagers as $storeManager) {
                try {
                    $storeManager->notify(new OrderCreatedNotification($order));
                } catch (\Exception $e) {
                    Log::error('Failed to send notification to Store Manager: ' . $storeManager->email . ' - ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Delete products from order using form-data
     * 
     * This endpoint is specifically designed for deleting products from orders
     * using multipart/form-data requests.
     * 
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function deleteOrderProductsWithFormData(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            // Get form data from request
            $formData = $this->getFormDataFromRequest($request);
            
            // Validate the request
            $orderType = $formData['order_type'] ?? null;
            $validationRules = [
                'order_type' => 'required|string|in:0,1',
                'order_id' => 'required|integer|exists:orders,id',
            ];
    
            if ($orderType === '0') {
                $validationRules['is_custom'] = 'sometimes|nullable|boolean';
                $validationRules['product_id'] = 'required_without:custom_product_id|nullable|integer|exists:products,id';
                $validationRules['custom_product_id'] = 'required_without:product_id|nullable|integer|exists:order_custom_products,id';
            }
    
            $validator = Validator::make($formData, $validationRules);
    
            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'Validation failed',
                    422
                );
            }

            $user = $request->user();
            $orderId = $formData['order_id'];
            $orderType = $formData['order_type'];
            $isCustom = filter_var($formData['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);

            // Find order
            $orderDetails = Order::where('site_manager_id', $user->id)
                ->where('id', $orderId)
                ->first();

            if (!$orderDetails) {
                return new ApiErrorResponse(
                    ['errors' => ['Order not found']],
                    'order operation failed',
                    404
                );
            }

            // Check if order can be modified - based on main status (delivery_status is deprecated)
            $immutableStatuses = ['approved', 'in_transit', 'delivered', 'rejected'];
            $currentStatus = $orderDetails->status?->value ?? $orderDetails->status ?? 'pending';
            if (in_array($currentStatus, $immutableStatuses, true)) {
                return new ApiErrorResponse(
                    ['errors' => ['Processed orders cannot be modified.']],
                    'order update failed',
                    422
                );
            }

            // Use only the single order (no parent/child relationships)
            $ordersToUpdate = collect([$orderDetails])
                ->filter(fn ($item) => $item->site_manager_id === $user->id)
                ->unique('id')
                ->values();

            if ($ordersToUpdate->isEmpty()) {
                return new ApiErrorResponse(
                    ['errors' => ['You do not have permission to modify this order.']],
                    'order update failed',
                    403
                );
            }

            // Check immutable statuses for all related orders (using main status)
            foreach ($ordersToUpdate as $item) {
                $itemStatus = $item->status?->value ?? $item->status ?? 'pending';
                if (in_array($itemStatus, $immutableStatuses, true)) {
                    return new ApiErrorResponse(
                        ['errors' => ['One or more related orders are already processed and cannot be modified.']],
                        'order update failed',
                        422
                    );
                }
            }

            $orderIds = $ordersToUpdate->pluck('id')->toArray();
            $deletedCount = 0;

            DB::beginTransaction();

            try {
                if ($orderType === '1') {
                    foreach ($ordersToUpdate as $order) {
                        $order->delete();
                    }
    
                    DB::commit();
    
                    return new ApiResponse(
                        isError: false,
                        code: 200,
                        data: [],
                        message: 'Order deleted successfully.',
                    );
                }
                
                if ($isCustom) {
                    // Delete custom product
                    $customProductId = $formData['custom_product_id'] ?? null;
                    
                    if ($customProductId) {
                        $affectedRows = OrderCustomProduct::where('id', $customProductId)
                            ->whereIn('order_id', $orderIds)
                            ->delete();
                        if ($affectedRows > 0) {
                            $deletedCount++;
                        }
                    }
                } else {
                    // Delete regular product
                    $productId = $formData['product_id'] ?? null;
                    
                    if ($productId) {
                        $affectedRows = 0;
                        foreach ($ordersToUpdate as $item) {
                            $affectedRows += DB::table('order_products')
                                ->where('order_id', $item->id)
                                ->where('product_id', $productId)
                                ->delete();
                        }
                        if ($affectedRows > 0) {
                            $deletedCount++;
                        }
                    }
                }

                if ($deletedCount === 0) {
                    DB::rollBack();
                    return new ApiErrorResponse(
                        ['errors' => ['No products were deleted. Products may not exist in the order.']],
                        'delete order products failed',
                        404
                    );
                }

                // Check if order has any products left
                $remainingProductsCount = DB::table('order_products')
                    ->where('order_id', $orderDetails->id)
                    ->count();
                
                $remainingCustomProductsCount = OrderCustomProduct::where('order_id', $orderDetails->id)
                    ->count();

                $orderIsEmpty = ($remainingProductsCount === 0 && $remainingCustomProductsCount === 0);

                if ($orderIsEmpty) {
                    // Delete the order if it has no products left
                    $orderDetails->delete();

                    DB::commit();

                    return new ApiResponse(
                        isError: false,
                        code: 200,
                        data: [],
                        message: 'Product removed and order deleted successfully as it had no remaining products.',
                    );
                }

                DB::commit();

                // Reload with relationships for response
                $orderDetails = Order::with(['site', 'products.category', 'products.productImages', 'customProducts.images'])
                    ->where('id', $orderDetails->id)
                    ->first();

                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: OrderResource::collection([$orderDetails]),
                    message: 'Product removed from order successfully.',
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Order product deletion failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return new ApiErrorResponse(
                [],
                'Failed to delete products: ' . $e->getMessage(),
                500
            );
        }
    }

    public function assignTransportManager(Request $request): ApiResponse|ApiErrorResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            // 'transport_id' => 'nullable|integer|exists:moderators,id|min:1',
            'driver_name' => 'nullable|string',
            'vehicle_number' => 'nullable|string',
            'action_type' => 'required|string|in:in_transit,outfordelivery,delivered',
        ]);

        $transport_id = Moderator::where('role', RoleEnum::TransportManager->value)->where('status', 'active')->first()->id ?? null;
        $request->merge(['transport_id' => $transport_id ?? null]);

        // if ($validator->fails()) {
        //     return new ApiErrorResponse(
        //         ['errors' => $validator->errors()],
        //         'transport manager assignment failed',
        //         422
        //     );
        // }

        try {
            DB::beginTransaction();

            $order = Order::findOrFail($request->order_id);
            
            // Convert transport_id to integer, but set to null if 0 or null
            // This prevents foreign key constraint violation (0 is not a valid moderator ID)
            $transportManagerId = $request->transport_id ? (int)$request->transport_id : null;
            if ($transportManagerId === 0) {
                $transportManagerId = null;
            }

            // Validate transport manager (using find to avoid exception if not found)
            $transportManager = $transportManagerId ? Moderator::find($transportManagerId) : null;
            // if ($transportManager && ($transportManager->role !== RoleEnum::TransportManager->value || $transportManager->status !== 'active')) {
            //     DB::rollBack();
            //     return new ApiErrorResponse([], 'Invalid transport manager selected. The selected user must be an active transport manager.', 422);
            // }

            // // Only allow assignment when delivery_status is approved
            // if ($order->delivery_status !== 'approved') {
            //     DB::rollBack();
            //     return new ApiErrorResponse(
            //         ['errors' => ['Transport manager can only be assigned when order delivery status is approved.']],
            //         'transport manager assignment failed',
            //         422
            //     );
            // }

            $oldTransportManagerId = $order->transport_manager_id;
            
            // Update order with transport manager, driver name, and vehicle number
            if($transportManagerId) {
            $updateData = [
                    'transport_manager_id' => $transportManagerId,
                ];
            }

            // Add driver name if provided
            if ($request->has('driver_name')) {
                $updateData['driver_name'] = $request->driver_name;
            }

            // Add vehicle number if provided
            if ($request->has('vehicle_number')) {
                $updateData['vehicle_number'] = $request->vehicle_number;
            }

            // Update main status based on action_type (delivery_status column is deprecated)
            if ($request->action_type === 'in_transit') {
                $updateData['status'] = OrderStatusEnum::InTransit->value;
            } elseif ($request->action_type === 'outfordelivery') {
                $updateData['status'] = OrderStatusEnum::OutOfDelivery->value;
            } elseif ($request->action_type === 'delivered') {
                $updateData['status'] = OrderStatusEnum::Delivery->value;
                $updateData['is_completed'] = true;
                $updateData['completed_at'] = now();
            }

            $order->update($updateData);
            
            // Check if order should be marked as completed (for mixed orders) - after update
            if ($request->action_type === 'delivered') {
                Order::syncMixedOrderCompletion($order);
            }

            // Send notification when transport manager is assigned for the first time
            if (!$oldTransportManagerId && isset($transportManager)) {
                try {
                    $transportManager->notify(new \App\Notifications\TransportManagerAssignedNotification($order));
                } catch (\Exception $e) {
                    Log::error('Failed to send notification to Transport Manager: ' . ($transportManager->email ?? 'ID: ' . $transportManagerId) . ' - ' . $e->getMessage());
                }
            }

            DB::commit();

            // Reload order with relationships
            $order->refresh();
            $order->load(['site', 'transportManager', 'products.category', 'products.productImages', 'customProducts.images']);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: OrderResource::collection([$order]),
                message: 'Transport manager assigned successfully!',
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return new ApiErrorResponse(
                ['errors' => ['Order or transport manager not found.']],
                'transport manager assignment failed',
                404
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign transport manager: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return new ApiErrorResponse(
                [],
                'Failed to assign transport manager: ' . $e->getMessage(),
                500
            );
        }
    }

    protected function deductStockForOrder($order, array $productsData, ?int $siteId = null): ?ApiErrorResponse
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        // Determine the order's store type
        $orderStore = null;
        if ($order->store) {
            try {
                if ($order->store instanceof StoreEnum) {
                    $orderStore = $order->store;
                } else {
                    $orderStore = StoreEnum::from($order->store);
                }
            } catch (\ValueError $e) {
                // If store value doesn't match any enum, try to determine from store_manager_role
                Log::warning("OrderController: Invalid store value '{$order->store}' for order #{$order->id}, falling back to store_manager_role");
            }
        }
        
        // If store couldn't be determined from store field, use store_manager_role
        if (!$orderStore && $order->store_manager_role) {
            // Map store_manager_role to store type
            if ($order->store_manager_role === RoleEnum::StoreManager->value) {
                $orderStore = StoreEnum::HardwareStore;
            } elseif ($order->store_manager_role === RoleEnum::WorkshopStoreManager->value) {
                $orderStore = StoreEnum::WarehouseStore;
            }
        }

        foreach ($productsData as $productId => $productInfo) {
            $product = Product::with('materials')->find($productId);
            
            if (!$product) {
                continue;
            }
            
            // Skip LPO products
            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }

            // Filter products by store type: only check stock for products matching the order's store
            if ($orderStore) {
                // If order has a specific store type, only process products of that store type
                if ($product->store && $product->store !== $orderStore) {
                    // Skip products that don't match the order's store type
                    $productStoreValue = $product->store instanceof StoreEnum ? $product->store->value : (string)$product->store;
                    $orderStoreValue = $orderStore instanceof StoreEnum ? $orderStore->value : (string)$orderStore;
                    Log::info("OrderController: Skipping stock check for product ID {$productId} (store: {$productStoreValue}) as it doesn't match order store ({$orderStoreValue}) for order #{$order->id}");
                    continue;
                }
            }

            $quantity = (int)($productInfo['quantity'] ?? 0);
            
            if ($quantity > 0) {
                $generalStock = $this->stockService->getCurrentStock((int)$productId, null);
                $siteStock = $siteId ? $this->stockService->getCurrentStock((int)$productId, $siteId) : 0;
                $totalAvailableStock = $generalStock + $siteStock;

                // Check stock availability - all products must have sufficient stock to approve
                // For hardware orders with multiple products (p1, p2, etc.), all must have stock available
                Log::info("OrderController: Checking stock for product ID {$productId} in order #{$order->id}. Available: {$totalAvailableStock} (General: {$generalStock}, Site: {$siteStock}), Requested: {$quantity}");
                
                if ($totalAvailableStock < $quantity) {
                    $productName = $product->product_name ?? $product->name ?? "Product ID {$productId}";
                    return new ApiErrorResponse(
                        ['errors' => ["Insufficient stock for {$productName} (ID: {$productId}). Available: {$totalAvailableStock}, Requested: {$quantity}"]],
                        'Insufficient stock for some products. Please check the stock availability.',
                        422
                    );
                }
                
                Log::info("OrderController: Stock available for product ID {$productId} in order #{$order->id}. Proceeding with stock deduction.");

                $remainingQuantity = $quantity;
                
                if ($siteId && $siteStock > 0) {
                    $deductFromSite = min($remainingQuantity, $siteStock);
                    if ($deductFromSite > 0) {
                        try {
                            $this->stockService->adjustStock(
                                (int)$productId,
                                $deductFromSite,
                                'out',
                                $siteId,
                                "Stock deducted for Order #{$order->id} (quantity: {$deductFromSite})",
                                $order,
                                "Order #{$order->id} - Stock Deducted"
                            );
                            $remainingQuantity -= $deductFromSite;
                        } catch (\Exception $e) {
                            Log::error("OrderController: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage());
                            $productName = $product->product_name ?? $product->name ?? "Product ID {$productId}";
                            return new ApiErrorResponse(
                                ['errors' => ["Failed to deduct stock for {$productName}. " . $e->getMessage()]],
                                'Failed to deduct stock. Please try again.',
                                422
                            );
                        }
                    }
                }
                
                if ($remainingQuantity > 0 && $generalStock > 0) {
                    $deductFromGeneral = min($remainingQuantity, $generalStock);
                    if ($deductFromGeneral > 0) {
                        try {
                            $this->stockService->adjustStock(
                                (int)$productId,
                                $deductFromGeneral,
                                'out',
                                null,
                                "Stock deducted for Order #{$order->id} (quantity: {$deductFromGeneral})",
                                $order,
                                "Order #{$order->id} - Stock Deducted"
                            );
                            $remainingQuantity -= $deductFromGeneral;
                        } catch (\Exception $e) {
                            Log::error("OrderController: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage());
                            $productName = $product->product_name ?? $product->name ?? "Product ID {$productId}";
                            return new ApiErrorResponse(
                                ['errors' => ["Failed to deduct stock for {$productName}. " . $e->getMessage()]],
                                'Failed to deduct stock. Please try again.',
                                422
                            );
                        }
                    }
                }
                
                // All users must have sufficient stock - fail if we couldn't deduct all remaining quantity
                if ($remainingQuantity > 0) {
                    $productName = $product->product_name ?? $product->name ?? "Product ID {$productId}";
                    return new ApiErrorResponse(
                        ['errors' => ["Insufficient stock for {$productName} (ID: {$productId}). Could not deduct remaining quantity: {$remainingQuantity}"]],
                        'Insufficient stock for some products. Please check the stock availability.',
                        422
                    );
                }

                foreach ($product->materials as $material) {
                    $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                    if ($materialQtyPerUnit <= 0) {
                        continue;
                    }

                    $materialTotalQty = (int)($materialQtyPerUnit * $quantity);

                    $materialGeneralStock = $this->stockService->getCurrentMaterialStock((int)$material->id, null);
                    $materialSiteStock = $siteId ? $this->stockService->getCurrentMaterialStock((int)$material->id, $siteId) : 0;
                    $materialTotalAvailable = $materialGeneralStock + $materialSiteStock;

                    // All users must have sufficient material stock to approve
                    if ($materialTotalAvailable < $materialTotalQty) {
                        $materialName = $material->material_name ?? $material->product_name ?? $material->name ?? "Material ID {$material->id}";
                        return new ApiErrorResponse(
                            ['errors' => ["Insufficient material stock for {$materialName} (ID: {$material->id}). Available: {$materialTotalAvailable}, Requested: {$materialTotalQty}"]],
                            'Insufficient material stock for some products. Please check the stock availability.',
                            422
                        );
                    }

                    $materialRemainingQty = $materialTotalQty;
                    
                    if ($siteId && $materialSiteStock > 0) {
                        $deductMaterialFromSite = min($materialRemainingQty, $materialSiteStock);
                        if ($deductMaterialFromSite > 0) {
                            try {
                                $this->stockService->adjustMaterialStock(
                                    (int)$material->id,
                                    $deductMaterialFromSite,
                                    'out',
                                    $siteId,
                                    "Material stock deducted for Order #{$order->id} (product: {$product->product_name}, ordered qty: {$quantity}, material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: {$deductMaterialFromSite})",
                                    $order,
                                    "Order #{$order->id} - Material {$material->material_name} Deducted",
                                    [
                                        'product_id' => $product->id,
                                        'product_quantity' => $quantity,
                                        'material_quantity_per_unit' => $materialQtyPerUnit,
                                        'material_total_quantity' => $deductMaterialFromSite,
                                    ]
                                );
                                $materialRemainingQty -= $deductMaterialFromSite;
                            } catch (\Exception $e) {
                                Log::error("OrderController: Failed to deduct material stock for material {$material->id} in order {$order->id}: " . $e->getMessage());
                                $materialName = $material->material_name ?? $material->product_name ?? $material->name ?? "Material ID {$material->id}";
                                return new ApiErrorResponse(
                                    ['errors' => ["Failed to deduct material stock for {$materialName}. " . $e->getMessage()]],
                                    'Failed to deduct material stock. Please try again.',
                                    422
                                );
                            }
                        }
                    }
                    
                    if ($materialRemainingQty > 0 && $materialGeneralStock > 0) {
                        $deductMaterialFromGeneral = min($materialRemainingQty, $materialGeneralStock);
                        if ($deductMaterialFromGeneral > 0) {
                            try {
                                $this->stockService->adjustMaterialStock(
                                    (int)$material->id,
                                    $deductMaterialFromGeneral,
                                    'out',
                                    null,
                                    "Material stock deducted for Order #{$order->id} (product: {$product->product_name}, ordered qty: {$quantity}, material per unit: " . number_format($materialQtyPerUnit, 2) . ", total material qty: {$deductMaterialFromGeneral})",
                                    $order,
                                    "Order #{$order->id} - Material {$material->material_name} Deducted",
                                    [
                                        'product_id' => $product->id,
                                        'product_quantity' => $quantity,
                                        'material_quantity_per_unit' => $materialQtyPerUnit,
                                        'material_total_quantity' => $deductMaterialFromGeneral,
                                    ]
                                );
                                $materialRemainingQty -= $deductMaterialFromGeneral;
                            } catch (\Exception $e) {
                                Log::error("OrderController: Failed to deduct material stock for material {$material->id} in order {$order->id}: " . $e->getMessage());
                                $materialName = $material->material_name ?? $material->product_name ?? $material->name ?? "Material ID {$material->id}";
                                return new ApiErrorResponse(
                                    ['errors' => ["Failed to deduct material stock for {$materialName}. " . $e->getMessage()]],
                                    'Failed to deduct material stock. Please try again.',
                                    422
                                );
                            }
                        }
                    }
                    
                    // All users must have sufficient material stock - fail if we couldn't deduct all remaining quantity
                    if ($materialRemainingQty > 0) {
                        $materialName = $material->material_name ?? $material->product_name ?? $material->name ?? "Material ID {$material->id}";
                        return new ApiErrorResponse(
                            ['errors' => ["Insufficient material stock for {$materialName} (ID: {$material->id}). Could not deduct remaining quantity: {$materialRemainingQty}"]],
                            'Insufficient material stock for some products. Please check the stock availability.',
                            422
                        );
                    }
                }
            }
        }
        
        // Return null to indicate success (no errors)
        return null;
    }

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

            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }

            $quantity = (int)($productInfo['quantity'] ?? 0);
            
            if ($quantity > 0) {
                try {
                    $this->stockService->adjustStock(
                        (int)$productId,
                        (int)$quantity,
                        'in',
                        $siteId,
                        "Stock restored for Order #{$order->id} (quantity: " . number_format($quantity, 2) . ")",
                        $order,
                        "Order #{$order->id} - Stock Restored"
                    );

                    foreach ($product->materials as $material) {
                        $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                        if ($materialQtyPerUnit <= 0) {
                            continue;
                        }

                        $materialTotalQty = (int)($materialQtyPerUnit * $quantity);

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
                    Log::error("OrderController: Failed to restore stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
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

            if ($product->store && $product->store === StoreEnum::LPO) {
                continue;
            }

            $oldQuantity = isset($oldProductsData[$productId]) ? (int)($oldProductsData[$productId]['quantity'] ?? 0) : 0;
            $newQuantity = isset($newProductsData[$productId]) ? (int)($newProductsData[$productId]['quantity'] ?? 0) : 0;
            $difference = $newQuantity - $oldQuantity;

            if ($difference != 0) {
                if ($difference > 0) {
                    try {
                        $this->stockService->adjustStock(
                            (int)$productId,
                            $difference,
                            'out',
                            $siteId,
                            "Stock adjusted for Order #{$order->id} (quantity increased: -{$difference})",
                            $order,
                            "Order #{$order->id} - Stock Deducted"
                        );

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
                        Log::error("OrderController: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                        throw new \Exception("Insufficient stock for product or materials. " . $e->getMessage());
                    }
                } else {
                    $restoreAmount = abs($difference);
                    try {
                        $this->stockService->adjustStock(
                            (int)$productId,
                            $restoreAmount,
                            'in',
                            $siteId,
                            "Stock adjusted for Order #{$order->id} (quantity decreased: +" . number_format($restoreAmount, 2) . ")",
                            $order,
                            "Order #{$order->id} - Stock Restored"
                        );

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
                        Log::error("OrderController: Failed to restore stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    }
                }
            }
        }
    }

    /**
     * Update product_status for multiple products in a single order
     * Supports updating status for hardware, warehouse, and LPO products in one request
     * 
     * Example: ABC order with:
     * - P1 -> HARDWARE
     * - P2 -> WAREHOUSE
     * - P3 -> LPO (S1 supplier)
     * - P4 -> LPO (S2 supplier)
     * 
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function updateOrderProductsStatus(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|integer|exists:orders,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required', // Can be integer (product ID) or string (product type: 'hardware', 'warehouse')
                'products.*.status' => 'required|string|in:pending,approved,rejected',
                'products.*.supplier_id' => 'sometimes|nullable|integer|exists:suppliers,id',
            ]);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    data: ['errors' => $validator->errors()->all()],
                    message: $validator->errors()->first(),
                    code: 422
                );
            }

            $order = Order::with('products')->findOrFail($request->order_id);
            
            // Additional validation: LPO products require supplier_id (only for actual product IDs, not product types)
            foreach ($request->products as $index => $productUpdate) {
                $productIdInput = $productUpdate['product_id'];
                
                // Skip validation for product type strings (hardware, warehouse)
                if (is_string($productIdInput) && in_array($productIdInput, ['hardware', 'warehouse'], true)) {
                    continue;
                }
                
                // Validate individual product IDs
                if (!is_numeric($productIdInput)) {
                    return new ApiErrorResponse(
                        data: ['errors' => ["Invalid product_id: {$productIdInput}. Must be product ID (integer) or product type ('hardware', 'warehouse')"]],
                        message: "Invalid product_id: {$productIdInput}. Must be product ID (integer) or product type ('hardware', 'warehouse')",
                        code: 422
                    );
                }
                
                $product = $order->products->firstWhere('id', (int)$productIdInput);
                if ($product && $product->store === StoreEnum::LPO) {
                    if (empty($productUpdate['supplier_id'])) {
                        return new ApiErrorResponse(
                            data: ['errors' => ["Supplier ID is required for LPO products (Product ID: {$productIdInput})"]],
                            message: "Supplier ID is required for LPO products (Product ID: {$productIdInput})",
                            code: 422
                        );
                    }
                }
            }
            $productStatuses = $order->product_status ?? $order->initializeProductStatus();
            $supplierMapping = $order->supplier_id ?? [];
            $updatedProducts = [];
            $errors = [];

            // Track statuses by product type to handle multiple products of same type
            // Hardware and Warehouse: combined status
            // LPO: supplier-wise status (object with supplier IDs as keys)
            $hardwareStatuses = [];
            $warehouseStatuses = [];
            $lpoSupplierStatuses = []; // {supplier_id: status}

            // Process each product update
            foreach ($request->products as $productUpdate) {
                $productIdInput = $productUpdate['product_id'];
                $status = $productUpdate['status'];
                $supplierId = isset($productUpdate['supplier_id']) ? (int) $productUpdate['supplier_id'] : null;
                
                // RESTRICTION: If already approved/outfordelivery/in_transit/delivered, cannot change to pending or rejected
                // Get current status for validation
                $currentStatus = null;
                if (is_string($productIdInput) && in_array($productIdInput, ['hardware', 'warehouse'], true)) {
                    $currentStatus = $order->getProductStatus($productIdInput);
                } elseif (is_numeric($productIdInput)) {
                    $product = $order->products->firstWhere('id', (int)$productIdInput);
                    if ($product) {
                        if ($product->store === StoreEnum::LPO && $supplierId) {
                            $currentStatus = $order->getProductStatus($product->store, $supplierId);
                        } else {
                            $currentStatus = $order->getProductStatus($product->store);
                        }
                    }
                }
                
                // Validate status change restriction
                $restrictedStatuses = ['approved', 'outfordelivery', 'in_transit', 'delivered'];
                if ($currentStatus && in_array($currentStatus, $restrictedStatuses, true) && in_array($status, ['pending', 'rejected'], true)) {
                    $errors[] = "Cannot change status from {$currentStatus} to {$status}. You can only change to outfordelivery, in_transit, or delivered.";
                    continue;
                }

                // Check if product_id is a product type (hardware, warehouse) or actual product ID
                if (is_string($productIdInput) && in_array($productIdInput, ['hardware', 'warehouse'], true)) {
                    // Product type-based update (all products of that type)
                    $productType = $productIdInput;
                    
                    if ($productType === 'hardware') {
                        $hardwareStatuses[] = $status;
                    } elseif ($productType === 'warehouse') {
                        $warehouseStatuses[] = $status;
                    }
                    
                    $updatedProducts[] = [
                        'product_type' => $productType,
                        'status' => $status,
                        'supplier_id' => null,
                    ];
                    continue;
                }

                // Individual product update (requires valid product ID)
                if (!is_numeric($productIdInput)) {
                    $errors[] = "Invalid product_id: {$productIdInput}. Must be product ID (integer) or product type ('hardware', 'warehouse')";
                    continue;
                }

                $productId = (int) $productIdInput;

                // Verify product exists in order
                $product = $order->products->firstWhere('id', $productId);
                if (!$product) {
                    $errors[] = "Product ID {$productId} not found in order";
                    continue;
                }

                // Determine product type based on store
                $productStore = $product->store;
                $productType = null;

                if ($productStore === StoreEnum::HardwareStore) {
                    $productType = 'hardware';
                    $hardwareStatuses[] = $status;
                } elseif ($productStore === StoreEnum::WarehouseStore) {
                    $productType = 'warehouse';
                    $warehouseStatuses[] = $status;
                } elseif ($productStore === StoreEnum::LPO) {
                    $productType = 'lpo';
                    
                    // LPO requires supplier_id
                    if ($supplierId === null) {
                        $errors[] = "Product ID {$productId} (LPO) requires supplier_id";
                        continue;
                    }
                    
                    // Update supplier mapping for LPO products
                    $supplierMapping[(string)$productId] = $supplierId;
                    
                    // Track LPO status by supplier
                    $lpoSupplierStatuses[(string)$supplierId] = $status;
                } else {
                    // For custom products or unknown types, use warehouse
                    $productType = 'warehouse';
                    $warehouseStatuses[] = $status;
                }

                $updatedProducts[] = [
                    'product_id' => $productId,
                    'product_name' => $product->product_name,
                    'store_type' => $productStore?->value ?? StoreEnum::WarehouseStore->value,
                    'product_type' => $productType,
                    'status' => $status,
                    'supplier_id' => $supplierId,
                ];
            }

            // Determine final status for hardware (combined)
            if (!empty($hardwareStatuses)) {
                $uniqueStatuses = array_unique($hardwareStatuses);
                if (count($uniqueStatuses) === 1) {
                    $productStatuses['hardware'] = $uniqueStatuses[0];
                } else {
                    // Priority: rejected > pending > approved
                    if (in_array('rejected', $uniqueStatuses, true)) {
                        $productStatuses['hardware'] = 'rejected';
                    } elseif (in_array('pending', $uniqueStatuses, true)) {
                        $productStatuses['hardware'] = 'pending';
                    } else {
                        $productStatuses['hardware'] = 'approved';
                    }
                }
            }

            // Determine final status for warehouse (combined)
            if (!empty($warehouseStatuses)) {
                $uniqueStatuses = array_unique($warehouseStatuses);
                if (count($uniqueStatuses) === 1) {
                    $productStatuses['warehouse'] = $uniqueStatuses[0];
                } else {
                    // Priority: rejected > pending > approved
                    if (in_array('rejected', $uniqueStatuses, true)) {
                        $productStatuses['warehouse'] = 'rejected';
                    } elseif (in_array('pending', $uniqueStatuses, true)) {
                        $productStatuses['warehouse'] = 'pending';
                    } else {
                        $productStatuses['warehouse'] = 'approved';
                    }
                }
            }

            // LPO: supplier-wise status (keep individual supplier statuses)
            if (!empty($lpoSupplierStatuses)) {
                // Initialize LPO as object if not already
                if (!is_array($productStatuses['lpo'] ?? null)) {
                    $productStatuses['lpo'] = [];
                }
                // Merge new supplier statuses with existing ones
                $productStatuses['lpo'] = array_merge($productStatuses['lpo'], $lpoSupplierStatuses);
            }

            // If there were errors, return them
            if (!empty($errors)) {
                return new ApiErrorResponse(
                    data: ['errors' => $errors],
                    message: 'Some products could not be updated: ' . implode(', ', $errors),
                    code: 400
                );
            }

            // Get old product statuses before updating to check if status changed to 'approved'
            $oldProductStatuses = $order->product_status ?? $order->initializeProductStatus();
            
            // Track which product types changed to 'approved' for stock deduction
            $typesToDeductStock = [];
            
            // Check hardware status change
            if (isset($productStatuses['hardware']) && $productStatuses['hardware'] === 'approved') {
                $oldHardwareStatus = $oldProductStatuses['hardware'] ?? null;
                if ($oldHardwareStatus !== 'approved') {
                    $typesToDeductStock[] = 'hardware';
                }
            }
            
            // Check warehouse status change
            if (isset($productStatuses['warehouse']) && $productStatuses['warehouse'] === 'approved') {
                $oldWarehouseStatus = $oldProductStatuses['warehouse'] ?? null;
                if ($oldWarehouseStatus !== 'approved') {
                    $typesToDeductStock[] = 'warehouse';
                }
            }

            // Update order with new product_status and supplier_id
            $order->product_status = $productStatuses;
            if (!empty($supplierMapping)) {
                $order->supplier_id = $supplierMapping;
            }
            $order->save();

            // Deduct stock for product types that changed to 'approved'
            foreach ($typesToDeductStock as $type) {
                try {
                    $this->deductStockForProductType($order, $type);
                } catch (\Exception $e) {
                    Log::error("OrderController: Failed to deduct stock for product type {$type} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    // Continue with other types even if one fails
                }
            }

            // Sync order status from product statuses
            $order->syncOrderStatusFromProductStatuses();

            $order->refresh();
            $order->load(['site', 'products.category', 'products.productImages', 'customProducts.images']);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [
                    'order' => new OrderResource($order),
                    'updated_products' => $updatedProducts,
                    'product_status' => $productStatuses,
                ],
                message: 'Product statuses updated successfully for all products'
            );
        } catch (\Exception $e) {
            Log::error('OrderController::updateOrderProductsStatus - Error: ' . $e->getMessage());
            return new ApiErrorResponse(
                data: ['errors' => [$e->getMessage()]],
                message: 'Failed to update product statuses',
                code: 500
            );
        }
    }

    /**
     * Deduct stock for products of a specific type when product status changes to 'approved'
     * 
     * @param Order $order The order
     * @param string $type Product type ('hardware' or 'warehouse')
     * @return void
     */
    private function deductStockForProductType(Order $order, string $type): void
    {
        if (!$this->stockService) {
            $this->stockService = app(StockService::class);
        }

        // Load order products with materials relationship
        $order->load(['products.materials', 'customProducts']);
        
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
        } elseif ($type === 'warehouse') {
            // Get warehouse store products
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
                    
                    // Custom products use quantity 1 by default
                    $customQty = 1;
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
                // Check if stock was already deducted for this order/product combination with this type
                // Check both by name pattern and notes pattern to catch all cases
                $orderId = $order->id;
                $alreadyDeducted = \App\Models\Stock::where('product_id', $productId)
                    ->where('reference_id', $orderId)
                    ->where('reference_type', Order::class)
                    ->where('adjustment_type', 'out')
                    ->where('status', true)
                    ->where(function($query) use ($type, $orderId) {
                        $query->where('name', 'like', "%({$type})%")
                              ->orWhere('notes', 'like', "%Product Status Approved ({$type})%")
                              ->orWhere('notes', 'like', "%Stock deducted for Order #{$orderId} - Product Status Approved ({$type})%");
                    })
                    ->exists();
                
                if ($alreadyDeducted) {
                    Log::info("OrderController: Stock already deducted for product {$productId} (type: {$type}) in order {$order->id}, skipping.");
                    continue;
                }
                
                try {
                    // Check stock availability before deducting
                    $generalStock = $this->stockService->getCurrentStock((int)$productId, null);
                    $siteStock = $order->site_id ? $this->stockService->getCurrentStock((int)$productId, $order->site_id) : 0;
                    $totalAvailableStock = $generalStock + $siteStock;
                    
                    if ($totalAvailableStock < $quantity) {
                        $productName = $product->product_name ?? "Product ID {$productId}";
                        Log::warning("OrderController: Insufficient stock for product {$productId} in order {$order->id}. Available: {$totalAvailableStock}, Requested: {$quantity}");
                        throw new \Exception("Insufficient stock for {$productName}. Available: {$totalAvailableStock}, Requested: {$quantity}");
                    }
                    
                    // 1) Deduct finished product stock
                    $this->stockService->adjustStock(
                        (int)$productId,
                        $quantity,
                        'out',
                        $order->site_id,
                        "Stock deducted for Order #{$order->id} - Product Status Approved ({$type}) (quantity: " . number_format($quantity, 2) . ")",
                        $order,
                        "Order #{$order->id} - Stock Deducted ({$type})"
                    );

                    // 2) Deduct material stock based on product BOM
                    foreach ($product->materials as $material) {
                        $materialQtyPerUnit = (float)($material->pivot->quantity ?? 0);

                        if ($materialQtyPerUnit <= 0) {
                            continue;
                        }

                        $materialTotalQty = $materialQtyPerUnit * $quantity;

                        $this->stockService->adjustMaterialStock(
                            (int)$material->id,
                            (int)$materialTotalQty,
                            'out',
                            $order->site_id,
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
                    Log::error("OrderController: Failed to deduct stock for product {$productId} in order {$order->id}: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
                    throw $e; // Re-throw to be caught by caller
                }
            }
        }
    }
}
