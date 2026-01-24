<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Category;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CategoryDatatable extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'per_page')]
    public int $perPage = 10;

    #[Url(as: 'sort')]
    public string $sortField = 'id';

    #[Url(as: 'direction')]
    public string $sortDirection = 'desc';

    #[Url(as: 'status')]
    public ?string $filter_status = null; // active|inactive|null

    public ?string $tempStatusFilter = null;

    public ?int $categoryToDelete = null;
    public ?string $categoryNameToDelete = null;
    public bool $showDeleteModal = false;

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
        $this->tempStatusFilter = $this->filter_status ?: 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->filter_status = ($this->tempStatusFilter === 'all' || $this->tempStatusFilter === null)
            ? null
            : $this->tempStatusFilter;

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filter_status = null;
        $this->tempStatusFilter = 'all';
        $this->search = '';
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return (bool) ($this->filter_status && $this->filter_status !== 'all');
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

    public function openCreateForm(): void
    {
        $this->redirect(route('admin.categories.create'));
    }

    public function openEditForm(int|string $id): void
    {
        $this->redirect(route('admin.categories.edit', $id));
    }

    public function openViewModal(int|string $id): void
    {
        $this->redirect(route('admin.categories.view', $id));
    }

    public function confirmDelete(int|string $id): void
    {
        /** @var Category $category */
        $category = Category::findOrFail($id);
        $this->categoryToDelete = (int) $id;
        $this->categoryNameToDelete = $category->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->categoryToDelete = null;
        $this->categoryNameToDelete = null;
        $this->showDeleteModal = false;
    }

    public function delete(): void
    {
        if (!$this->categoryToDelete) {
            return;
        }

        try {
            /** @var Category $category */
            $category = Category::findOrFail($this->categoryToDelete);

            if (method_exists($category, 'isAssignedToProducts') && $category->isAssignedToProducts()) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'This category cannot be deleted because it is assigned to products.',
                ]);
                $this->closeDeleteModal();
                return;
            }

            $category->delete();

            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Category deleted successfully!']);
            $this->resetPage();
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
            $this->closeDeleteModal();
        }
    }

    public function toggleStatus(int|string $id): void
    {
        try {
            /** @var Category $category */
            $category = Category::findOrFail($id);

            if (method_exists($category, 'isAssignedToProducts') && $category->isAssignedToProducts()) {
                $this->dispatch('show-toast', [
                    'type' => 'error',
                    'message' => 'Cannot change status: Category is assigned to products.',
                ]);
                return;
            }

            $category->update(['status' => !$category->status]);

            $this->dispatch('show-toast', [
                'type' => 'success',
                'message' => 'Category status updated successfully!',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('show-toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function getCategoriesProperty()
    {
        $query = Category::query();

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        if ($this->filter_status) {
            if ($this->filter_status === 'active') {
                $query->where('status', true);
            } elseif ($this->filter_status === 'inactive') {
                $query->where('status', false);
            }
        }

        $sortField = in_array($this->sortField, ['name', 'created_at'], true)
            ? $this->sortField
            : 'id';

        return $query->orderBy($sortField, $this->sortDirection)->paginate($this->perPage);
    }

    public function render(): View
    {
        /** @var \Livewire\Features\SupportLayouts\View $view */
        $view = view('admin::Category.views.category-datatable', [
            'categories' => $this->categories,
        ]);

        return $view->layout('panel::layout.app', [
            'title' => 'Category Management',
            'breadcrumb' => [['Categories', route('admin.categories.index')]],
        ]);
    }
}


