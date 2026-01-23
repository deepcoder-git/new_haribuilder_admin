<?php

declare(strict_types=1);

namespace App\Utility\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseCrudComponent extends Component
{
    use WithPagination;

    // Common properties
    #[Url(as: 'modal')]
    public bool $showModal = false;
    public bool $isEditMode = false;
    public bool $isViewMode = false;
    public int|string|null $editingId = null;
    public string $search = '';
    public string $sortField = 'id';
    public string $sortDirection = 'desc';
    public int $perPage = 10;
    public array $selectedItems = [];
    public bool $selectAll = false;

    // Flash messages
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    // Date filter properties
    public ?string $dateFilterFrom = null;
    public ?string $dateFilterTo = null;
    public bool $showDateFilterDropdown = false;

    /**
     * Mount component
     */
    public function mount(): void
    {
        if (request()->has('modal')) {
            $modalValue = request()->get('modal');
            if ($modalValue === '1' || $modalValue === 'true' || $modalValue === true) {
                $this->showModal = true;
            }
        }
    }

    /**
     * Get the model class name
     */
    abstract protected function getModelClass(): string;

    /**
     * Get the view name
     */
    abstract protected function getViewName(): string;

    /**
     * Get validation rules for create/edit
     */
    abstract protected function getValidationRules(): array;

    /**
     * Get form data for create/edit
     */
    abstract protected function getFormData(): array;

    /**
     * Set form data from model
     */
    abstract protected function setFormData($model): void;

    /**
     * Reset form data
     */
    abstract protected function resetForm(): void;

    /**
     * Get query builder with filters
     */
    protected function getQuery()
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query();

        // Apply search
        if ($this->search) {
            $query = $this->applySearch($query);
        }

