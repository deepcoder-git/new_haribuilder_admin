<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Return;

use App\Models\Moderator;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Site;
use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ReturnForm extends Component
{
    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public ?string $type = null;
    public ?int $site_id = null;
    public ?int $manager_id = null;
    public int|string|null $order_id = null;
    public ?string $date = null;
    public ?string $status = null;
    public ?string $reason = null;

    /** @var array<int, array{product_id:int|null, ordered_quantity:int, return_quantity:int, unit_type:string|null, adjust_stock:bool}> */
    public array $returnItems = [];

    public function mount(?int $id = null): void
    {
        $this->date = now()->format('Y-m-d');
        $this->status = 'pending';

        if ($id) {
            /** @var OrderReturn $return */
            $return = OrderReturn::with(['items.product'])->findOrFail($id);
            $this->isEditMode = true;
            $this->editingId = $id;

            $this->type = $return->type;
            $this->site_id = $return->site_id;
            $this->manager_id = $return->manager_id;
            $this->order_id = $return->order_id;
            $this->date = optional($return->date)->format('Y-m-d');
            $this->status = $return->status;
            $this->reason = $return->reason;

            $this->returnItems = $return->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'ordered_quantity' => (int) ($item->ordered_quantity ?? 0),
                    'return_quantity' => (int) ($item->return_quantity ?? 1),
                    'unit_type' => $item->unit_type,
                    'adjust_stock' => (bool) ($item->adjust_stock ?? false),
                ];
            })->values()->all();
        }

        if (empty($this->returnItems)) {
            $this->resetItemRows();
        }
    }

    protected function blankItemRow(): array
    {
        return [
            'product_id' => null,
            'ordered_quantity' => 0,
            'return_quantity' => 1,
            'unit_type' => null,
            'adjust_stock' => false,
        ];
    }

    protected function resetItemRows(int $rows = 3): void
    {
        $this->returnItems = [];
        for ($i = 0; $i < $rows; $i++) {
            $this->returnItems[] = $this->blankItemRow();
        }
    }

    protected function ensureMinItemRows(int $rows = 3): void
    {
        $current = count($this->returnItems);
        for ($i = $current; $i < $rows; $i++) {
            $this->returnItems[] = $this->blankItemRow();
        }
    }

    public function getSitesProperty()
    {
        return Site::orderBy('name')->get();
    }

    public function getManagersProperty()
    {
        $query = Moderator::query()
            ->where('status', 'active');

        if ($this->site_id) {
            $site = Site::find($this->site_id);
            if ($site && $site->site_manager_id) {
                $query->where('id', $site->site_manager_id);
            }
        }

        return $query->orderBy('name')->get();
    }

    public function getProductsProperty()
    {
        return Product::with('category')->orderBy('product_name')->get();
    }

    public function getOrdersProperty()
    {
        $query = Order::query()->orderByDesc('id');

        if ($this->site_id) {
            $query->where('site_id', $this->site_id);
        }

        return $query->limit(200)->get();
    }

    public function addItemRow(): void
    {
        $this->returnItems[] = $this->blankItemRow();
    }

    public function removeItemRow(int $index): void
    {
        if (count($this->returnItems) <= 1) {
            return;
        }

        unset($this->returnItems[$index]);
        $this->returnItems = array_values($this->returnItems);
    }

    public function updatedSiteId($value): void
    {
        if (!$value) {
            $this->manager_id = null;
            $this->order_id = null;
            $this->resetItemRows();
            return;
        }

        $site = Site::find($value);
        if ($site && $site->site_manager_id) {
            $this->manager_id = $site->site_manager_id;
        }

        $this->order_id = null;
        $this->resetItemRows();
    }

    public function updatedOrderId($value): void
    {
        if ($value === 'other') {
            $this->returnItems = [];
            $this->resetItemRows();
            return;
        }

        if (!$value) {
            $this->resetItemRows();
            return;
        }

        /** @var Order|null $order */
        $order = Order::query()
            ->with(['products' => function ($q) {
                $q->with('category');
            }])
            ->find($value);

        if (!$order) {
            $this->resetItemRows();
            return;
        }

        $this->returnItems = $order->products->map(function (Product $product) {
            return [
                'product_id' => $product->id,
                'ordered_quantity' => (int) ($product->pivot->quantity ?? 0),
                'return_quantity' => 1,
                'unit_type' => $product->unit_type,
                // Default: checked so returned products affect stock
                'adjust_stock' => true,
            ];
        })->values()->all();

        // If order has no products, ensure at least 1 row for manual entry
        if (empty($this->returnItems)) {
            $this->resetItemRows(1);
        }
    }

    public function updatedProductId($value, int $index): void
    {
        if (!$value || !isset($this->returnItems[$index])) {
            return;
        }

        $product = Product::find($value);
        if ($product) {
            $this->returnItems[$index]['unit_type'] = $product->unit_type;
        }
    }

    protected function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['required', 'exists:moderators,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(['pending', 'approved', 'rejected', 'completed'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'returnItems' => ['required', 'array', 'min:1'],
            'returnItems.*.product_id' => ['required', 'exists:products,id'],
            'returnItems.*.ordered_quantity' => ['nullable', 'integer', 'min:0'],
            'returnItems.*.return_quantity' => ['required', 'integer', 'min:1'],
            'returnItems.*.unit_type' => ['nullable', 'string', 'max:255'],
            'returnItems.*.adjust_stock' => ['sometimes', 'boolean'],
        ];
    }

    protected function payload(): array
    {
        $items = array_values(array_filter($this->returnItems, fn ($row) => !empty($row['product_id'])));

        return [
            'type' => $this->type,
            'manager_id' => $this->manager_id,
            'site_id' => $this->site_id ?: null,
            'order_id' => $this->order_id && $this->order_id !== 'other' ? $this->order_id : null,
            'date' => $this->date,
            'status' => $this->status ?? 'pending',
            'reason' => $this->reason,
            'items' => $items,
        ];
    }

    public function save(): void
    {
        if ($this->order_id === 'other') {
            $this->order_id = null;
        }

        $this->validate($this->rules());

        try {
            $data = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                /** @var OrderReturn $return */
                $return = OrderReturn::with('items')->findOrFail((int) $this->editingId);
                $return->update([
                    'type' => $data['type'],
                    'manager_id' => $data['manager_id'],
                    'site_id' => $data['site_id'],
                    'order_id' => $data['order_id'],
                    'date' => $data['date'],
                    'status' => $data['status'],
                    'reason' => $data['reason'],
                ]);

                $return->items()->delete();
                foreach ($data['items'] as $item) {
                    $return->items()->create([
                        'order_id' => $data['order_id'],
                        'product_id' => $item['product_id'],
                        'ordered_quantity' => $item['ordered_quantity'] ?? 0,
                        'return_quantity' => $item['return_quantity'],
                        'unit_type' => $item['unit_type'],
                        'adjust_stock' => !empty($item['adjust_stock']),
                    ]);
                }

                // Rebuild stock entries for this return
                $this->syncReturnStock($return);

                $message = 'Return updated successfully!';
            } else {
                /** @var OrderReturn $return */
                $return = OrderReturn::create([
                    'type' => $data['type'],
                    'manager_id' => $data['manager_id'],
                    'site_id' => $data['site_id'],
                    'order_id' => $data['order_id'],
                    'date' => $data['date'],
                    'status' => $data['status'],
                    'reason' => $data['reason'],
                ]);

                foreach ($data['items'] as $item) {
                    $return->items()->create([
                        'order_id' => $data['order_id'],
                        'product_id' => $item['product_id'],
                        'ordered_quantity' => $item['ordered_quantity'] ?? 0,
                        'return_quantity' => $item['return_quantity'],
                        'unit_type' => $item['unit_type'],
                        'adjust_stock' => !empty($item['adjust_stock']),
                    ]);
                }

                // Create stock entries for this new return
                $this->syncReturnStock($return);

                $message = 'Return created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.returns.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.returns.index'));
    }

    public function render(): View
    {
        return view('admin.Return.views.return-form', [
            'isEditMode' => $this->isEditMode,
            'sites' => $this->sites,
            'managers' => $this->managers,
            'products' => $this->products,
            'orders' => $this->orders,
            'returnItems' => $this->returnItems,
        ])->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Return' : 'Add Return',
            'breadcrumb' => [
                ['Returns', route('admin.returns.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }

    /**
     * Create / refresh stock "in" entries for a given return so that StockReport reflects it.
     */
    protected function syncReturnStock(OrderReturn $return): void
    {
        /** @var StockService $stockService */
        $stockService = app(StockService::class);

        // Remove previous stock adjustments for this return to keep it idempotent
        Stock::where('reference_type', OrderReturn::class)
            ->where('reference_id', $return->id)
            ->delete();

        $return->loadMissing('items.product');

        $siteId = $return->site_id ? (int) $return->site_id : null;

        foreach ($return->items as $item) {
            if (empty($item->adjust_stock) || !$item->product || $item->return_quantity <= 0) {
                continue;
            }

            $qty = (int) $item->return_quantity;
            $product = $item->product;

            // Decide whether this is product or material based on is_product flag
            $isProduct = ($product->is_product ?? 1) > 0;

            $notes = "Stock increased from Return #{$return->id} (qty: " . number_format($qty, 2) . ")";
            $name = "Return #{$return->id} - Stock In";

            if ($isProduct) {
                $stockService->adjustProductStock((int) $product->id, $qty, 'in', $siteId, $notes, $return, $name);
            } else {
                $stockService->adjustMaterialStock((int) $product->id, $qty, 'in', $siteId, $notes, $return, $name);
            }
        }
    }
}

