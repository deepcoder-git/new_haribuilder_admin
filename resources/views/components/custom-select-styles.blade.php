<style>
    /* Custom Searchable Select Styling */
    .custom-searchable-select {
        position: relative;
        width: 100%;
    }
    
    .select-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        height: 44px;
        padding: 0.5rem 1rem;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.875rem;
        color: #374151;
    }
    
    .select-trigger:hover {
        border-color: #1e3a8a;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
    }
    
    .select-trigger.active {
        border-color: #1e3a8a;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
    }
    
    .select-trigger i {
        color: #1e3a8a;
        font-size: 0.75rem;
        transition: transform 0.2s ease;
    }
    
    .select-trigger i.rotate {
        transform: rotate(180deg);
    }
    
    .select-dropdown {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        right: 0;
        background-color: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        z-index: 1000;
        overflow: hidden;
    }
    
    .select-search {
        position: relative;
        padding: 0.75rem;
        border-bottom: 1px solid #e5e7eb;
        background-color: #f9fafb;
    }
    
    .select-search i {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        font-size: 0.875rem;
        pointer-events: none;
    }
    
    .select-search-input {
        width: 100%;
        padding: 0.5rem 0.75rem 0.5rem 2.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: #374151;
        background-color: #ffffff;
        outline: none;
        transition: all 0.2s ease;
    }
    
    .select-search-input:focus {
        border-color: #1e3a8a;
        box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
    }
    
    .select-options {
        max-height: 200px;
        overflow-y: auto;
        padding: 0.25rem 0;
    }
    
    .select-options::-webkit-scrollbar {
        width: 6px;
    }
    
    .select-options::-webkit-scrollbar-track {
        background: #f3f4f6;
    }
    
    .select-options::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }
    
    .select-options::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
    
    .select-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: all 0.15s ease;
        font-size: 0.875rem;
        color: #374151;
    }
    
    .select-option:hover {
        background-color: #f3f4f6;
    }
    
    .select-option.selected {
        background-color: #eff6ff;
        color: #1e3a8a;
        font-weight: 500;
    }
    
    .select-option i {
        color: #1e3a8a;
        font-size: 0.75rem;
    }
    
    .select-no-results {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        color: #9ca3af;
        font-size: 0.875rem;
    }
    
    .select-no-results i {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        opacity: 0.5;
    }
    
    [x-cloak] {
        display: none !important;
    }
</style>

<script>
    function customSelect(config) {
        return {
            open: false,
            searchQuery: '',
            options: config.options || [],
            filteredOptions: config.options || [],
            selectedValue: config.selectedValue || null,
            placeholder: config.placeholder || 'Select...',
            selectedText: '',
            instanceId: config.instanceId || Math.random().toString(36).substring(2),
            
            init() {
                this.updateSelectedText();
                this.filteredOptions = [...this.options];
                
                // Watch for external changes to selectedValue
                this.$watch('selectedValue', (value) => {
                    this.updateSelectedText();
                });

                // Listen for other custom selects opening and close this one
                window.addEventListener('custom-select-open', (event) => {
                    if (!event.detail || event.detail.id === this.instanceId) {
                        return;
                    }
                    this.open = false;
                });
            },
            
            toggle() {
                if (!this.open) {
                    // Notify all other instances so they can close
                    window.dispatchEvent(new CustomEvent('custom-select-open', {
                        detail: { id: this.instanceId }
                    }));
                    this.open = true;
                } else {
                    this.open = false;
                }
            },
            
            updateSelectedText() {
                const selected = this.options.find(opt => opt.id == this.selectedValue);
                this.selectedText = selected ? selected.name : '';
            },
            
            filterOptions() {
                const query = this.searchQuery.toLowerCase().trim();
                if (!query) {
                    this.filteredOptions = [...this.options];
                    return;
                }
                
                this.filteredOptions = this.options.filter(option => 
                    option.name.toLowerCase().includes(query)
                );
            },
            
            selectOption(option) {
                this.selectedValue = option.id;
                this.updateSelectedText();
                this.open = false;
                this.searchQuery = '';
                this.filterOptions();
            }
        }
    }
</script>

