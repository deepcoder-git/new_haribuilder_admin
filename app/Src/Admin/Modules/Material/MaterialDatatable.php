<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Material;

use App\Imports\MaterialImport;
use App\Models\Category;
use App\Models\Material;
use App\Models\Order;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class MaterialDatatable extends Component
{
    use WithPagination;
    use WithFileUploads;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    #[Url(as: 'sort')]
    public string $sortField = 'id';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    // Filters (persisted in URL)
    #[Url(as: 'category')]
    public ?int $filter_category_id = null;

    #[Url(as: 'unit')]
    public ?string $filter_unit_type = null;

    #[Url(as: 'status')]
    public ?string $filter_status = null; // active|inactive|null

    #[Url(as: 'material_type')]
    public ?string $filter_material_type = null; // 0|1|2|null

    // Temporary filters (UI dropdown)
    public string $tempCategoryFilter = 'all';
    public string $tempUnitFilter = 'all';
    public string $tempStatusFilter = 'all';
    public string $tempMaterialTypeFilter = 'all';

    // Delete modal state
    public ?int $materialToDelete = null;
    public ?string $materialNameToDelete = null;
    public bool $showDeleteModal = false;

    // Import modal state
    public bool $showImportModal = false;
    public bool $importing = false;
    public $importFile;
    public array $importErrors = [];
    public int $importSuccessCount = 0;
    public int $importErrorCount = 0;

    public function mount(): void
    {
        $perPage = request()->get('per_page', 10);
        $this->perPage = is_numeric($perPage) ? (int) $perPage : 10;

        if (!in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }

        $this->syncTempFilters();
    }

    public function syncTempFilters(): void
    {
        $this->tempCategoryFilter = $this->filter_category_id ? (string) $this->filter_category_id : 'all';
        $this->tempUnitFilter = $this->filter_unit_type ?: 'all';
        $this->tempStatusFilter = $this->filter_status ?: 'all';
        $this->tempMaterialTypeFilter = $this->filter_material_type ?: 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->filter_category_id = ($this->tempCategoryFilter === 'all') ? null : (int) $this->tempCategoryFilter;
        $this->filter_unit_type = ($this->tempUnitFilter === 'all') ? null : $this->tempUnitFilter;
        $this->filter_status = ($this->tempStatusFilter === 'all') ? null : $this->tempStatusFilter;
        $this->filter_material_type = ($this->tempMaterialTypeFilter === 'all') ? null : $this->tempMaterialTypeFilter;

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filter_category_id = null;
        $this->filter_unit_type = null;
        $this->filter_status = null;
        $this->filter_material_type = null;
        $this->search = '';

        $this->syncTempFilters();
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) ($this->filter_category_id || $this->filter_unit_type || $this->filter_status || $this->filter_material_type);
    }

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.materials.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.materials.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.materials.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        /** @var Material $material */
        $material = Material::findOrFail($id);
        $this->materialToDelete = (int) $id;
        $this->materialNameToDelete = $material->material_name ?? $material->product_name ?? 'Material';
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->materialToDelete = null;
        $this->materialNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->materialToDelete) {
            return;
        }

        try {
            $material = Material::findOrFail($this->materialToDelete);

            if ($this->isMaterialConnectedToOrders($material->id)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'This material cannot be deleted because it is connected to orders.',
                ]);
                $this->closeDeleteModal();
                return;
            }

            $material->delete();
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Material deleted successfully!']);
            $this->closeDeleteModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function toggleStatus(int|string $id): void
    {
        try {
            /** @var Material $material */
            $material = Material::findOrFail($id);

            // Prevent deactivating if connected to orders
            if ($material->status && $this->isMaterialConnectedToOrders($material->id)) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Cannot inactive material: material is connected to orders.',
                ]);
                return;
            }

            $material->update(['status' => !$material->status]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Material status updated successfully!',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function isMaterialConnectedToOrders(int $materialId): bool
    {
        return false;
        // return Order::query()
        //     ->whereHas('products', function (Builder $q) use ($materialId) {
        //         $q->where('products.id', $materialId);
        //     })
        //     ->exists();
    }

    public function getMaterialConnectionShortMessage(int $materialId): string
    {
        $count = Order::query()
            ->whereHas('products', function (Builder $q) use ($materialId) {
                $q->where('products.id', $materialId);
            })
            ->count();

        return $count > 0 ? "Used in {$count} order(s)" : 'Used in orders';
    }

    public function getMaterialTypeLabel($material): string
    {
        $type = (int) ($material->is_product ?? 0);
        return match ($type) {
            0 => 'Material Only',
            1 => 'Material As Product',
            2 => 'Material + Product',
            default => 'Material Only',
        };
    }

    public function renderImage($material): string
    {
        $url = $material->primary_image_url ?? null;
        if (!$url) {
            return '<div class="material-image-placeholder d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px; border-radius: 0.5rem; border: 1px solid #e5e7eb; background: #f9fafb;"><i class="fa-solid fa-image text-muted"></i></div>';
        }

        $name = htmlspecialchars((string) ($material->material_name ?? $material->product_name ?? 'Material'), ENT_QUOTES, 'UTF-8');
        $urlEsc = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');

        return '<div class="material-image-wrapper d-inline-block">'
            . '<img src="' . $urlEsc . '" class="material-table-image material-image-zoom" data-image-url="' . $urlEsc . '" data-material-name="' . $name . '"'
            . ' style="width: 44px; height: 44px; object-fit: cover; border-radius: 0.5rem; border: 1px solid #e5e7eb; cursor: pointer;" />'
            . '</div>';
    }

    public function renderAvailableQty($material): string
    {
        $qty = (int) ($material->total_stock_quantity ?? $material->available_qty ?? 0);
        $textColor = $qty === 0 ? 'text-danger' : 'text-gray-700';
        return '<span class="fw-semibold ' . $textColor . '">' . $qty . '</span>';
    }

    public function renderLowStock($material): string
    {
        $qty = (int) ($material->total_stock_quantity ?? $material->available_qty ?? 0);
        $threshold = (int) ($material->low_stock_threshold ?? 0);

        // Show red if quantity is 0 OR if quantity <= low stock threshold (when threshold is set)
        $isLow = $qty === 0 || ($threshold > 0 && $qty <= $threshold);
        $badgeClass = $isLow ? 'badge-light-danger' : 'badge-light-success';

        // Display the low stock threshold value (not the quantity)
        $displayValue = $threshold > 0 ? $threshold : 'N/A';

        return '<span class="badge ' . $badgeClass . '">' . $displayValue . '</span>';
    }

    public function openImportModal(): void
    {
        $this->resetImportState();
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        if ($this->importing) {
            return;
        }
        $this->showImportModal = false;
        $this->resetImportState();
    }

    protected function resetImportState(): void
    {
        $this->importFile = null;
        $this->importErrors = [];
        $this->importSuccessCount = 0;
        $this->importErrorCount = 0;
        $this->importing = false;
        $this->resetValidation();
    }

    public function importMaterials(): void
    {
        $this->validate([
            'importFile' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
        ], [
            'importFile.required' => 'Please select a file to import.',
            'importFile.mimes' => 'File must be an Excel or CSV file.',
        ]);

        $this->importing = true;
        $this->importErrors = [];
        $this->importSuccessCount = 0;
        $this->importErrorCount = 0;

        try {
            $importer = new MaterialImport();
            Excel::import($importer, $this->importFile);

            $this->importErrors = $importer->getErrors();
            $this->importSuccessCount = $importer->getSuccessCount();
            $this->importErrorCount = $importer->getErrorCount();

            if ($this->importErrorCount === 0) {
                $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Materials imported successfully!']);
            } else {
                $this->dispatch('show-toast', ['type' => 'warning', 'message' => 'Import completed with some errors.']);
            }

            // Refresh list
            $this->resetPage();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        } finally {
            $this->importing = false;
        }
    }

    public function getMaterialsProperty()
    {
        $query = Material::query()->with(['category', 'productImages']);

        if ($this->search) {
            $query->where('product_name', 'like', "%{$this->search}%");
        }

        if ($this->filter_category_id) {
            $query->where('category_id', $this->filter_category_id);
        }

        if ($this->filter_unit_type) {
            $query->where('unit_type', $this->filter_unit_type);
        }

        if ($this->filter_status) {
            if ($this->filter_status === 'active') {
                $query->where('status', true);
            } elseif ($this->filter_status === 'inactive') {
                $query->where('status', false);
            }
        }

        if ($this->filter_material_type !== null && $this->filter_material_type !== '') {
            $query->where('is_product', (int) $this->filter_material_type);
        }

        $sortField = match ($this->sortField) {
            'material_name' => 'product_name',
            'created_at' => 'created_at',
            default => 'id',
        };

        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function render(): View
    {
        $categories = Category::query()->orderBy('name')->get();
        $unitTypes = Unit::query()->where('status', true)->orderBy('name')->pluck('name');

        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Material.views.material-datatable', [
            'materials' => $this->materials,
            'categories' => $categories,
            'unitTypes' => $unitTypes,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Material Management',
            'breadcrumb' => [['Materials', route('admin.materials.index')]],
        ]);
    }
}


