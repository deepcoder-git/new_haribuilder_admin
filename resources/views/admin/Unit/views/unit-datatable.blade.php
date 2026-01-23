<div>
    <!-- Delete Confirmation Modal -->
    <x-delete-confirm-modal 
        :itemNameProperty="'unitNameToDelete'"
        :showModalProperty="'showDeleteModal'"
        deleteMethod="delete"
        closeMethod="closeDeleteModal"
        title="Delete Unit"
    />

    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-white px-3 py-2">
            <div class="card-title w-100 mb-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-nowrap w-100">
                    <div class="d-flex align-items-center position-relative" style="min-width: 280px; max-width: 400px; flex: 0 0 auto;">
                        <i class="fa-solid fa-magnifying-glass position-absolute" style="left: 1rem; top: 50%; transform: translateY(-50%); color: #6b7280; z-index: 10; pointer-events: none;"></i>
                        <input type="text" 
                               wire:model.live.debounce.300ms="search"
                               class="form-control form-control-solid" 
                               placeholder="Search units by name..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <div class="dropdown" style="flex-shrink: 0;">
                            <button class="btn dropdown-toggle d-flex align-items-center px-3 fw-semibold {{ $this->hasActiveFilters() ? 'btn-danger' : 'btn-light' }}" 
                                    type="button" 
                                    id="filterDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="height: 44px; border-radius: 0.5rem; {{ $this->hasActiveFilters() ? 'border: 1px solid #dc3545; color: #fff;' : 'border: 1px solid #e5e7eb; white-space: nowrap;' }}">
                                <i class="fa-solid fa-filter me-2"></i>
                                Filter
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" 
                                aria-labelledby="filterDropdown" 
                                style="min-width: 300px; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;">
                                <li class="mb-3">
                                    <label class="form-label fw-bold text-gray-700 mb-2 d-block">
                                        Status
                                    </label>
                                    <select id="statusFilterSelect"
                                            wire:model="tempStatusFilter"
                                            class="form-select form-select-solid"
                                            style="border-radius: 0.5rem; border: 1px solid #e5e7eb; height: 44px;">
                                        <option value="all">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </li>
                                <li>
                                    <div class="d-flex gap-2">
                                        <button type="button"
                                                wire:click="applyFilters"
                                                class="btn btn-primary flex-fill"
                                                style="border-radius: 0.5rem; height: 44px; background: #1e3a8a; border: none;">
                                            Apply
                                        </button>
                                        <button type="button"
                                                wire:click="resetFilters"
                                                class="btn btn-light flex-fill"
                                                style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb;">
                                            Reset
                                        </button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <button type="button" 
                                wire:click="openCreateForm"
                                class="btn btn-primary d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #1e3a8a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-plus me-2"></i>
                            Add New Unit
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed mb-0">
                    <thead>
                        <tr class="fw-bold text-uppercase">
                            <th>DATE</th>
                            <th class="cursor-pointer" wire:click="sortBy('name')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>NAME</span>
                                    @if($sortField === 'name')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>NO. OF PRODUCTS</th>
                            <th>NO. OF MATERIALS</th>
                            <th>STATUS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($units as $unit)
                        <tr>
                            <td>
                                <span class="text-gray-700">
                                    {{ $unit->created_at ? $unit->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-800 fw-semibold">{{ $unit->name }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $unit->product_count ?? 0 }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $unit->material_count ?? 0 }}</span>
                            </td>
                            <td>
                                <div class="form-check form-switch form-check-custom form-check-solid d-inline-flex">
                                    @php
                                        $hasProducts = \App\Models\Product::where('unit_type', $unit->name)->exists();
                                    @endphp
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           wire:change="toggleStatus({{ $unit->id }})"
                                           @if($unit->status) checked @endif
                                           @if($hasProducts) disabled @endif
                                           style="cursor: {{ $hasProducts ? 'not-allowed' : 'pointer' }}; width: 40px; height: 20px; opacity: {{ $hasProducts ? '0.6' : '1' }};"
                                           title="{{ $hasProducts ? 'Cannot change status: Unit is assigned to products' : 'Toggle status' }}"
                                           wire:loading.attr="disabled">
                                    <span wire:loading wire:target="toggleStatus({{ $unit->id }})" class="spinner-border spinner-border-sm ms-2"></span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="#" 
                                       wire:click.prevent="openViewModal({{ $unit->id }})"
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Details"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="openEditForm({{ $unit->id }})"
                                       class="btn btn-sm btn-icon btn-light-info"
                                       title="Edit Unit"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="confirmDelete({{ $unit->id }})"
                                       class="btn btn-sm btn-icon btn-light-danger"
                                       title="Delete Unit"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No units found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-datatable-pagination :items="$units" />
        </div>
    </div>

    <x-datatable-styles />
</div>

