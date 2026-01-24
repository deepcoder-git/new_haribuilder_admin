<?php

declare(strict_types=1);

/** @intelephense-ignore-file */

namespace App\Src\Admin\Modules\Lpo;

use App\Models\Moderator;
use App\Models\Order;
use App\Models\Product;
use App\Models\Site;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\OrderCustomProductManager;
use App\Utility\Enums\OrderStatusEnum;
use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class LpoForm extends Component
{
    use WithFileUploads;

    public bool $isEditMode = false;
    public ?int $editingId = null;

    public ?int $site_id = null;
    public ?int $site_manager_id = null;
    public string $sale_date = '';
    public ?string $expected_delivery_date = null;
    public ?string $priority = null;
    public ?string $note = null;
    public ?string $drop_location = null;

    /** @var array<int, array<string,mixed>> */
    public array $orderProducts = [];

    /** @var array<string,mixed> */
    public array $productStatuses = [
        'lpo' => [],
    ];

    // Site dropdown
    public bool $siteDropdownOpen = false;
    public string $siteSearch = '';
    /** @var array<int, array<string,mixed>> */
    public array $siteSearchResults = [];
    public bool $siteLoading = false;
    public bool $siteHasMore = true;
    public int $sitePage = 1;

    // Site manager dropdown
    public bool $siteManagerDropdownOpen = false;
    public string $siteManagerSearch = '';
    /** @var array<int, array<string,mixed>> */
    public array $siteManagerSearchResults = [];
    public bool $siteManagerLoading = false;
    public bool $siteManagerHasMore = true;
    public int $siteManagerPage = 1;
    public bool $shouldDisableSiteSupervisor = false;

    // Product dropdown per row
    /** @var array<int,bool> */
    public array $productDropdownOpen = [];
    /** @var array<int,string> */
    public array $productSearch = [];
    /** @var array<int, array<int, array<string,mixed>>> */
    public array $productSearchResults = [];
    /** @var array<int,bool> */
    public array $productLoading = [];
    /** @var array<int,bool> */
    public array $productHasMore = [];
    /** @var array<int,int> */
    public array $productPage = [];

    // Supplier dropdown per row
    /** @var array<int,bool> */
    public array $supplierDropdownOpen = [];
    /** @var array<int,string> */
    public array $supplierSearch = [];
    /** @var array<int, array<int, array<string,mixed>>> */
    public array $supplierSearchResults = [];
    /** @var array<int,bool> */
    public array $supplierLoading = [];
    /** @var array<int,bool> */
    public array $supplierHasMore = [];
    /** @var array<int,int> */
    public array $supplierPage = [];

    // Transit modal used in blade
    public bool $showInTransitModal = false;
    public ?string $temp_driver_name = null;
    public ?string $temp_vehicle_number = null;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->isEditMode = true;
            $this->editingId = $id;

            /** @var Order $order */
            $order = Order::query()->with(['products', 'site'])->findOrFail($id);

            $this->site_id = $order->site_id;
            $this->site_manager_id = $order->site_manager_id;
            $this->sale_date = $order->sale_date ? $order->sale_date->format('Y-m-d') : '';
            $this->expected_delivery_date = $order->expected_delivery_date ? $order->expected_delivery_date->format('Y-m-d') : null;
            $this->priority = $order->priority;
            $this->note = $order->note;
            $this->drop_location = $order->drop_location;

            if (is_array($order->product_status)) {
                $this->productStatuses = array_merge($this->productStatuses, $order->product_status);
            }

            $supplierMapping = is_array($order->supplier_id) ? $order->supplier_id : [];
            $this->orderProducts = $order->products->map(function (Product $p) use ($supplierMapping) {
                return [
                    'product_id' => $p->id,
                    'quantity' => (int) ($p->pivot->quantity ?? 1),
                    'is_custom' => 0,
                    'custom_note' => null,
                    'custom_images' => [],
                    'supplier_id' => isset($supplierMapping[(string) $p->id]) ? (int) $supplierMapping[(string) $p->id] : null,
                ];
            })->values()->all();
        }

        if (empty($this->orderProducts)) {
            $this->orderProducts = [[
                'product_id' => null,
                'quantity' => 1,
                'is_custom' => 0,
                'custom_note' => null,
                'custom_images' => [],
                'supplier_id' => null,
            ]];
        }
    }

    // ---------- Site dropdown ----------
    public function toggleSiteDropdown(): void
    {
        $this->siteDropdownOpen = !$this->siteDropdownOpen;
        if ($this->siteDropdownOpen && empty($this->siteSearchResults)) {
            $this->sitePage = 1;
            $this->siteHasMore = true;
            $this->siteSearchResults = [];
            $this->loadMoreSites(true);
        }
    }

    public function closeSiteDropdown(): void
    {
        $this->siteDropdownOpen = false;
    }

    public function handleSiteSearch(string $term, string $field = 'siteSearch'): void
    {
        $this->siteSearch = $term;
        $this->sitePage = 1;
        $this->siteHasMore = true;
        $this->siteSearchResults = [];
        $this->loadMoreSites(true);
    }

    public function loadMoreSites(bool $reset = false): void
    {
        if (!$this->siteHasMore && !$reset) {
            return;
        }

        $this->siteLoading = true;
        $perPage = 20;

        $query = Site::query()->select(['id', 'name', 'location', 'address', 'site_manager_id'])->orderBy('name');
        if ($this->siteSearch !== '') {
            $s = $this->siteSearch;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('location', 'like', "%{$s}%")
                    ->orWhere('address', 'like', "%{$s}%");
            });
        }

        $sites = $query->forPage($this->sitePage, $perPage)->get();
        $results = $sites->map(fn (Site $site) => [
            'id' => $site->id,
            'text' => $site->name,
            'location' => $site->location,
        ])->all();

        $this->siteSearchResults = array_values(array_merge($this->siteSearchResults, $results));
        $this->siteHasMore = count($results) === $perPage;
        $this->sitePage++;
        $this->siteLoading = false;
    }

    public function selectSite(?int $id): void
    {
        if (!$id) {
            $this->site_id = null;
            $this->drop_location = null;
            $this->site_manager_id = null;
            $this->shouldDisableSiteSupervisor = false;
            $this->closeSiteDropdown();
            return;
        }

        $site = Site::query()->find($id);
        if (!$site) {
            return;
        }

        $this->site_id = (int) $site->id;
        $this->drop_location = $site->address ?: $site->location;
        if ($site->site_manager_id) {
            $this->site_manager_id = (int) $site->site_manager_id;
            $this->shouldDisableSiteSupervisor = true;
        } else {
            $this->shouldDisableSiteSupervisor = false;
        }

        $this->closeSiteDropdown();
    }

    // ---------- Site manager dropdown ----------
    public function toggleSiteManagerDropdown(): void
    {
        $this->siteManagerDropdownOpen = !$this->siteManagerDropdownOpen;
        if ($this->siteManagerDropdownOpen && $this->site_id && empty($this->siteManagerSearchResults)) {
            $this->siteManagerPage = 1;
            $this->siteManagerHasMore = true;
            $this->siteManagerSearchResults = [];
            $this->loadMoreSiteManagers(true);
        }
    }

    public function closeSiteManagerDropdown(): void
    {
        $this->siteManagerDropdownOpen = false;
    }

    public function handleSiteManagerSearch(string $term, string $field = 'siteManagerSearch'): void
    {
        $this->siteManagerSearch = $term;
        $this->siteManagerPage = 1;
        $this->siteManagerHasMore = true;
        $this->siteManagerSearchResults = [];
        $this->loadMoreSiteManagers(true);
    }

    public function loadMoreSiteManagers(bool $reset = false): void
    {
        if (!$this->site_id) {
            return;
        }
        if (!$this->siteManagerHasMore && !$reset) {
            return;
        }

        $this->siteManagerLoading = true;
        $perPage = 20;

        $query = Moderator::query()
            ->select(['id', 'name', 'email', 'role'])
            ->where('role', RoleEnum::StoreManager->value)
            ->orderBy('name');

        if ($this->siteManagerSearch !== '') {
            $s = $this->siteManagerSearch;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $mods = $query->forPage($this->siteManagerPage, $perPage)->get();
        $results = $mods->map(fn (Moderator $m) => [
            'id' => $m->id,
            'text' => $m->name,
            'email' => $m->email,
        ])->all();

        $this->siteManagerSearchResults = array_values(array_merge($this->siteManagerSearchResults, $results));
        $this->siteManagerHasMore = count($results) === $perPage;
        $this->siteManagerPage++;
        $this->siteManagerLoading = false;
    }

    public function selectSiteManager(?int $id): void
    {
        $this->site_manager_id = $id ? (int) $id : null;
        $this->closeSiteManagerDropdown();
    }

    // ---------- Product dropdown ----------
    public function toggleProductDropdown(int $index): void
    {
        $this->productDropdownOpen[$index] = !($this->productDropdownOpen[$index] ?? false);
        if (($this->productDropdownOpen[$index] ?? false) && empty($this->productSearchResults[$index] ?? [])) {
            $this->productPage[$index] = 1;
            $this->productHasMore[$index] = true;
            $this->productSearchResults[$index] = [];
            $this->loadMoreProducts($index, true);
        }
    }

    public function closeProductDropdown(int $index): void
    {
        $this->productDropdownOpen[$index] = false;
    }

    public function handleProductSearch(string $term, string $field): void
    {
        $parts = explode('.', $field);
        $index = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null;
        if ($index === null) {
            return;
        }

        $this->productSearch[$index] = $term;
        $this->productPage[$index] = 1;
        $this->productHasMore[$index] = true;
        $this->productSearchResults[$index] = [];
        $this->loadMoreProducts($index, true);
    }

    public function loadMoreProducts(int $index, bool $reset = false): void
    {
        if (!($this->productHasMore[$index] ?? true) && !$reset) {
            return;
        }

        $this->productLoading[$index] = true;
        $perPage = 20;
        $page = $this->productPage[$index] ?? 1;
        $term = $this->productSearch[$index] ?? '';

        $query = Product::query()
            ->with(['category', 'productImages'])
            ->where('store', StoreEnum::LPO)
            ->orderBy('product_name');

        if ($term !== '') {
            $query->where('product_name', 'like', "%{$term}%");
        }

        $products = $query->forPage($page, $perPage)->get();
        $results = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'text' => $p->product_name,
            'category_name' => $p->category?->name,
            'unit_type' => $p->unit_type,
            'image_url' => $p->first_image_url,
        ])->all();

        $this->productSearchResults[$index] = array_values(array_merge($this->productSearchResults[$index] ?? [], $results));
        $this->productHasMore[$index] = count($results) === $perPage;
        $this->productPage[$index] = $page + 1;
        $this->productLoading[$index] = false;
    }

    public function selectProduct(int $index, ?int $productId): void
    {
        if (!isset($this->orderProducts[$index])) {
            return;
        }
        $this->orderProducts[$index]['product_id'] = $productId ?: null;
        $this->closeProductDropdown($index);
    }

    // ---------- Supplier dropdown ----------
    public function toggleSupplierDropdown(int $index): void
    {
        $this->supplierDropdownOpen[$index] = !($this->supplierDropdownOpen[$index] ?? false);
        if (($this->supplierDropdownOpen[$index] ?? false) && empty($this->supplierSearchResults[$index] ?? [])) {
            $this->supplierPage[$index] = 1;
            $this->supplierHasMore[$index] = true;
            $this->supplierSearchResults[$index] = [];
            $this->loadMoreSuppliers($index, true);
        }
    }

    public function closeSupplierDropdown(int $index): void
    {
        $this->supplierDropdownOpen[$index] = false;
    }

    public function handleSupplierSearch(string $term, string $field): void
    {
        $parts = explode('.', $field);
        $index = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null;
        if ($index === null) {
            return;
        }

        $this->supplierSearch[$index] = $term;
        $this->supplierPage[$index] = 1;
        $this->supplierHasMore[$index] = true;
        $this->supplierSearchResults[$index] = [];
        $this->loadMoreSuppliers($index, true);
    }

    public function loadMoreSuppliers(int $index, bool $reset = false): void
    {
        if (!($this->supplierHasMore[$index] ?? true) && !$reset) {
            return;
        }

        $this->supplierLoading[$index] = true;
        $perPage = 20;
        $page = $this->supplierPage[$index] ?? 1;
        $term = $this->supplierSearch[$index] ?? '';

        $query = Supplier::query()->select(['id', 'name', 'type'])->orderBy('name');
        if ($term !== '') {
            $query->where('name', 'like', "%{$term}%");
        }

        $suppliers = $query->forPage($page, $perPage)->get();
        $results = $suppliers->map(fn (Supplier $s) => [
            'id' => $s->id,
            'text' => $s->name,
            'type' => $s->type ?? null,
        ])->all();

        $this->supplierSearchResults[$index] = array_values(array_merge($this->supplierSearchResults[$index] ?? [], $results));
        $this->supplierHasMore[$index] = count($results) === $perPage;
        $this->supplierPage[$index] = $page + 1;
        $this->supplierLoading[$index] = false;
    }

    public function selectSupplier(int $index, ?int $supplierId): void
    {
        if (!isset($this->orderProducts[$index])) {
            return;
        }
        $this->orderProducts[$index]['supplier_id'] = $supplierId ?: null;
        $this->closeSupplierDropdown($index);
    }

    public function updateLpoSupplierStatus(int $index, string $status): void
    {
        $supplierId = $this->orderProducts[$index]['supplier_id'] ?? null;
        if (!$supplierId) {
            return;
        }
        if (!isset($this->productStatuses['lpo']) || !is_array($this->productStatuses['lpo'])) {
            $this->productStatuses['lpo'] = [];
        }
        $this->productStatuses['lpo'][(string) $supplierId] = $status;
    }

    // ---------- Row actions ----------
    public function removeProductRow(int $index): void
    {
        if (!array_key_exists($index, $this->orderProducts)) {
            return;
        }
        unset($this->orderProducts[$index]);
        $this->orderProducts = array_values($this->orderProducts);
        if (empty($this->orderProducts)) {
            $this->orderProducts[] = [
                'product_id' => null,
                'quantity' => 1,
                'is_custom' => 0,
                'custom_note' => null,
                'custom_images' => [],
                'supplier_id' => null,
            ];
        }
    }

    public function incrementQuantity(int $index): void
    {
        if (!isset($this->orderProducts[$index])) {
            return;
        }
        $q = (int) ($this->orderProducts[$index]['quantity'] ?? 1);
        $this->orderProducts[$index]['quantity'] = $q + 1;
    }

    public function decrementQuantity(int $index): void
    {
        if (!isset($this->orderProducts[$index])) {
            return;
        }
        $q = (int) ($this->orderProducts[$index]['quantity'] ?? 1);
        $this->orderProducts[$index]['quantity'] = max(1, $q - 1);
    }

    public function removeCustomImage(int $index, int $imgIndex): void
    {
        if (!isset($this->orderProducts[$index])) {
            return;
        }
        $images = $this->orderProducts[$index]['custom_images'] ?? [];
        if (!is_array($images) || !array_key_exists($imgIndex, $images)) {
            return;
        }
        unset($images[$imgIndex]);
        $this->orderProducts[$index]['custom_images'] = array_values($images);
    }

    public function closeInTransitModal(): void
    {
        $this->showInTransitModal = false;
        $this->temp_driver_name = null;
        $this->temp_vehicle_number = null;
    }

    public function saveInTransitDetails(): void
    {
        $this->closeInTransitModal();
    }

    public function getCurrentStockForProduct($productId, $siteId): int
    {
        $pid = is_numeric($productId) ? (int) $productId : 0;
        $sid = is_numeric($siteId) ? (int) $siteId : 0;
        if ($pid <= 0 || $sid <= 0) {
            return 0;
        }

        $qty = Stock::query()
            ->where('product_id', $pid)
            ->where('site_id', $sid)
            ->orderByDesc('id')
            ->value('quantity');

        return is_numeric($qty) ? (int) $qty : 0;
    }

    public function save(): void
    {
        $this->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'priority' => ['required', 'string'],
            'expected_delivery_date' => ['required', 'date'],
            'orderProducts' => ['required', 'array', 'min:1'],
        ]);

        try {
            DB::beginTransaction();

            $payload = [
                'site_id' => $this->site_id,
                'site_manager_id' => $this->site_manager_id,
                'sale_date' => $this->sale_date ?: now()->format('Y-m-d'),
                'expected_delivery_date' => $this->expected_delivery_date,
                'priority' => $this->priority,
                'note' => $this->note,
                'drop_location' => $this->drop_location,
                'status' => OrderStatusEnum::Pending->value,
                'is_lpo' => true,
            ];

            /** @var Order $order */
            if ($this->isEditMode && $this->editingId) {
                $order = Order::query()->findOrFail($this->editingId);
                $order->update($payload);
            } else {
                $order = Order::query()->create(array_merge($payload, [
                    'is_custom_product' => false,
                ]));
                $this->editingId = (int) $order->id;
                $this->isEditMode = true;
            }

            $sync = [];
            $supplierMapping = [];
            $hasCustom = false;

            foreach ($this->orderProducts as $row) {
                $isCustom = (int) ($row['is_custom'] ?? 0) === 1;
                if ($isCustom) {
                    $hasCustom = true;
                    continue;
                }

                $productId = isset($row['product_id']) && is_numeric($row['product_id']) ? (int) $row['product_id'] : 0;
                $qty = isset($row['quantity']) && is_numeric($row['quantity']) ? (int) $row['quantity'] : 0;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $sync[$productId] = ['quantity' => $qty];

                $supplierId = isset($row['supplier_id']) && is_numeric($row['supplier_id']) ? (int) $row['supplier_id'] : null;
                if ($supplierId) {
                    $supplierMapping[(string) $productId] = $supplierId;
                }
            }

            $order->products()->sync($sync);
            $order->update([
                'supplier_id' => $supplierMapping,
                'product_status' => $this->productStatuses,
                'is_custom_product' => $hasCustom,
            ]);

            if ($hasCustom) {
                $manager = app(OrderCustomProductManager::class);
                foreach ($this->orderProducts as $row) {
                    $isCustom = (int) ($row['is_custom'] ?? 0) === 1;
                    if (!$isCustom) {
                        continue;
                    }

                    $note = trim((string) ($row['custom_note'] ?? ''));
                    $uploads = $row['custom_images'] ?? [];
                    if (!is_array($uploads)) {
                        $uploads = $uploads ? [$uploads] : [];
                    }

                    $imagePaths = [];
                    foreach ($uploads as $upload) {
                        if (is_string($upload)) {
                            $imagePaths[] = $upload;
                            continue;
                        }
                        if ($upload) {
                            $path = $upload->store('order_custom_products', 'public');
                            if ($path) {
                                $imagePaths[] = $path;
                            }
                        }
                    }

                    if ($note === '' && empty($imagePaths)) {
                        continue;
                    }

                    $manager->create((int) $order->id, [
                        'custom_note' => $note ?: null,
                        'product_ids' => [],
                    ], $imagePaths);
                }
            }

            DB::commit();
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'LPO saved successfully!']);
            $this->redirect(route('admin.lpo.index'));
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.lpo.index'));
    }

    public function render(): View
    {
        // Optimization: only load selected site/site supervisor/products.
        $sites = Site::query()
            ->when($this->site_id, fn ($q) => $q->where('id', $this->site_id))
            ->orderBy('name')
            ->get();

        $siteManagers = Moderator::query()
            ->when($this->site_manager_id, fn ($q) => $q->where('id', $this->site_manager_id))
            ->orderBy('name')
            ->get();

        $productIds = collect($this->orderProducts)
            ->pluck('product_id')
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $products = empty($productIds)
            ? collect()
            : Product::query()
                ->with(['category', 'productImages'])
                ->where('store', StoreEnum::LPO)
                ->whereIn('id', $productIds)
                ->orderBy('product_name')
                ->get();

        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::LPO.views.lpo-form', [
            'sites' => $sites,
            'siteManagers' => $siteManagers,
            'products' => $products,
            'priorities' => PriorityEnum::cases(),
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit LPO' : 'Add LPO',
            'breadcrumb' => [
                ['LPOs', route('admin.lpo.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


