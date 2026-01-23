(function() {
    window.initSelect2Fields = function(componentContext) {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
            return;
        }

        const select2Fields = document.querySelectorAll('.select2-field');
        let initialized = false;

        select2Fields.forEach(function(select) {
            const fieldId = select.id || select.getAttribute('data-select2-field');
            
            if (!fieldId) {
                return;
            }

            if (!jQuery(select).hasClass('select2-hidden-accessible') && select.offsetParent) {
                initialized = true;

                const fieldName = jQuery(select).attr('data-select2-field') || select.id;
                let currentValue = select.value;
                
                try {
                    if (componentContext && typeof componentContext.get === 'function') {
                        const livewireValue = componentContext.get(fieldName);
                        if (livewireValue !== null && livewireValue !== undefined) {
                            currentValue = livewireValue;
                        }
                    }
                } catch (e) {
                }
                
                try {
                    if (jQuery(select).data('select2')) {
                        jQuery(select).select2('destroy');
                    }
                    
                    jQuery(select).select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: function() {
                            return jQuery(select).find('option:first').text();
                        },
                        dropdownParent: jQuery(select).closest('.modal, body'),
                        allowClear: false,
                        minimumResultsForSearch: 0,
                        language: {
                            noResults: function() {
                                return "No results found";
                            },
                            searching: function() {
                                return "Searching...";
                            }
                        }
                    });
                } catch (e) {
                    console.error('Select2 initialization error:', e);
                }

                if (currentValue) {
                    jQuery(select).val(currentValue).trigger('change.select2');
                }

                let isUpdating = false;
                const selectFieldName = fieldName;
                
                jQuery(select).off('change.select2-livewire').on('change.select2-livewire', function(e) {
                    if (isUpdating) return;
                    
                    const newValue = jQuery(this).val();
                    try {
                        if (componentContext && typeof componentContext.set === 'function') {
                            isUpdating = true;
                            componentContext.set(selectFieldName, newValue);
                            setTimeout(() => { isUpdating = false; }, 100);
                        }
                    } catch (e) {
                        console.error('Error updating Livewire property:', e);
                        isUpdating = false;
                    }
                });
                
                jQuery(select).on('select2:open', function() {
                    setTimeout(function() {
                        jQuery('.select2-search__field').focus();
                    }, 100);
                });
            }
        });

        return initialized;
    };

    window.setupSelect2Observer = function(componentContext) {
        let observer = null;
        let morphUpdateTimeout = null;
        let initializedFields = new Set();

        function debounceMorphUpdate() {
            if (morphUpdateTimeout) {
                clearTimeout(morphUpdateTimeout);
            }
            morphUpdateTimeout = setTimeout(function() {
                initializedFields.clear();
                window.initSelect2Fields(componentContext);
            }, 500);
        }

        function setupObserver() {
            if (observer) {
                observer.disconnect();
            }

            observer = new MutationObserver(function(mutations) {
                let shouldReinit = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) {
                                const hasSelect2Field = node.querySelector && (
                                    node.querySelector('.select2-field') || 
                                    node.classList && node.classList.contains('select2-field')
                                );
                                if (hasSelect2Field) {
                                    shouldReinit = true;
                                }
                            }
                        });
                    }
                });

                if (shouldReinit) {
                    setTimeout(function() {
                        window.initSelect2Fields(componentContext);
                    }, 300);
                }
            });

            const targetNode = document.querySelector('[x-slot="formContent"]') || document.body;
            if (targetNode) {
                observer.observe(targetNode, {
                    childList: true,
                    subtree: true
                });
            }
        }

        if (typeof Livewire !== 'undefined') {
            document.addEventListener('livewire:init', function() {
                Livewire.hook('morph.updated', function() {
                    debounceMorphUpdate();
                });
                
                Livewire.hook('morph.updating', function() {
                    const select2Fields = document.querySelectorAll('.select2-field');
                    select2Fields.forEach(function(select) {
                        if (jQuery(select).data('select2')) {
                            const fieldId = select.id || select.getAttribute('data-select2-field');
                            if (fieldId) {
                                initializedFields.delete(fieldId);
                            }
                        }
                    });
                });
            }, { passive: true });
            
            document.addEventListener('livewire:update', function() {
                setTimeout(function() {
                    const formVisible = document.querySelector('[x-slot="formContent"]');
                    if (formVisible && formVisible.offsetParent !== null) {
                        initializedFields.clear();
                        window.initSelect2Fields(componentContext);
                    }
                }, 300);
            }, { passive: true });
            
            document.addEventListener('livewire:navigated', function() {
                initializedFields.clear();
                setTimeout(function() {
                    window.initSelect2Fields(componentContext);
                }, 300);
            }, { passive: true });
        }

        setupObserver();
    };
})();

