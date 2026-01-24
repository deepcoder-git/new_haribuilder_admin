<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\ProductPurchase;

use App\Models\ProductPurchase;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProductPurchaseView extends Component
{
    public ProductPurchase $purchase;

    public function mount(int $id): void
    {
        $this->purchase = ProductPurchase::query()
            ->with([
                'supplier',
                'creator',
                'items',
                'items.product',
                'items.product.category',
                'items.product.productImages',
            ])
            ->findOrFail($id);
    }

    public function edit(): void
    {
        $this->redirect(route('admin.product-purchases.edit', $this->purchase->id));
    }

    public function back(): void
    {
        $this->redirect(route('admin.product-purchases.index'));
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::ProductPurchase.views.product-purchase-view', [
            'purchase' => $this->purchase,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'View Product Purchase',
            'breadcrumb' => [
                ['Product Purchases', route('admin.product-purchases.index')],
                ['View', '#'],
            ],
        ]);
    }
}


