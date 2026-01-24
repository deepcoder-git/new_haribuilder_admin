<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Material;

use App\Models\Category;
use App\Models\Material;
use App\Models\ProductImage;
use App\Models\Unit;
use App\Services\StockService;
use App\Utility\Enums\ProductTypeEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class MaterialForm extends Component
{
    use WithFileUploads;

    public bool $isEditMode = false;
    public int|string|null $editingId = null;

    // Fields (Blade expects these names)
    public string $material_name = '';
    public ?int $category_id = null;
    public ?string $unit_type = null;
    public ?int $available_qty = null;
    public ?int $low_stock_threshold = null;
    public string|int $material_type = 0; // maps to products.is_product
    public ?string $store = null; // StoreEnum value
    public string|bool|null $status = '1';

    // Image upload (single)
    public $image = null;
    public array $existingImages = [];

    // Category dropdown state
    public bool $categoryDropdownOpen = false;
    public string $categorySearch = '';
    public array $categorySearchResults = [];
    public bool $categoryLoading = false;
    public bool $categoryHasMore = true;
    public int $categoryPage = 1;

    // Unit dropdown state
    public bool $unitDropdownOpen = false;
    public string $unitSearch = '';
    public array $unitSearchResults = [];
    public bool $unitLoading = false;
    public bool $unitHasMore = true;
    public int $unitPage = 1;

    // Store dropdown state
    public bool $storeDropdownOpen = false;

    public function mount(?int $id = null): void
    {
        $this->hydrateCategoryResults(reset: true);
        $this->hydrateUnitResults(reset: true);

        if ($id) {
            /** @var Material $material */
            $material = Material::with(['productImages'])->findOrFail($id);
            $this->isEditMode = true;
            $this->editingId = $id;

            $this->material_name = (string) ($material->material_name ?? $material->product_name ?? '');
            $this->category_id = $material->category_id ? (int) $material->category_id : null;
            $this->unit_type = $material->unit_type ?: null;
            $this->available_qty = $material->available_qty !== null ? (int) $material->available_qty : null;
            $this->low_stock_threshold = $material->low_stock_threshold !== null ? (int) $material->low_stock_threshold : null;
            $this->material_type = (string) ((int) ($material->is_product ?? 0));
            $this->store = $material->store?->value ?? (is_string($material->store) ? $material->store : null);
            $this->status = $material->status ? '1' : '0';

            $this->existingImages = $material->productImages
                ->sortBy('order')
                ->map(fn (ProductImage $pi) => [
                    'id' => (string) $pi->id,
                    'name' => $pi->image_name ?? basename((string) $pi->image_path),
                    'url' => $pi->image_url,
                ])
                ->values()
                ->all();
        }
    }

    public function toggleCategoryDropdown(): void
    {
        $this->categoryDropdownOpen = !$this->categoryDropdownOpen;
        if ($this->categoryDropdownOpen) {
            $this->hydrateCategoryResults(reset: true);
        }
    }

    public function closeCategoryDropdown(): void
    {
        $this->categoryDropdownOpen = false;
    }

    public function handleCategorySearch(string $value): void
    {
        $this->categorySearch = $value;
        $this->hydrateCategoryResults(reset: true);
    }

    public function loadMoreCategories(): void
    {
        if (!$this->categoryHasMore || $this->categoryLoading) {
            return;
        }
        $this->hydrateCategoryResults(reset: false);
    }

    protected function hydrateCategoryResults(bool $reset): void
    {
        $this->categoryLoading = true;

        if ($reset) {
            $this->categoryPage = 1;
            $this->categorySearchResults = [];
            $this->categoryHasMore = true;
        }

        $query = Category::query()->orderBy('name');
        if ($this->categorySearch !== '') {
            $query->where('name', 'like', '%' . $this->categorySearch . '%');
        }

        $perPage = 20;
        $results = $query->paginate($perPage, ['*'], 'page', $this->categoryPage);

        $mapped = $results->getCollection()
            ->map(fn (Category $c) => ['id' => $c->id, 'text' => $c->name])
            ->values()
            ->all();

        $this->categorySearchResults = array_values(array_merge($this->categorySearchResults, $mapped));
        $this->categoryHasMore = $results->hasMorePages();
        $this->categoryPage++;

        $this->categoryLoading = false;
    }

    public function selectCategory(?int $id): void
    {
        $this->category_id = $id;
        $this->closeCategoryDropdown();
    }

    public function toggleUnitDropdown(): void
    {
        $this->unitDropdownOpen = !$this->unitDropdownOpen;
        if ($this->unitDropdownOpen) {
            $this->hydrateUnitResults(reset: true);
        }
    }

    public function closeUnitDropdown(): void
    {
        $this->unitDropdownOpen = false;
    }

    public function handleUnitSearch(string $value): void
    {
        $this->unitSearch = $value;
        $this->hydrateUnitResults(reset: true);
    }

    public function loadMoreUnits(): void
    {
        if (!$this->unitHasMore || $this->unitLoading) {
            return;
        }
        $this->hydrateUnitResults(reset: false);
    }

    protected function hydrateUnitResults(bool $reset): void
    {
        $this->unitLoading = true;

        if ($reset) {
            $this->unitPage = 1;
            $this->unitSearchResults = [];
            $this->unitHasMore = true;
        }

        $query = Unit::query()->where('status', true)->orderBy('name');
        if ($this->unitSearch !== '') {
            $query->where('name', 'like', '%' . $this->unitSearch . '%');
        }

        $perPage = 20;
        $results = $query->paginate($perPage, ['*'], 'page', $this->unitPage);

        $mapped = $results->getCollection()
            ->map(fn (Unit $u) => ['text' => $u->name])
            ->values()
            ->all();

        $this->unitSearchResults = array_values(array_merge($this->unitSearchResults, $mapped));
        $this->unitHasMore = $results->hasMorePages();
        $this->unitPage++;

        $this->unitLoading = false;
    }

    public function selectUnit(string $value): void
    {
        $this->unit_type = $value;
        $this->closeUnitDropdown();
    }

    public function toggleStoreDropdown(): void
    {
        $this->storeDropdownOpen = !$this->storeDropdownOpen;
    }

    public function closeStoreDropdown(): void
    {
        $this->storeDropdownOpen = false;
    }

    public function selectStore(string $value): void
    {
        $this->store = $value;

        // When selecting LPO, available quantity + low stock threshold fields are hidden.
        // Ensure we don't accidentally persist stale values.
        if ($value === StoreEnum::LPO->value) {
            $this->available_qty = null;
            $this->low_stock_threshold = null;
        }

        $this->closeStoreDropdown();
    }

    public function removeImage(): void
    {
        $this->image = null;
    }

    public function removeExistingImage(string $id): void
    {
        if (!$this->editingId) {
            return;
        }

        /** @var ProductImage|null $pi */
        $pi = ProductImage::query()
            ->where('id', $id)
            ->where('product_id', $this->editingId)
            ->first();

        if ($pi) {
            if (!empty($pi->image_path)) {
                Storage::disk('public')->delete($pi->image_path);
            }
            $pi->delete();
        }

        // Refresh existing images
        $material = Material::with('productImages')->findOrFail($this->editingId);
        $this->existingImages = $material->productImages
            ->sortBy('order')
            ->map(fn (ProductImage $p) => [
                'id' => (string) $p->id,
                'name' => $p->image_name ?? basename((string) $p->image_path),
                'url' => $p->image_url,
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $payload = $this->payload();

            if ($this->isEditMode && $this->editingId) {
                /** @var Material $material */
                $material = Material::findOrFail($this->editingId);
                $material->update($payload);

                $this->handleImageSave($material);

                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Material updated successfully!']);
            } else {
                /** @var Material $material */
                $material = Material::create($payload);

                // Ensure type is set for materials
                $material->update(['type' => ProductTypeEnum::Material->value]);

                $this->handleImageSave($material);

                // Optional: create initial stock entry so total_stock_quantity works immediately
                if (($this->store !== StoreEnum::LPO->value) && !empty($this->available_qty) && (int) $this->available_qty > 0) {
                    app(StockService::class)->adjustMaterialStock(
                        (int) $material->id,
                        (int) $this->available_qty,
                        'adjustment',
                        null,
                        "Material created with available quantity {$this->available_qty}",
                        null,
                        'Material Create'
                    );
                }

                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Material created successfully!']);
            }

            $this->redirect(route('admin.materials.index'));
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    protected function handleImageSave(Material $material): void
    {
        if (!$this->image) {
            return;
        }

        // This module is designed for a single primary image per material.
        // If a new image is uploaded (especially on edit), remove previous images first
        // to avoid storing old + new images together.
        $existing = ProductImage::query()
            ->where('product_id', $material->id)
            ->get();

        foreach ($existing as $pi) {
            if (!empty($pi->image_path)) {
                Storage::disk('public')->delete($pi->image_path);
            }
            $pi->delete();
        }

        // Also clear legacy single-image field if it exists (optional cleanup)
        try {
            $material->update(['image' => null]);
        } catch (\Exception $e) {
            // ignore
        }

        $path = $this->image->store('products', 'public');

        // Save as a single ProductImage row (order=1)
        try {
            ProductImage::create([
                'product_id' => $material->id,
                'image_path' => $path,
                'image_name' => $this->material_name,
                'order' => 1,
            ]);
        } catch (\Exception $e) {
            // fallback to products.image column
            $material->update(['image' => $path]);
        }

        $this->image = null;
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.materials.index'));
    }

    protected function rules(): array
    {
        return [
            'material_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit_type' => ['required', 'string', 'max:255'],
            'available_qty' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'material_type' => ['required', Rule::in(['0', '1', '2', 0, 1, 2])],
            'store' => [
                Rule::requiredIf(fn () => in_array((int) $this->material_type, [1, 2], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'image' => [
                Rule::requiredIf(fn () => !$this->isEditMode),
                'nullable',
                'image',
                'max:2048',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'material_name.required' => 'The material name is required.',
            'category_id.required' => 'Please select a category.',
            'unit_type.required' => 'Please select a unit type.',
            'store.required' => 'Please select a store.',
            'image.required' => 'Please upload an image.',
        ];
    }

    protected function payload(): array
    {
        $isLpo = ($this->store === StoreEnum::LPO->value);

        return [
            'product_name' => $this->material_name,
            'category_id' => $this->category_id,
            'unit_type' => $this->unit_type,
            // If Store = LPO, these fields are hidden and should be stored as NULL
            'available_qty' => $isLpo ? null : ($this->available_qty ?? 0),
            'low_stock_threshold' => $isLpo ? null : ($this->low_stock_threshold ?? 0),
            'is_product' => (int) $this->material_type,
            'store' => $this->store,
            'status' => true,
            'type' => ProductTypeEnum::Material->value,
        ];
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Material.views.material-form', [
            'isEditMode' => $this->isEditMode,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => $this->isEditMode ? 'Edit Material' : 'Add Material',
            'breadcrumb' => [
                ['Materials', route('admin.materials.index')],
                [$this->isEditMode ? 'Edit' : 'Create', '#'],
            ],
        ]);
    }
}


