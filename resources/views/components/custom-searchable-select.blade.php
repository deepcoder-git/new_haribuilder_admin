@props([
    'options' => [],
    'selectedValue' => null,
    'placeholder' => 'Select...',
    'wireModel' => null,
    'label' => null,
])

@php
    // Convert options to array format if needed
    $optionsArray = is_array($options) ? $options : $options->map(fn($item) => [
        'id' => is_object($item) ? $item->id : $item['id'] ?? $item,
        'name' => is_object($item) ? $item->name : $item['name'] ?? $item
    ])->toArray();
@endphp

<div class="custom-searchable-select-wrapper">
    @if($label)
        <label class="form-label fw-bold text-gray-700 mb-2 d-block">
            {{ $label }}
        </label>
    @endif
    
    <div class="custom-searchable-select" 
         x-data="customSelect({
             options: @js($optionsArray),
             selectedValue: @if($wireModel) @entangle($wireModel) @else '{{ $selectedValue }}' @endif,
             placeholder: '{{ $placeholder }}',
             // Unique ID per instance to coordinate open/close across all filters
             instanceId: '{{ uniqid('custom_select_', true) }}'
         })"
         @click.stop>
        <div class="select-trigger" 
             @click="toggle()"
             :class="{ 'active': open }">
            <span x-text="selectedText || placeholder"></span>
            <i class="fa-solid fa-chevron-down" 
               :class="{ 'rotate': open }"
               style="transition: transform 0.2s ease;"></i>
        </div>
        <div class="select-dropdown" 
             x-show="open"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.away="open = false"
             x-cloak>
            <div class="select-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" 
                       x-model="searchQuery"
                       @input="filterOptions()"
                       placeholder="Search..."
                       class="select-search-input">
            </div>
            <div class="select-options" x-ref="optionsContainer">
                <template x-for="(option, index) in filteredOptions" :key="option.id">
                    <div class="select-option"
                         :class="{ 'selected': selectedValue == option.id }"
                         @click="selectOption(option)"
                         x-show="option.visible !== false">
                        <span x-text="option.name"></span>
                        <i class="fa-solid fa-check" x-show="selectedValue == option.id"></i>
                    </div>
                </template>
                <div class="select-no-results" x-show="filteredOptions.length === 0">
                    <i class="fa-solid fa-inbox"></i>
                    <span>No results found</span>
                </div>
            </div>
        </div>
    </div>
</div>

