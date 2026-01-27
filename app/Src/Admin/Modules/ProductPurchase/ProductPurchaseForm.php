<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\ProductPurchase;

use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Supplier;
use App\Services\ProductPurchaseService;
use Carbon\Carbon;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ProductPurchaseForm extends Component
{
    public bool $isEditMode = false;
    public ?int $editingId = null;

    public ?int $supplier_id = null;
    public string $invoice_no = '';
    public string $purchase_date = '';
    public ?string $notes = null;

    /** Supplier dropdown state */
    public bool $supplierDropdownOpen = false;
    public string $supplierSearch = '';
    /** @var array<int, array{id:int,text:string,type?:string|null}> */
    public array $supplierSearchResults = [];
    public bool $supplierLoading = false;
    public bool $supplierHasMore = false;
    public int $supplierPage = 1;
    public int $supplierPerPage = 20;

    /**
     * Purchase items:
     * [
     *   ['product_id' => int|null, 'quantity' => int, 'unit_price' => int, 'total_price' => int],
     * ]
     *
     * @var array<int, array{product_id:int|null,quantity:int,unit_price:int,total_price:int}>
     */
    public array $purchaseItems = [];

    /** Product dropdown per-row state */
    /** @var array<int, bool> */
    public array $productDropdownOpen = [];
    /** @var array<int, string> */
    public array $productSearch = [];
    /** @var array<int, array<int, array{id:int,text:string,image_url?:string|null,category_name?:string|null}>> */
    public array $productSearchResults = [];
    /** @var array<int, bool> */
    public array $productLoading = [];
    /** @var array<int, bool> */
    public array $productHasMore = [];
    /** @var array<int, int> */
    public array $productPage = [];
    public int $productPerPage = 20;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->isEditMode = true;
            $this->editingId = $id;

            /** @var ProductPurchase $purchase */
            $purchase = ProductPurchase::query()
                ->with(['items', 'items.product'])
                ->findOrFail($id);

            $this->supplier_id = (int) ($purchase->supplier_id ?? 0) ?: null;
            $this->invoice_no = (string) ($purchase->purchase_number ?? '');
            $this->purchase_date = $purchase->purchase_date ? $purchase->purchase_date->format('d/m/Y') : '';
            $this->notes = $purchase->notes;

            $this->purchaseItems = $purchase->items->map(function ($item) {
                $qty = (int) ($item->quantity ?? 0);
                $unit = (int) ($item->unit_price ?? 0);
                return [
                    'product_id' => (int) ($item->product_id ?? 0) ?: null,
                    'quantity' => max(1, $qty),
                    'unit_price' => max(0, $unit),
                    'total_price' => max(0, (int) ($item->total_price ?? ($qty * $unit))),
                ];
            })->values()->all();

            if (empty($this->purchaseItems)) {
                $this->purchaseItems = [$this->blankItemRow()];
            }
        } else {
            $this->invoice_no = app(ProductPurchaseService::class)->generatePurchaseNumber();
            $this->purchase_date = now()->format('d/m/Y');
            $this->purchaseItems = [$this->blankItemRow()];
        }

        $this->recalculateTotals();
    }

    protected function blankItemRow(): array
    {
        return [
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
        ];
    }

    // ---------- Supplier dropdown ----------
    public function toggleSupplierDropdown(): void
    {
        $this->supplierDropdownOpen = !$this->supplierDropdownOpen;
        if ($this->supplierDropdownOpen) {
            $this->supplierPage = 1;
            $this->loadSuppliers(reset: true);
        }
    }

    public function closeSupplierDropdown(): void
    {
        $this->supplierDropdownOpen = false;
    }

    public function handleSupplierSearch(string $value, string $field = 'supplierSearch'): void
    {
        $this->supplierSearch = $value;
        $this->supplierPage = 1;
        $this->loadSuppliers(reset: true);
    }

    public function loadMoreSuppliers(): void
    {
        if (!$this->supplierHasMore) {
            return;
        }

        $this->supplierPage++;
        $this->loadSuppliers(reset: false);
    }

    protected function loadSuppliers(bool $reset): void
    {
        $this->supplierLoading = true;

        $query = Supplier::query()->select(['id', 'name', 'supplier_type'])->orderBy('name');
        if ($this->supplierSearch !== '') {
            $query->where('name', 'like', '%' . $this->supplierSearch . '%');
        }

        $items = $query
            ->skip(($this->supplierPage - 1) * $this->supplierPerPage)
            ->take($this->supplierPerPage + 1)
            ->get();

        $hasMore = $items->count() > $this->supplierPerPage;
        $items = $items->take($this->supplierPerPage);

        $mapped = $items->map(fn (Supplier $s) => [
            'id' => $s->id,
            'text' => $s->name,
            'type' => $s->supplier_type,
        ])->values()->all();

        $this->supplierSearchResults = $reset ? $mapped : array_values(array_merge($this->supplierSearchResults, $mapped));
        $this->supplierHasMore = $hasMore;
        $this->supplierLoading = false;
    }

    public function selectSupplier(?int $id): void
    {
        if (!$id) {
            return;
        }
        $this->supplier_id = $id;
        $this->closeSupplierDropdown();
    }

    // ---------- Product dropdown ----------
    public function toggleProductDropdown(int $index): void
    {
        $open = (bool) ($this->productDropdownOpen[$index] ?? false);
        $willOpen = !$open;

        // Close all
        $this->productDropdownOpen = [];
        $this->productDropdownOpen[$index] = $willOpen;

        if ($willOpen) {
            $this->productPage[$index] = 1;
            $this->loadProducts($index, reset: true);
        }
    }

    public function closeProductDropdown(int $index): void
    {
        $this->productDropdownOpen[$index] = false;
    }

    public function handleProductSearch(string $value, string $field): void
    {
        // $field looks like "productSearch.<index>"
        $parts = explode('.', $field);
        $index = isset($parts[1]) ? (int) $parts[1] : 0;

        $this->productSearch[$index] = $value;
        $this->productPage[$index] = 1;
        $this->loadProducts($index, reset: true);
    }

    public function loadMoreProducts(int $index): void
    {
        if (!($this->productHasMore[$index] ?? false)) {
            return;
        }

        $this->productPage[$index] = (int) ($this->productPage[$index] ?? 1) + 1;
        $this->loadProducts($index, reset: false);
    }

    protected function loadProducts(int $index, bool $reset): void
    {
        $this->productLoading[$index] = true;

        $search = (string) ($this->productSearch[$index] ?? '');
        $page = (int) ($this->productPage[$index] ?? 1);

        // Purchase module: show all products/materials in dropdown (no store / type filter)
        $query = Product::query()
            ->with(['category', 'productImages'])
            ->where('store', '!=', StoreEnum::LPO)
            ->orderBy('product_name');

        if ($search !== '') {
            $query->where('product_name', 'like', '%' . $search . '%');
        }

        $items = $query
            ->skip(($page - 1) * $this->productPerPage)
            ->take($this->productPerPage + 1)
            ->get();

        $hasMore = $items->count() > $this->productPerPage;
        $items = $items->take($this->productPerPage);

        $mapped = $items->map(function (Product $p) {
            return [
                'id' => $p->id,
                'text' => $p->product_name,
                'image_url' => $p->primary_image_url,
                'category_name' => $p->category?->name,
            ];
        })->values()->all();

        $existing = $this->productSearchResults[$index] ?? [];
        $this->productSearchResults[$index] = $reset ? $mapped : array_values(array_merge($existing, $mapped));
        $this->productHasMore[$index] = $hasMore;
        $this->productLoading[$index] = false;
    }

    public function selectProduct(int $index, ?int $id): void
    {
        if (!$id) {
            return;
        }

        if (!array_key_exists($index, $this->purchaseItems)) {
            return;
        }

        $this->purchaseItems[$index]['product_id'] = $id;
        $this->closeProductDropdown($index);
    }

    // ---------- Quantity / totals ----------
    public function incrementQuantity(int $index): void
    {
        $current = (int) ($this->purchaseItems[$index]['quantity'] ?? 1);
        $this->purchaseItems[$index]['quantity'] = $current + 1;
        $this->recalculateTotals();
    }

    public function decrementQuantity(int $index): void
    {
        $current = (int) ($this->purchaseItems[$index]['quantity'] ?? 1);
        $this->purchaseItems[$index]['quantity'] = max(1, $current - 1);
        $this->recalculateTotals();
    }

    public function updatedPurchaseItems(): void
    {
        $this->recalculateTotals();
    }

    protected function recalculateTotals(): void
    {
        foreach ($this->purchaseItems as $i => $row) {
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unit = max(0, (int) ($row['unit_price'] ?? 0));
            $this->purchaseItems[$i]['quantity'] = $qty;
            $this->purchaseItems[$i]['unit_price'] = $unit;
            $this->purchaseItems[$i]['total_price'] = $qty * $unit;
        }
    }

    // ---------- Rows ----------
    public function addItemRow(): void
    {
        $this->purchaseItems[] = $this->blankItemRow();
    }

    public function removeItemRow(int $index): void
    {
        if (!array_key_exists($index, $this->purchaseItems)) {
            return;
        }

        unset($this->purchaseItems[$index]);
        $this->purchaseItems = array_values($this->purchaseItems);

        // Reset per-row dropdown state since indexes changed
        $this->productDropdownOpen = [];
        $this->productSearch = [];
        $this->productSearchResults = [];
        $this->productLoading = [];
        $this->productHasMore = [];
        $this->productPage = [];
    }

    // ---------- Save / Cancel ----------
    public function cancel(): void
    {
        $this->redirect(route('admin.product-purchases.index'));
    }

    protected function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'invoice_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_purchases', 'purchase_number')->ignore($this->editingId),
            ],
            'purchase_date' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'purchaseItems' => ['required', 'array', 'min:1'],
            'purchaseItems.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'purchaseItems.*.quantity' => ['required', 'integer', 'min:1'],
            'purchaseItems.*.unit_price' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function save(): void
    {
        $this->recalculateTotals();
        $this->validate();

        try {
            $date = Carbon::createFromFormat('d/m/Y', $this->purchase_date)->format('Y-m-d');

            $items = array_map(function (array $row) {
                $qty = max(1, (int) ($row['quantity'] ?? 1));
                $unit = max(0, (int) ($row['unit_price'] ?? 0));
                return [
                    'product_id' => (int) $row['product_id'],
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total_price' => $qty * $unit,
                ];
            }, $this->purchaseItems);

            $totalAmount = array_sum(array_map(fn ($r) => (int) ($r['total_price'] ?? 0), $items));

            $payload = [
                'supplier_id' => (int) $this->supplier_id,
                'purchase_date' => $date,
                'purchase_number' => $this->invoice_no,
                'total_amount' => (int) $totalAmount,
                'notes' => $this->notes,
                'status' => true,
                'items' => $items,
            ];

            $service = app(ProductPurchaseService::class);
            if ($this->isEditMode && $this->editingId) {
                $service->update($this->editingId, $payload);
                $message = 'Purchase updated successfully!';
            } else {
                $service->create($payload);
                $message = 'Purchase created successfully!';
            }

            $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            $this->redirect(route('admin.product-purchases.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render(): View
    {
        // Used only to show selected supplier name in the button; keep it light.
        $suppliers = Supplier::query()->select(['id', 'name', 'supplier_type'])->orderBy('name')->get();

        $selectedIds = collect($this->purchaseItems)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $products = empty($selectedIds)
            ? collect([])
            : Product::query()
                ->with(['category', 'productImages'])
                ->whereIn('id', $selectedIds)
                ->get();

        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::ProductPurchase.views.product-purchase-form', [
            'isEditMode' => $this->isEditMode,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Purchase' : 'Add Purchase',
            'breadcrumb' => [
                ['Product Purchases', route('admin.product-purchases.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


