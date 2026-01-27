<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Profile;

use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\Stock;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Illuminate\Support\Facades\Schema;

class Dashboard extends Component
{
    public function render(): View
    {
        // Pending orders (overall, admin view)
        $pendingOrdersCount = Order::where('status', OrderStatusEnum::Pending->value)->count();

        // Active sites (status = true)
        $activeSitesCount = Site::where('status', true)->count();

        // Low stock products (shared logic with stock report)
        $latestStockQty = Stock::query()
            ->select('quantity')
            ->whereColumn('product_id', 'products.id')
            ->whereNull('site_id')
            ->where('status', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(1);

        $baseQuery = Product::query()
            ->with('category')
            // LPO products are not stock-managed
            ->where('store', '!=', StoreEnum::LPO)
            ->select('products.*')
            ->selectSub($latestStockQty, 'current_qty')
            ->orderByDesc('created_at');

        // Threshold must be present and current qty <= threshold
        $lowStockBaseQuery = Product::query()
            ->fromSub($baseQuery, 'products_with_qty')
            ->select('products_with_qty.*')
            ->whereNotNull('low_stock_threshold')
            ->where('low_stock_threshold', '>', 0)
            ->whereRaw('COALESCE(current_qty, available_qty, 0) <= low_stock_threshold')
            ->with('category');

        $lowStockProducts = (clone $lowStockBaseQuery)
            ->orderBy('current_qty')
            ->limit(5)
            ->get();

        $lowStockCount = (clone $lowStockBaseQuery)->count();

        // Active dispatches
        // Prefer delivery_status column when it exists; otherwise fall back to status-based enum values.
        if (Schema::hasColumn('orders', 'delivery_status')) {
            $activeDispatchesCount = Order::whereIn('delivery_status', ['approved', 'in_transit', 'outfordelivery'])->count();
        } else {
            $activeDispatchesCount = Order::whereIn('status', [
                OrderStatusEnum::Approved->value,
                OrderStatusEnum::InTransit->value,
                OrderStatusEnum::OutOfDelivery->value,
            ])->count();
        }

        // Recent orders for widget
        $recentOrders = Order::with('site')
            ->latest()
            ->limit(5)
            ->get();

        $stats = [
            'pending_orders' => $pendingOrdersCount,
            'sites' => $activeSitesCount,
            'low_stock_items' => $lowStockCount,
            'active_dispatches' => $activeDispatchesCount,
        ];

        return view('admin::Profile.views.dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'lowStockProducts' => $lowStockProducts,
        ])->layout('panel::layout.app', [
            'title' => __('admin.dashboard'),
        ]);
    }
}
