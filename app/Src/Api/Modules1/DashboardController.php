<?php

declare(strict_types=1);

namespace App\Src\Api\Modules;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Src\Api\Modules\SiteManagement\Resources\OrderResource;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Site;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\OrderStatusEnum;
use App\Models\Moderator;
use App\Models\Product;


class DashboardController extends Controller
{

    public function __construct(
        protected AuthService $authService
    ) {}

    public function dashboard(Request $request, string $role, int $site_id = null): ApiResponse|ApiErrorResponse
    {   
        try{
            // Get site_id from route parameter or query parameter
            $site_id = $site_id ?? $request->input('site_id');
            $site_id = $site_id ? (int) $site_id : null;
            
            if($role == RoleEnum::SiteSupervisor->value){
                $pending_orders = Order::countPendingOrdersForSiteManager($request->user()->id, $site_id);
                $approved_orders = Order::countApprovedOrdersForSiteManager($request->user()->id, $site_id);
                $delivered_orders = Order::countDeliveredOrdersForSiteManager($request->user()->id, $site_id);
                $rejected_orders = Order::countRejectedOrdersForSiteManager($request->user()->id, $site_id);
                $total_sites = Site::countAssignedSitesForSiteManager($request->user()->id);
                $delayed_orders = Order::countDelayedOrdersForSiteManager($request->user()->id, $site_id);

                $dashboard_data = [
                    'pending_orders' => $pending_orders,
                    'delayed_orders' => $delayed_orders,
                    'approved_orders' => $approved_orders,
                    'delivered_orders' => $delivered_orders,
                    'rejected_orders' => $rejected_orders,
                    'total_sites' => $total_sites,
                ];
                
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: $dashboard_data,
                    message: __('api.site-manager.records_found'),
                );
            }
            if($role == RoleEnum::StoreManager->value){
                $user = $request->user();

                // Hardware Store Manager: orders with hardware products (non-LPO)
                $pending_orders = Order::where('is_lpo', 0)
                    ->where('status', OrderStatusEnum::Pending->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::HardwareStore->value);
                    })
                    ->count();

                $urgent_orders = Order::where('is_lpo', 0)
                    ->where('priority', PriorityEnum::High->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::HardwareStore->value);
                    })
                    ->count();

                // Treat returned orders as rejected in new status model
                $return_orders = Order::where('is_lpo', 0)
                    ->where('status', OrderStatusEnum::Rejected->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::HardwareStore->value);
                    })
                    ->count();

                 // Count products for this StoreManager
                 $inventory_products = Product::where('store_manager_id', $user->id)
                    ->count();

                $total_store = Moderator::whereIn('role', [RoleEnum::StoreManager->value])->count();

                $dashboard_data = [
                    'pending_orders'   => $pending_orders,
                    'returned_orders'  => $return_orders,
                    'urgent_orders'    => $urgent_orders,
                    'total_store'      => $total_store,
                    'inventory_products' => $inventory_products,
                    'store_manager_role' => $user->role?->value ?? null
                ];
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: $dashboard_data,
                    message: __('api.store-manager.dashboard_data_fetched'),
                );
            }
            if($role == RoleEnum::WorkshopStoreManager->value){
                $user = $request->user();

                // Workshop Store Manager: warehouse/custom orders (non-LPO)
                $pending_orders = Order::where('is_lpo', 0)
                    ->where('status', OrderStatusEnum::Pending->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::WarehouseStore->value);
                    })
                    ->count();

                // Delayed: expected_delivery_date in the past while still active
                $delayed_orders = Order::where('is_lpo', 0)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::WarehouseStore->value);
                    })
                    ->whereDate('expected_delivery_date', '<', now()->toDateString())
                    ->whereIn('status', [
                        OrderStatusEnum::Approved->value,
                        OrderStatusEnum::InTransit->value,
                        OrderStatusEnum::OutOfDelivery->value,
                    ])
                    ->count();
                    
                $approved_orders = Order::where('is_lpo', 0)
                    ->where('status', OrderStatusEnum::Approved->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::WarehouseStore->value);
                    })
                    ->count();

                // Delivered orders use 'delivered' enum value in main status column
                $delivered_orders = Order::where('is_lpo', 0)
                    ->where('status', OrderStatusEnum::Delivery->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::WarehouseStore->value);
                    })
                    ->count();
                    
                $rejected_orders = Order::where('is_lpo', 0)
                    ->where('status', OrderStatusEnum::Rejected->value)
                    ->whereHas('products', function ($q) {
                        $q->where('store', \App\Utility\Enums\StoreEnum::WarehouseStore->value);
                    })
                    ->count();

                $dashboard_data = [
                    'pending_orders' => $pending_orders,
                    'delayed_orders' => $delayed_orders,
                    'approved_orders' => $approved_orders,
                    'delivered_orders' => $delivered_orders,
                    'total_wastage' => 0,
                ];
            }
            if($role == RoleEnum::TransportManager->value){
                $transportManagerId = $request->user()->id;
                
                $pending_orders = Order::where('transport_manager_id', $transportManagerId)
                    ->where('status', OrderStatusEnum::Pending->value)
                    ->count();
                
                $in_transit_orders = Order::where('transport_manager_id', $transportManagerId)
                    ->where('status', OrderStatusEnum::InTransit->value)
                    ->count();
                
                $delayed_orders = Order::where('transport_manager_id', $transportManagerId)
                    ->where('expected_delivery_date', '<', now())
                    ->whereIn('status', [
                        OrderStatusEnum::Approved->value,
                        OrderStatusEnum::InTransit->value,
                        OrderStatusEnum::OutOfDelivery->value,
                    ])
                    ->count();
                
                $completed_orders = Order::where('transport_manager_id', $transportManagerId)
                    ->where('status', OrderStatusEnum::Delivery->value)
                    ->count();

                $dashboard_data = [
                    'pending_orders' => $pending_orders,
                    'in_transit_orders' => $in_transit_orders,
                    'delayed_orders' => $delayed_orders,
                    'completed_orders' => $completed_orders,
                ];
                
                return new ApiResponse(
                    isError: false,
                    code: 200,
                    data: $dashboard_data,
                    message: __('api.transport-manager.dashboard_data_fetched') ?? 'Transport manager dashboard data retrieved successfully',
                );
            }
            return new ApiErrorResponse(
                ['errors' => ['Invalid role specified']],
                'dashboard failed',
                400
            );
        } catch (\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'dashboard failed',
                500
            );
        }
    }    
}
