<div>
    <!-- Delete Confirmation Modal -->
    <x-delete-confirm-modal 
        :itemNameProperty="'wastageNameToDelete'"
        :showModalProperty="'showDeleteModal'"
        deleteMethod="delete"
        closeMethod="closeDeleteModal"
        title="Delete Wastage"
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
                               placeholder="Search wastages by manager, site, product..."
                               style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb; padding-left: 3rem; padding-right: 1rem; transition: all 0.2s; width: 100%;">
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex-shrink: 0; margin-left: auto;">
                        <button type="button" 
                                wire:click="openCreateForm"
                                class="btn btn-primary d-flex align-items-center px-4 fw-semibold"
                                style="height: 44px; border-radius: 0.5rem; background: #1e3a8a; border: none; white-space: nowrap;">
                            <i class="fa-solid fa-plus me-2"></i>
                            Add New Wastage
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
                            <th>ID</th>
                            <th class="cursor-pointer" wire:click="sortBy('type')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>TYPE</span>
                                    @if($sortField === 'type')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>MANAGER</th>
                            <th>SITE</th>
                            <th class="cursor-pointer" wire:click="sortBy('date')">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <span>DATE</span>
                                    @if($sortField === 'date')
                                        <i class="fa-solid fa-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary" style="font-size: 0.75rem;"></i>
                                    @else
                                        <i class="fa-solid fa-arrows-up-down text-muted opacity-50" style="font-size: 0.75rem;"></i>
                                    @endif
                                </div>
                            </th>
                            <th>PRODUCTS</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($wastages as $wastage)
                        <tr>
                            <td>
                                <span class="text-gray-700">
                                    {{ $wastage->created_at ? $wastage->created_at->format('d/m/Y') : 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-800 fw-semibold">#{{ $wastage->id }}</span>
                            </td>
                            <td>
                                {!! $this->renderType($wastage) !!}
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $wastage->manager->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">{{ $wastage->site->name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {{ $wastage->date ? $wastage->date->format('d-m-Y') : '-' }}
                                </span>
                            </td>
                            <td>
                                <span class="text-gray-700">
                                    {!! $this->renderProducts($wastage) !!}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="#" 
                                       wire:click.prevent="openViewModal({{ $wastage->id }})"
                                       class="btn btn-sm btn-icon btn-light-primary"
                                       title="View Details"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-eye" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="openEditForm({{ $wastage->id }})"
                                       class="btn btn-sm btn-icon btn-light-info"
                                       title="Edit Wastage"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-pen" style="font-size: 0.875rem;"></i>
                                    </a>
                                    <a href="#" 
                                       wire:click.prevent="confirmDelete({{ $wastage->id }})"
                                       class="btn btn-sm btn-icon btn-light-danger"
                                       title="Delete Wastage"
                                       style="width: 32px; height: 32px; border-radius: 0.5rem; padding: 0; display: flex; align-items: center; justify-content: center;"
                                       wire:loading.attr="disabled">
                                        <i class="fa-solid fa-trash" style="font-size: 0.875rem;"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-12">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="symbol symbol-circle symbol-100px mb-4">
                                        <div class="symbol-label bg-light">
                                            <i class="fa-solid fa-inbox fs-2x text-gray-400"></i>
                                        </div>
                                    </div>
                                    <div class="text-gray-600 fw-semibold fs-5 mb-2">No wastages found</div>
                                    <div class="text-gray-500 fs-6">Try adjusting your search or filters</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-datatable-pagination :items="$wastages" />
        </div>
    </div>

    <x-datatable-styles />
</div>

