<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Product;

use App\Models\Category;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Unit;
use App\Services\StockService;
use App\Utility\Enums\ProductTypeEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductForm extends Component
{
    use WithFileUploads;

    public bool $isEditMode = false;
    public ?int $productId = null;

    public string $product_name = '';
    public ?int $category_id = null;
    public ?string $unit_type = null;
    public ?string $store = null;
    public ?int $available_qty = null;
    public ?int $low_stock_threshold = null;

    /** Product type (stored in products.type) */
    public string $type;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null> */
    public array $images = [];

    /** @var array<int, array{id:string,url:string,name:string,path?:string|null,order?:int|null}> */
    public array $existingImages = [];

    // Category dropdown state
    public bool $categoryDropdownOpen = false;
    public string $categorySearch = '';
    /** @var array<int, array{id:int,text:string}> */
    public array $categorySearchResults = [];
    public bool $categoryLoading = false;
    public bool $categoryHasMore = false;
    public int $categoryPage = 1;
    public int $categoryPerPage = 20;

    // Unit dropdown state
    public bool $unitDropdownOpen = false;
    public string $unitSearch = '';
    /** @var array<int, array{text:string}> */
    public array $unitSearchResults = [];
    public bool $unitLoading = false;
    public bool $unitHasMore = false;
    public int $unitPage = 1;
    public int $unitPerPage = 30;

    // Store dropdown state
    public bool $storeDropdownOpen = false;

    // Materials table state
    /** @var array<int, array{material_id:int|null,material_name?:string|null,category_name?:string|null,quantity:int,unit_type?:string|null}> */
    public array $materials = [];

    /** @var array<int, bool> */
    public array $materialDropdownOpen = [];

    /** @var array<int, string> */
    public array $materialSearch = [];

    /** @var array<int, array<int, array{id:int,text:string,image_url?:string|null,category_name?:string|null,unit_type?:string|null}>> */
    public array $materialSearchResults = [];

    /** @var array<int, bool> */
    public array $materialLoading = [];

    /** @var array<int, bool> */
    public array $materialHasMore = [];

    /** @var array<int, int> */
    public array $materialPage = [];

    public int $materialPerPage = 15;

    public function mount(?int $id = null): void
    {
        $this->type = ProductTypeEnum::Product->value;

        if ($id) {
            $this->isEditMode = true;
            $this->productId = $id;

            /** @var Product $product */
            $product = Product::with(['productImages', 'materials.category'])
                ->whereIn('is_product', [1, 2])
                ->findOrFail($id);

            $this->product_name = (string) ($product->product_name ?? '');
            $this->category_id = $product->category_id;
            $this->unit_type = $product->unit_type;
            $this->store = $product->store?->value ?? null;
            $this->available_qty = $product->available_qty;
            $this->low_stock_threshold = $product->low_stock_threshold;
            $this->type = $product->type?->value ?? ProductTypeEnum::Product->value;

            $this->existingImages = $product->productImages->map(function (ProductImage $img) {
                return [
                    'id' => (string) $img->id,
                    'url' => $img->image_url,
                    'name' => $img->image_name ?? 'Image',
                    'path' => $img->image_path,
                    'order' => $img->order,
                ];
            })->values()->all();

            $this->materials = $product->materials->map(function (Product $m) {
                return [
                    'material_id' => (int) $m->id,
                    'material_name' => $m->product_name,
                    'category_name' => $m->category?->name,
                    'quantity' => (int) ($m->pivot->quantity ?? 0),
                    'unit_type' => (string) ($m->pivot->unit_type ?? $m->unit_type ?? ''),
                ];
            })->values()->all();
        } else {
            // defaults
            // No default store selection on create (user must choose)
            $this->store = null;
            $this->materials = [];
        }
    }

    protected function rules(): array
    {
        $isCreate = !$this->isEditMode;
        $isLpo = ($this->store === StoreEnum::LPO->value);

        $rules = [
            'product_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'unit_type' => ['required', 'string', 'max:255'],
            'store' => ['required', 'string'],
            'low_stock_threshold' => [$isLpo ? 'nullable' : 'nullable', 'integer', 'min:0'],
            'available_qty' => [$isLpo ? 'nullable' : 'nullable', 'integer', 'min:0'],
            'images' => [$isCreate ? 'required' : 'nullable', 'array'],
            'images.*' => ['nullable', 'image', 'max:2048'],
            'materials' => ['array'],
            // Prevent selecting the same material multiple times
            'materials.*.material_id' => ['nullable', 'integer', 'distinct'],
            'materials.*.quantity' => ['nullable', 'integer', 'min:0'],
        ];

        return $rules;
    }

    // ---------- Category dropdown ----------
    public function toggleCategoryDropdown(): void
    {
        $this->categoryDropdownOpen = !$this->categoryDropdownOpen;
        if ($this->categoryDropdownOpen) {
            $this->categoryPage = 1;
            $this->loadCategories(reset: true);
        }
    }

    public function closeCategoryDropdown(): void
    {
        $this->categoryDropdownOpen = false;
    }

    public function handleCategorySearch(string $value): void
    {
        $this->categorySearch = $value;
        $this->categoryPage = 1;
        $this->loadCategories(reset: true);
    }

    public function loadMoreCategories(): void
    {
        if (!$this->categoryHasMore) {
            return;
        }
        $this->categoryPage++;
        $this->loadCategories(reset: false);
    }

    protected function loadCategories(bool $reset): void
    {
        $this->categoryLoading = true;

        $query = Category::query()->orderBy('name');
        if ($this->categorySearch !== '') {
            $query->where('name', 'like', '%' . $this->categorySearch . '%');
        }

        $items = $query->skip(($this->categoryPage - 1) * $this->categoryPerPage)
            ->take($this->categoryPerPage + 1)
            ->get();

        $hasMore = $items->count() > $this->categoryPerPage;
        $items = $items->take($this->categoryPerPage);

        $mapped = $items->map(fn (Category $c) => ['id' => $c->id, 'text' => $c->name])->values()->all();

        $this->categorySearchResults = $reset ? $mapped : array_values(array_merge($this->categorySearchResults, $mapped));
        $this->categoryHasMore = $hasMore;
        $this->categoryLoading = false;
    }

    public function selectCategory(?int $id): void
    {
        $this->category_id = $id;
        $this->closeCategoryDropdown();
    }

    // ---------- Unit dropdown ----------
    public function toggleUnitDropdown(): void
    {
        $this->unitDropdownOpen = !$this->unitDropdownOpen;
        if ($this->unitDropdownOpen) {
            $this->unitPage = 1;
            $this->loadUnits(reset: true);
        }
    }

    public function closeUnitDropdown(): void
    {
        $this->unitDropdownOpen = false;
    }

    public function handleUnitSearch(string $value): void
    {
        $this->unitSearch = $value;
        $this->unitPage = 1;
        $this->loadUnits(reset: true);
    }

    public function loadMoreUnits(): void
    {
        if (!$this->unitHasMore) {
            return;
        }
        $this->unitPage++;
        $this->loadUnits(reset: false);
    }

    protected function loadUnits(bool $reset): void
    {
        $this->unitLoading = true;
        $query = Unit::query()->where('status', true)->orderBy('name');
        if ($this->unitSearch !== '') {
            $query->where('name', 'like', '%' . $this->unitSearch . '%');
        }

        $items = $query->skip(($this->unitPage - 1) * $this->unitPerPage)
            ->take($this->unitPerPage + 1)
            ->get();

        $hasMore = $items->count() > $this->unitPerPage;
        $items = $items->take($this->unitPerPage);

        $mapped = $items->map(fn (Unit $u) => ['text' => $u->name])->values()->all();
        $this->unitSearchResults = $reset ? $mapped : array_values(array_merge($this->unitSearchResults, $mapped));
        $this->unitHasMore = $hasMore;
        $this->unitLoading = false;
    }

    public function selectUnit(string $value): void
    {
        $this->unit_type = $value;
        $this->closeUnitDropdown();
    }

    // ---------- Store dropdown ----------
    public function toggleStoreDropdown(): void
    {
        if ($this->isEditMode) {
            return;
        }
        $this->storeDropdownOpen = !$this->storeDropdownOpen;
    }

    public function closeStoreDropdown(): void
    {
        $this->storeDropdownOpen = false;
    }

    public function selectStore(string $value): void
    {
        if ($this->isEditMode) {
            return;
        }
        $this->store = $value;

        // LPO hides these fields; ensure we don't persist stale values
        if ($value === StoreEnum::LPO->value) {
            $this->available_qty = null;
            $this->low_stock_threshold = null;
        }

        // Enforce store rules for materials rows:
        // - Hardware/LPO: only one material allowed
        // - Warehouse: multiple materials allowed
        $this->enforceMaterialLimitForStore();

        $this->closeStoreDropdown();
    }

    protected function enforceMaterialLimitForStore(): void
    {
        // Store rules:
        // - Hardware/LPO: only one material allowed
        // - Warehouse: multiple materials allowed

        $singleOnly = in_array($this->store, [StoreEnum::HardwareStore->value, StoreEnum::LPO->value], true);
        if (!$singleOnly) {
            return;
        }

        if (count($this->materials) <= 1) {
            return;
        }

        // Keep first selected material row if any, otherwise keep the first row.
        $keep = null;
        foreach ($this->materials as $row) {
            if (!empty($row['material_id'])) {
                $keep = $row;
                break;
            }
        }

        $this->materials = [($keep ?? $this->materials[0])];

        // cleanup per-row dropdown state (since indexes changed)
        $this->materialDropdownOpen = [];
        $this->materialSearch = [];
        $this->materialSearchResults = [];
        $this->materialLoading = [];
        $this->materialHasMore = [];
        $this->materialPage = [];
    }

    // ---------- Images ----------
    public function removeImage(int $index): void
    {
        if (!array_key_exists($index, $this->images)) {
            return;
        }
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function removeExistingImage(string $id): void
    {
        if (!$this->productId) {
            return;
        }

        $img = ProductImage::query()
            ->where('product_id', $this->productId)
            ->where('id', (int) $id)
            ->first();

        if ($img) {
            if (!empty($img->image_path)) {
                Storage::disk('public')->delete($img->image_path);
            }
            $img->delete();
        }

        $this->refreshExistingImages();
    }

    protected function refreshExistingImages(): void
    {
        if (!$this->productId) {
            $this->existingImages = [];
            return;
        }

        $product = Product::with('productImages')->find($this->productId);
        $this->existingImages = $product
            ? $product->productImages->map(fn (ProductImage $i) => [
                'id' => (string) $i->id,
                'url' => $i->image_url,
                'name' => $i->image_name ?? 'Image',
                'path' => $i->image_path,
                'order' => $i->order,
            ])->values()->all()
            : [];
    }

    protected function saveImages(Product $product): void
    {
        if (empty($this->images)) {
            return;
        }

        $startOrder = (int) (ProductImage::query()->where('product_id', $product->id)->max('order') ?? 0);

        foreach ($this->images as $idx => $file) {
            if (!$file) {
                continue;
            }
            $path = $file->store('products', 'public');

            try {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'image_name' => $this->product_name,
                    'order' => $startOrder + $idx + 1,
                ]);
            } catch (\Exception $e) {
                // fallback: keep at least first image in legacy column
                if (!$product->image) {
                    $product->update(['image' => $path]);
                }
            }
        }

        $this->images = [];
        $this->refreshExistingImages();
    }

    // ---------- Materials table ----------
    public function addMaterial(): void
    {
        // Enforce store rules on server side too (UI already disables button)
        if (in_array($this->store, [StoreEnum::HardwareStore->value, StoreEnum::LPO->value], true) && count($this->materials) >= 1) {
            return;
        }

        $this->materials[] = [
            'material_id' => null,
            'material_name' => null,
            'category_name' => null,
            'quantity' => 0,
            'unit_type' => null,
        ];
    }

    public function removeMaterial(int $index): void
    {
        if (!array_key_exists($index, $this->materials)) {
            return;
        }

        unset($this->materials[$index]);
        $this->materials = array_values($this->materials);

        // cleanup per-row dropdown state
        $this->materialDropdownOpen = [];
        $this->materialSearch = [];
        $this->materialSearchResults = [];
        $this->materialLoading = [];
        $this->materialHasMore = [];
        $this->materialPage = [];
    }

    public function incrementQuantity(int $index): void
    {
        $current = (int) ($this->materials[$index]['quantity'] ?? 0);
        $this->materials[$index]['quantity'] = $current + 1;
    }

    public function decrementQuantity(int $index): void
    {
        $current = (int) ($this->materials[$index]['quantity'] ?? 0);
        $this->materials[$index]['quantity'] = max(0, $current - 1);
    }

    public function toggleMaterialDropdown(int $index): void
    {
        $open = (bool) ($this->materialDropdownOpen[$index] ?? false);
        $willOpen = !$open;

        // Close other open dropdowns (prevents multi-row conflicts while searching/scrolling)
        foreach ($this->materialDropdownOpen as $i => $isOpen) {
            $this->materialDropdownOpen[$i] = false;
        }

        $this->materialDropdownOpen[$index] = $willOpen;

        if ($willOpen) {
            $this->materialPage[$index] = 1;
            $this->loadMaterials($index, reset: true);
        }
    }

    public function closeMaterialDropdown(int $index): void
    {
        $this->materialDropdownOpen[$index] = false;
    }

    /**
     * Livewire hook: when `materialSearch.<index>` updates, reload that row results.
     */
    public function updatedMaterialSearch(mixed $value, string|int $key): void
    {
        $index = (int) $key;
        if (!($this->materialDropdownOpen[$index] ?? false)) {
            return;
        }

        $this->materialPage[$index] = 1;
        $this->loadMaterials($index, reset: true);
    }

    public function loadMoreMaterials(int $index): void
    {
        if (!($this->materialHasMore[$index] ?? false)) {
            return;
        }
        $this->materialPage[$index] = (int) ($this->materialPage[$index] ?? 1) + 1;
        $this->loadMaterials($index, reset: false);
    }

    protected function loadMaterials(int $index, bool $reset): void
    {
        $this->materialLoading[$index] = true;

        $search = (string) ($this->materialSearch[$index] ?? '');
        $page = (int) ($this->materialPage[$index] ?? 1);

        // Only allow materials that can be used to create another product:
        // - 0: Material Only
        // - 2: Product + Material
        // Exclude 1 (Material As Product)
        $query = Material::query()
            ->with('category')
            ->whereIn('is_product', [0, 2])
            ->orderBy('product_name');
        if ($search !== '') {
            $query->where('product_name', 'like', '%' . $search . '%');
        }

        $items = $query->skip(($page - 1) * $this->materialPerPage)
            ->take($this->materialPerPage + 1)
            ->get();

        $hasMore = $items->count() > $this->materialPerPage;
        $items = $items->take($this->materialPerPage);

        $mapped = $items->map(function (Material $m) {
            return [
                'id' => $m->id,
                'text' => $m->product_name,
                'image_url' => $m->primary_image_url,
                'category_name' => $m->category?->name,
                'unit_type' => $m->unit_type,
            ];
        })->values()->all();

        $this->materialSearchResults[$index] = $reset
            ? $mapped
            : array_values(array_merge($this->materialSearchResults[$index] ?? [], $mapped));

        $this->materialHasMore[$index] = $hasMore;
        $this->materialLoading[$index] = false;
    }

    public function selectMaterial(int $index, ?int $id): void
    {
        if (!$id) {
            return;
        }

        // Prevent duplicate material selection across rows
        foreach ($this->materials as $i => $row) {
            if ($i === $index) {
                continue;
            }
            if (!empty($row['material_id']) && (int) $row['material_id'] === (int) $id) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'This material is already selected. Please choose a different material.',
                ]);
                $this->closeMaterialDropdown($index);
                return;
            }
        }

        /** @var Material|null $material */
        $material = Material::with('category')->find($id);
        if (!$material) {
            return;
        }

        // Block "Material As Product" from being used inside a product
        if ((int) ($material->is_product ?? 0) === 1) {
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Material As Product cannot be used to create another product.',
            ]);
            $this->closeMaterialDropdown($index);
            return;
        }

        $this->materials[$index]['material_id'] = $material->id;
        $this->materials[$index]['material_name'] = $material->product_name;
        $this->materials[$index]['category_name'] = $material->category?->name;
        $this->materials[$index]['unit_type'] = $material->unit_type;

        $this->closeMaterialDropdown($index);
    }

    // ---------- Save / Cancel ----------
    public function cancel(): void
    {
        $this->redirect(route('admin.products.index'));
    }

    public function save(): void
    {
        $this->validate();

        $isLpo = ($this->store === StoreEnum::LPO->value);

        DB::transaction(function () use ($isLpo): void {
            if ($this->isEditMode && $this->productId) {
                /** @var Product $product */
                $product = Product::findOrFail($this->productId);

                // If this record is actually a "material record" shown in Product listing
                // (Material as Product / Product + Material), do NOT change its type/is_product.
                $existingType = $product->type?->value ?? ProductTypeEnum::Product->value;
                $existingIsProduct = (int) ($product->is_product ?? 1);
                $isMaterialRecord = ($existingType !== ProductTypeEnum::Product->value);

                $payload = [
                    'product_name' => $this->product_name,
                    'category_id' => $this->category_id,
                    'unit_type' => $this->unit_type,
                    // Do not allow store changes on edit
                    'store' => $product->store?->value ?? $this->store,
                    'low_stock_threshold' => $isLpo ? null : $this->low_stock_threshold,
                    'available_qty' => $isLpo ? null : $this->available_qty,
                    'status' => true,
                    'type' => $existingType,
                    'is_product' => $existingIsProduct,
                ];

                $product->update($payload);

                $this->saveImages($product);

                // Material selection must not be visible/editable when editing material-records from Product module,
                // so we also do not touch pivot materials for those records.
                if (!$isMaterialRecord) {
                    $this->enforceMaterialLimitForStore();
                    $this->syncMaterials($product);
                }
            } else {
                $this->enforceMaterialLimitForStore();

                $payload = [
                    'product_name' => $this->product_name,
                    'category_id' => $this->category_id,
                    'unit_type' => $this->unit_type,
                    'store' => $this->store,
                    'low_stock_threshold' => $isLpo ? null : $this->low_stock_threshold,
                    'available_qty' => $isLpo ? null : $this->available_qty,
                    'status' => true,
                    'type' => ProductTypeEnum::Product->value,
                    'is_product' => 1,
                ];

                /** @var Product $product */
                $product = Product::create($payload);

                $this->saveImages($product);
                $this->syncMaterials($product);

                // Initial stock entry for create mode (non-LPO)
                if (!$isLpo && !empty($this->available_qty) && (int) $this->available_qty > 0) {
                    app(StockService::class)->adjustProductStock(
                        (int) $product->id,
                        (int) $this->available_qty,
                        'adjustment',
                        null,
                        'Initial stock',
                        null,
                        'Initial stock adjustment'
                    );
                }

                $this->productId = (int) $product->id;
                $this->isEditMode = true;
            }
        });

        $this->dispatch('show-toast', [
            'type' => 'success',
            'message' => $this->isEditMode ? 'Product updated successfully!' : 'Product created successfully!',
        ]);

        $this->redirect(route('admin.products.index'));
    }

    protected function syncMaterials(Product $product): void
    {
        // Store rules:
        // - Warehouse: multiple materials allowed
        // - Hardware/LPO: only one material allowed (enforced before calling)

        $sync = [];
        foreach ($this->materials as $row) {
            $materialId = (int) ($row['material_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            if ($materialId <= 0 || $qty <= 0) {
                continue;
            }
            $sync[$materialId] = [
                'quantity' => $qty,
                'unit_type' => (string) ($row['unit_type'] ?? ''),
            ];
        }

        // Enforce allowed material types server-side as well (0 or 2, type=material)
        if (!empty($sync)) {
            $ids = array_keys($sync);
            $allowedIds = Product::query()
                ->whereIn('id', $ids)
                ->where('type', ProductTypeEnum::Material->value)
                ->whereIn('is_product', [0, 2])
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $sync = array_intersect_key($sync, array_flip($allowedIds));
        }

        $product->materials()->sync($sync);
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Product.views.product-form');

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Product' : 'Add Product',
            'breadcrumb' => [['Products', route('admin.products.index')]],
        ]);
    }
}


