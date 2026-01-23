<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Profile;

use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\Delivery;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        // Low stock products collection used in both stats and widget
       $stats = [];
       $recentOrders = [];
       $lowStockProducts = [];


        return view('admin::Profile.views.dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'lowStockProducts' => $lowStockProducts,
        ])
            ->layout('panel::layout.app', [
                'title' => __('admin.dashboard'),
            ]);
    }
}
