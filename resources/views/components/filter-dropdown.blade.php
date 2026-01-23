@props([
    'filters' => [], // Array of filter configurations
    'hasActiveFilters' => false,
    'applyMethod' => 'applyFilters',
    'resetMethod' => 'resetFilters',
])

<div class="dropdown" style="flex-shrink: 0;" data-bs-auto-close="outside">
    <button class="btn dropdown-toggle d-flex align-items-center px-3 fw-semibold {{ $hasActiveFilters ? 'btn-danger' : 'btn-light' }}" 
            type="button" 
            id="filterDropdown" 
            data-bs-toggle="dropdown" 
            data-bs-auto-close="outside"
            aria-expanded="false"
            style="height: 44px; border-radius: 0.5rem; {{ $hasActiveFilters ? 'border: 1px solid #dc3545; color: #fff;' : 'border: 1px solid #e5e7eb; white-space: nowrap;' }}">
        <i class="fa-solid fa-filter me-2"></i>
        Filter
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm" 
        aria-labelledby="filterDropdown" 
        style="min-width: 300px; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem;"
        @click.stop>
        @foreach($filters as $filter)
            <li class="mb-3">
                <x-custom-searchable-select
                    :options="$filter['options'] ?? []"
                    :wireModel="$filter['wireModel'] ?? null"
                    :placeholder="$filter['placeholder'] ?? 'Select...'"
                    :label="$filter['label'] ?? null"
                />
            </li>
        @endforeach
        <li>
            <div class="d-flex gap-2">
                <button type="button"
                        wire:click="{{ $applyMethod }}"
                        class="btn btn-primary flex-fill"
                        style="border-radius: 0.5rem; height: 44px; background: #1e3a8a; border: none;">
                    Apply
                </button>
                <button type="button"
                        wire:click="{{ $resetMethod }}"
                        wire:loading.attr="disabled"
                        @click.stop
                        class="btn btn-light flex-fill"
                        style="border-radius: 0.5rem; height: 44px; border: 1px solid #e5e7eb;">
                    <span wire:loading.remove wire:target="{{ $resetMethod }}">Reset</span>
                    <span wire:loading wire:target="{{ $resetMethod }}" class="spinner-border spinner-border-sm"></span>
                </button>
            </div>
        </li>
    </ul>
</div>