        // Apply date filters
        $query = $this->applyDateFilters($query);

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query;
    }

    /**
     * Apply search filters
     */
    protected function applySearch($query)
    {
        // Override in child class for specific search logic
        return $query;
    }

    /**
     * Apply date filters
     * Override this method in child class to customize date filtering logic
     */
    protected function applyDateFilters($query)
    {
        if ($this->dateFilterFrom || $this->dateFilterTo) {
            $dateField = $this->getDateFilterField();
            
            if ($this->dateFilterFrom && $this->dateFilterTo) {
                $query->whereBetween($dateField, [$this->dateFilterFrom, $this->dateFilterTo]);
            } elseif ($this->dateFilterFrom) {
                $query->whereDate($dateField, '>=', $this->dateFilterFrom);
            } elseif ($this->dateFilterTo) {
                $query->whereDate($dateField, '<=', $this->dateFilterTo);
            }
        }
        
        return $query;
    }

    /**
     * Get the date field to filter by
     * Override this method in child class to specify which field to filter
     * Default is 'created_at'
     */
    protected function getDateFilterField(): string
    {
        return 'created_at';
    }

    /**
     * Get paginated items
     */
    public function getItemsProperty()
    {
        return $this->getQuery()->paginate($this->perPage);
    }

    /**
     * Open create modal
     */
    public function openCreateModal(): void
    {
        $this->isEditMode = false;
        $this->isViewMode = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Open edit modal
     */
    public function openEditModal(int|string $id): void
    {
        $modelClass = $this->getModelClass();
        $model = $modelClass::findOrFail($id);

        $this->isEditMode = true;
        $this->isViewMode = false;
        $this->editingId = $id;
        $this->setFormData($model);
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Open view modal (read-only)
     */
    public function openViewModal(int|string $id): void
    {
        $modelClass = $this->getModelClass();
        $model = $modelClass::findOrFail($id);

        $this->isEditMode = false;
        $this->isViewMode = true;
        $this->editingId = $id;
        $this->setFormData($model);
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Close modal
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->isEditMode = false;
        $this->isViewMode = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
        $this->reset(['successMessage', 'errorMessage']);
    }

    /**
     * Save (create or update)
     */
    public function save(): void
    {
        $this->validate($this->getValidationRules());

        try {
            $modelClass = $this->getModelClass();
            $data = $this->getFormData();

            if ($this->isEditMode && $this->editingId) {
                $model = $modelClass::findOrFail($this->editingId);
                $model->update($data);
                $message = $this->getUpdateSuccessMessage();
                $this->successMessage = $message;
                $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
            } else {
                $modelClass::create($data);
                $message = $this->getCreateSuccessMessage();
                $this->successMessage = $message;
                $this->dispatch('show-toast', ['type' => 'success', 'message' => $message]);
                $this->search = '';
            }

            $this->closeModal();
            $this->reset(['errorMessage']);
            
            $this->resetPage();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->errorMessage = $errorMsg;
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $errorMsg]);
        }
    }

    /**
     * Delete item
     */
    public function delete(int|string $id): void
    {
        try {
            $modelClass = $this->getModelClass();
            $model = $modelClass::findOrFail($id);
            $model->delete();

            $message = $this->getDeleteSuccessMessage();
            $this->successMessage = $message;
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $message]);
            $this->selectedItems = array_filter($this->selectedItems, fn($item) => $item != $id);
            $this->reset(['errorMessage']);
            
            // Force Livewire to clear cached computed properties
            unset($this->items);
            
            $this->resetPage();
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->errorMessage = $errorMsg;
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $errorMsg]);
        }
    }

    /**
     * Toggle status
     */
    public function toggleStatus(int|string $id): void
    {
        try {
            $modelClass = $this->getModelClass();
            $model = $modelClass::findOrFail($id);
            $model->update(['status' => !$model->status]);
            $message = 'Status updated successfully!';
            $this->successMessage = $message;
            $this->dispatch('show-toast', type: 'success', message: $message);
            $this->reset(['errorMessage']);
            
            // Force refresh
            unset($this->items);
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->errorMessage = $errorMsg;
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $errorMsg]);
        }
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(): void
    {
        if (empty($this->selectedItems)) {
            $errorMsg = 'Please select items to delete';
            $this->errorMessage = $errorMsg;
            $this->dispatch('show-toast', ['type' => 'warning', 'message' => $errorMsg]);
            return;
        }

        try {
            $modelClass = $this->getModelClass();
            $count = count($this->selectedItems);
            $modelClass::whereIn('id', $this->selectedItems)->delete();

            $message = $count . ' item(s) deleted successfully';
            $this->successMessage = $message;
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $message]);
            $this->selectedItems = [];
            $this->selectAll = false;
            $this->reset(['errorMessage']);
            
            // Force refresh
            unset($this->items);
            
            $this->resetPage();
            $this->dispatch('$refresh');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->errorMessage = $errorMsg;
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $errorMsg]);
        }
    }

    /**
     * Toggle select all
     */
    public function updatedSelectAll($value): void
    {
        if ($value) {
            $items = $this->getItemsProperty();
            $this->selectedItems = $items->pluck('id')->toArray();
        } else {
            $this->selectedItems = [];
        }
    }

    /**
     * Sort by field
     */
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

    /**
     * Update per page
     */
    public function updatedPerPage($value): void
    {
        $this->perPage = (int) $value;
        $this->resetPage();
    }

    /**
     * Update per page from pagination component
     */
    public function updatedPagePerPage($value): void
    {
        $this->perPage = (int) $value;
        $this->resetPage();
    }

    /**
     * Update search
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Toggle date filter dropdown
     */
    public function toggleDateFilter(): void
    {
        $this->showDateFilterDropdown = !$this->showDateFilterDropdown;
    }

    /**
     * Reset date filters
     */
    public function resetDateFilter(): void
    {
        $this->dateFilterFrom = null;
        $this->dateFilterTo = null;
        $this->resetPage();
    }

    /**
     * Update date filter from
     */
    public function updatedDateFilterFrom(): void
    {
        if ($this->dateFilterFrom && $this->dateFilterTo && $this->dateFilterFrom > $this->dateFilterTo) {
            $this->dateFilterTo = null;
        }
        $this->resetPage();
    }

    /**
     * Update date filter to
     */
    public function updatedDateFilterTo(): void
    {
        if ($this->dateFilterFrom && $this->dateFilterTo && $this->dateFilterTo < $this->dateFilterFrom) {
            $this->dateFilterFrom = null;
        }
        $this->resetPage();
    }

    /**
     * Get success messages
     */
    protected function getCreateSuccessMessage(): string
    {
        return 'Item created successfully!';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Item updated successfully!';
    }

    protected function getDeleteSuccessMessage(): string
    {
        return 'Item deleted successfully!';
    }

    /**
     * Get view route name for navigation
     * Override this method in child classes if needed
     */
    protected function getViewRouteName(): ?string
    {
        $currentRoute = request()->route()->getName();
        if ($currentRoute) {
            // Convert 'admin.products.index' to 'admin.products.view'
            $viewRoute = str_replace('.index', '.view', $currentRoute);
            // Check if route exists
            if (\Illuminate\Support\Facades\Route::has($viewRoute)) {
                return $viewRoute;
            }
        }
        return null;
    }

    /**
     * Render component
     */
    public function render(): View
    {
        try {
            $items = $this->getItemsProperty();
        } catch (\Exception $e) {
            $items = [];
        }

        $viewData = [
            'items' => $items,
        ];

        // Add additional data if method exists
        if (method_exists($this, 'getAdditionalViewData')) {
            $additionalData = $this->getAdditionalViewData();
            if (is_array($additionalData)) {
                $viewData = array_merge($viewData, $additionalData);
            }
        }

        return view($this->getViewName(), $viewData);
    }
}

