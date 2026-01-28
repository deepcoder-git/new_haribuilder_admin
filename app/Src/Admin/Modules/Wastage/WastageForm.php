<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Wastage;

use App\Models\Moderator;
use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\Wastage;
use App\Services\WastageService;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\WastageTypeEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class WastageForm extends Component
{
    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    public ?string $type = null;
    public ?int $site_id = null;
    public ?int $manager_id = null;
    public ?int $order_id = null;
    public ?string $date = null;
    public ?string $reason = null;

    /** @var array<int, array{product_id:int|null, quantity:int, wastage_qty:int, unit_type:string|null}> */
    public array $wastageProducts = [];

    public function mount(?int $id = null): void
    {
        $this->type = WastageTypeEnum::SiteWastage->value;
        $this->date = now()->format('Y-m-d');

        if ($id) {
            /** @var Wastage $wastage */
            $wastage = Wastage::with(['products'])->findOrFail($id);
            $this->isEditMode = true;
            $this->editingId = $id;

            $this->type = $wastage->type?->value ?? (string) $wastage->type;
            $this->site_id = $wastage->site_id;
            $this->manager_id = $wastage->manager_id;
            $this->order_id = $wastage->order_id;
            $this->date = optional($wastage->date)->format('Y-m-d');
            $this->reason = $wastage->reason;

            $this->wastageProducts = $wastage->products->map(function (Product $product) {
                return [
                    'product_id' => $product->id,
                    'quantity' => (int) ($product->pivot->quantity ?? $product->pivot->wastage_qty ?? 1),
                    'wastage_qty' => (int) ($product->pivot->wastage_qty ?? 1),
                    'unit_type' => $product->pivot->unit_type ?? $product->unit_type,
                ];
            })->values()->all();
        }

        if (empty($this->wastageProducts)) {
            $this->resetProductRows();
        }
    }

    protected function blankProductRow(): array
    {
        return [
            'product_id' => null,
            'quantity' => 1,
            'wastage_qty' => 1,
            'unit_type' => null,
        ];
    }

    /**
     * Reset products to a fixed number of blank rows (default 3).
     */
    protected function resetProductRows(int $rows = 3): void
    {
        $this->wastageProducts = [];
        for ($i = 0; $i < $rows; $i++) {
            $this->wastageProducts[] = $this->blankProductRow();
        }
    }

    /**
     * Ensure we always have at least $rows product rows (default 3).
     */
    protected function ensureMinProductRows(int $rows = 3): void
    {
        $current = count($this->wastageProducts);
        for ($i = $current; $i < $rows; $i++) {
            $this->wastageProducts[] = $this->blankProductRow();
        }
    }

    public function getWastageTypesProperty()
    {
        return WastageTypeEnum::cases();
    }

    public function getSitesProperty()
    {
        return Site::orderBy('name')->get();
    }

    public function getManagersProperty()
    {
        $query = Moderator::query()
            ->where('role', RoleEnum::SiteSupervisor->value)
            ->where('status', 'active');

        // If a site is selected, prefer its assigned site_manager_id
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

        // When a site is selected, only show orders from that site
        if ($this->site_id) {
            $query->where('site_id', $this->site_id);
        }

        return $query->limit(200)->get();
    }

    public function addProductRow(): void
    {
        $this->wastageProducts[] = $this->blankProductRow();
    }

    public function removeProductRow(int $index): void
    {
        if (count($this->wastageProducts) <= 1) {
            return;
        }

        unset($this->wastageProducts[$index]);
        $this->wastageProducts = array_values($this->wastageProducts);
    }

    public function updatedSiteId($value): void
    {
        if (!$value) {
            $this->manager_id = null;
            $this->order_id = null;
            $this->resetProductRows();
            return;
        }

        $site = Site::find($value);
        if ($site && $site->site_manager_id) {
            $this->manager_id = $site->site_manager_id;
        }

        // Reset order & products when site changes to avoid mismatched data
        $this->order_id = null;
        $this->resetProductRows();
    }

    public function updatedOrderId($value): void
    {
        if (!$value) {
            $this->resetProductRows();
            return;
        }

        /** @var Order|null $order */
        $order = Order::query()
            ->with(['products' => function ($q) {
                $q->with('category');
            }])
            ->find($value);

        if (!$order) {
            $this->resetProductRows();
            return;
        }

        $this->wastageProducts = $order->products->map(function (Product $product) {
            return [
                'product_id' => $product->id,
                'quantity' => (int) ($product->pivot->quantity ?? 1),
                'wastage_qty' => 1,
                'unit_type' => $product->unit_type,
            ];
        })->values()->all();

        // If order has less than 3 products, pad with empty rows to always show 3
        $this->ensureMinProductRows();
    }

    public function updatedProductId($value, int $index): void
    {
        if (!$value || !isset($this->wastageProducts[$index])) {
            return;
        }

        $product = Product::find($value);
        if ($product) {
            $this->wastageProducts[$index]['unit_type'] = $product->unit_type;
        }
    }

    protected function rules(): array
    {
        $typeValues = array_map(static fn (WastageTypeEnum $e) => $e->value, WastageTypeEnum::cases());

        return [
            'type' => ['required', 'string', Rule::in($typeValues)],
            'manager_id' => ['required', 'exists:moderators,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'wastageProducts' => ['required', 'array', 'min:1'],
            'wastageProducts.*.product_id' => ['required', 'exists:products,id'],
            'wastageProducts.*.quantity' => ['required', 'integer', 'min:1'],
            'wastageProducts.*.wastage_qty' => ['required', 'integer', 'min:1'],
            'wastageProducts.*.unit_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function payload(): array
    {
        $products = array_values(array_filter($this->wastageProducts, fn ($row) => !empty($row['product_id'])));

        return [
            'type' => $this->type,
            'manager_id' => $this->manager_id,
            'site_id' => $this->site_id ?: null,
            'order_id' => $this->order_id ?: null,
            'date' => $this->date,
            'reason' => $this->reason,
            'products' => $products,
        ];
    }

    public function save(WastageService $service): void
    {
        $this->validate($this->rules());

        try {
            $data = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                $service->update((int) $this->editingId, $data);
                $message = 'Wastage updated successfully!';
            } else {
                $service->create($data);
                $message = 'Wastage created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.wastages.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.wastages.index'));
    }

    public function render(): View
    {
        return view('admin.Wastage.views.wastage-form', [
            'isEditMode' => $this->isEditMode,
            'wastageTypes' => $this->wastageTypes,
            'sites' => $this->sites,
            'managers' => $this->managers,
            'products' => $this->products,
            'orders' => $this->orders,
            'wastageProducts' => $this->wastageProducts,
        ])->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Wastage' : 'Add Wastage',
            'breadcrumb' => [
                ['Wastages', route('admin.wastages.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}

