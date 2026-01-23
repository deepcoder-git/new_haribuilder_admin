<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\TransportManagement;

use App\Http\Controllers\Controller;
use App\Models\Moderator;
use App\Models\Order;
use App\Src\Api\Modules\SiteManagement\Resources\ModeratorResource;
use App\Src\Api\Modules\TransportManagement\Resources\TransportOrderResource;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StatusEnum;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use App\Utility\Resource\PaginationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Database\Eloquent\Builder;
use App\Src\Api\Modules\StoreManagement\Resources\WorkshopStoreManagerOrderResource;

class TransportManagerController extends Controller
{
    /**
     * Get all transport managers
     */
    public function index(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 20);
            $perPage = $perPage > 0 ? min($perPage, 100) : 20;

            $query = Moderator::where('role', RoleEnum::TransportManager->value);

            // Search by name or email
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('mobile_number', 'like', '%' . $search . '%');
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            $transportManagers = $query->orderBy('name', 'asc')
                                     ->paginate($perPage)
                                     ->appends($request->query());

            return new ApiResponse(
                isError: false,
                code: 200,
                data: ModeratorResource::collection($transportManagers),
                message: ($transportManagers->isEmpty()) ? 'No transport managers found.' : 'Transport managers retrieved successfully.',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get transport managers failed',
                500
            );
        }
    }

    /**
     * Get a single transport manager by ID
     */
    public function show(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $transportManager = Moderator::where('role', RoleEnum::TransportManager->value)
                                        ->findOrFail($id);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: new ModeratorResource($transportManager),
                message: 'Transport manager retrieved successfully.',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get transport manager failed',
                404
            );
        }
    }

    /**
     * Create a new transport manager
     */
    public function store(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string'],
                'email' => ['required', 'email', 'unique:moderators,email'],
                'mobile_number' => ['nullable', 'string', 'max:20'],
                'password' => ['required', 'max:100', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
                'status' => ['nullable', 'string', 'in:active,inactive'],
            ]);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'transport manager creation failed',
                    422
                );
            }

            $transportManager = Moderator::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile_number' => $request->mobile_number,
                'password' => $request->password,
                'role' => RoleEnum::TransportManager->value,
                'status' => $request->status ?? StatusEnum::Active->value,
                'type' => 'moderator',
            ]);

            return new ApiResponse(
                isError: false,
                code: 201,
                data: new ModeratorResource($transportManager),
                message: 'Transport manager created successfully.',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'transport manager creation failed',
                500
            );
        }
    }

    /**
     * Update an existing transport manager
     */
    public function update(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $transportManager = Moderator::where('role', RoleEnum::TransportManager->value)
                                        ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'required', 'string'],
                'email' => ['sometimes', 'required', 'email', 'unique:moderators,email,' . $id],
                'mobile_number' => ['nullable', 'string', 'max:20'],
                'password' => ['sometimes', 'required', 'max:100', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
                'status' => ['nullable', 'string', 'in:active,inactive'],
            ]);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'transport manager update failed',
                    422
                );
            }

            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            
            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }
            
            if ($request->has('mobile_number')) {
                $updateData['mobile_number'] = $request->mobile_number;
            }
            
            if ($request->has('password')) {
                $updateData['password'] = $request->password;
            }
            
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            $transportManager->update($updateData);
            $transportManager->refresh();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: new ModeratorResource($transportManager),
                message: 'Transport manager updated successfully.',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'transport manager update failed',
                500
            );
        }
    }

    /**
     * Delete a transport manager
     */
    public function destroy(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $transportManager = Moderator::where('role', RoleEnum::TransportManager->value)
                                        ->findOrFail($id);

            $transportManager->delete();

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [],
                message: 'Transport manager deleted successfully.',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'transport manager deletion failed',
                500
            );
        }
    }

    /**
     * Get orders assigned to the transport manager
     */
    public function getOrders(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $request->validate([
                'per_page' => ['required', 'integer', 'max_digits:3', 'min_digits:1'],
                'page' => ['required', 'integer'],
                'delivery_status' => ['nullable', 'string', Rule::in(['approved', 'in_transit', 'delivered', 'outfordelivery', 'all'])],
            ]);

            $user = $request->user();
            $deliveryStatus = $request->delivery_status;
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
                'delivery_status' => $deliveryStatus,
            ]);

            $baseQuery = Order::where('store_manager_role', $userRole)->orWhere('is_lpo', 1);
            
            $totalWithRole = $baseQuery->count();
            Log::info('StoreOrderController::getOrders - Orders with role', [
                'total_orders_with_role' => $totalWithRole,
                'user_role' => $userRole,
            ]);

            $query = Order::with(['site','products.category', 'products.productImages'])
                ->whereIn('delivery_status',['approved','in_transit','delivered','outfordelivery'])
                ->where('store_manager_role', RoleEnum::WorkshopStoreManager->value)
                ->orWhere('is_lpo', 1)
                ->when($deliveryStatus && $deliveryStatus !== 'all', function (Builder $query) use ($deliveryStatus) {
                    $query->where('delivery_status', $deliveryStatus);
                })
                ->orderByDesc('id');

            $totalBeforePagination = $query->count();
            Log::info('StoreOrderController::getOrders - Query count', [
                'total_matching_orders' => $totalBeforePagination,
                'total_with_role_only' => $totalWithRole,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);

            $getOrders = $query->paginate($request->per_page)
                ->appends($request->query());
              
            $resourceClass = WorkshopStoreManagerOrderResource::class;

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

            $order = Order::with([
                    'site',
                    'products.category',
                    'products.productImages',
                    'customProducts'
                ])
                ->where('id', $orderId)
                ->where('store_manager_role', RoleEnum::WorkshopStoreManager->value)
                ->orWhere('is_lpo', 1)
                ->first();

            if (!$order) {
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: [],
                    message: __('api.store-manager.empty_records'),
                );
            }

            $resourceClass = WorkshopStoreManagerOrderResource::class;

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

    /**
     * Update delivery status (approve/update order status)
     */
    public function updateDeliveryStatus(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $request->validate([
                'order_id' => ['required', 'integer', 'exists:orders,id'],
                'delivery_status' => ['required', 'string', Rule::in(['in_transit', 'delivered', 'outfordelivery'])],
                'driver_name' => ['nullable', 'string', 'max:255'],
                'vehicle_number' => ['nullable', 'string', 'max:255'],
            ], [
                'order_id.required' => 'Order ID is required',
                'order_id.exists' => 'Order not found',
                'delivery_status.required' => 'Delivery status is required',
                'delivery_status.in' => 'Invalid delivery status. Allowed values: in_transit, delivered, outfordelivery',
            ]);

            $user = $request->user();

            // if (!$user || !$user->getRole() || !$user->hasRole(RoleEnum::TransportManager)) {
            //     return new ApiErrorResponse([], 'Unauthorized access', 403);
            // }

            DB::beginTransaction();

            $order = Order::findOrFail($request->order_id);

            // Verify the order is assigned to this transport manager
            // if ($order->transport_manager_id !== $user->id) {
            //     DB::rollBack();
            //     return new ApiErrorResponse([], 'Unauthorized access to this order', 403);
            // }

            $deliveryStatus = $request->delivery_status;
            $updateData = [];

            // Handle in_transit status
            if ($deliveryStatus === 'in_transit') {
                // Only allow if current status is approved
                // if ($order->delivery_status !== 'approved') {
                //     DB::rollBack();
                //     return new ApiErrorResponse([], 'Order must be approved before it can be set to in transit', 422);
                // }
                
                $updateData['delivery_status'] = 'in_transit';
                $updateData['status'] = OrderStatusEnum::InTransit->value;
            }
            if($deliveryStatus === 'outfordelivery') {
                $updateData['delivery_status'] = 'outfordelivery';
                $updateData['status'] = OrderStatusEnum::OutOfDelivery->value;
            }
            // Handle delivered status
            elseif ($deliveryStatus === 'delivered') {
                // Only allow if current status is in_transit
                // if ($order->delivery_status !== 'in_transit') {
                //     DB::rollBack();
                //     return new ApiErrorResponse([], 'Order must be in transit before it can be marked as delivered', 422);
                // }

                $updateData['delivery_status'] = 'delivered';
                // All delivered orders now use unified 'delivery' status (no separate 'completed' status)
                $updateData['status'] = OrderStatusEnum::Delivery->value;
                $updateData['is_completed'] = true;
                $updateData['completed_at'] = now();
                $updateData['completed_by'] = $user->id;

                // Check if order should be marked as completed (for mixed orders)
                Order::syncMixedOrderCompletion($order);

            }

            // Update driver name if provided
            if ($request->has('driver_name')) {
                $updateData['driver_name'] = $request->driver_name;
            }

            // Update vehicle number if provided
            if ($request->has('vehicle_number')) {
                $updateData['vehicle_number'] = $request->vehicle_number;
            }

            $order->update($updateData);
            $order->refresh();
            $order->load(['site', 'products.category', 'products.productImages', 'customProducts', 'transportManager']);

            DB::commit();

            $statusMessages = [
                'in_transit' => 'Order status updated to in transit successfully',
                'delivered' => 'Order marked as delivered successfully',
            ];

            return new ApiResponse(
                data: new WorkshopStoreManagerOrderResource($order),
                message: $statusMessages[$deliveryStatus] ?? 'Order status updated successfully',
                code: 200,
                isError: false
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return new ApiErrorResponse(
                ['errors' => $e->errors()],
                'delivery status update failed',
                422
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return new ApiErrorResponse(
                ['errors' => ['Order not found']],
                'delivery status update failed',
                404
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update delivery status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'delivery status update failed',
                500
            );
        }
    }
}

