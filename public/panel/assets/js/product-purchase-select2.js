/**
 * Product Purchase Module - Select2 Initialization
 * This file handles Select2 initialization and Livewire integration for the Product Purchase module
 */

(function() {
    'use strict';

    let isInitializing = false;
    let initializedFields = new Set();
    let morphUpdateTimeout = null;

    /**
     * Initialize Select2 for Product Purchase form
     * @param {Object} componentContext - Livewire component context
     */
    window.initProductPurchaseSelect2 = function(componentContext) {
        if (isInitializing) {
            return false;
        }

        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
            console.warn('Select2 library not loaded');
            return false;
        }

        const select2Fields = document.querySelectorAll('.product-purchase-form .select2-field');
        let initialized = false;

        select2Fields.forEach(function(select) {
            const fieldId = select.id || select.getAttribute('data-select2-field');
            
            if (!fieldId) {
                return;
            }

            // Skip if already initialized
            if (jQuery(select).hasClass('select2-hidden-accessible')) {
                if (!initializedFields.has(fieldId)) {
                    initializedFields.add(fieldId);
                }
                return;
            }

            // Check if element is visible
            const isVisible = select.offsetParent !== null || 
                             (select.closest('.modal') && select.closest('.modal').style.display !== 'none');
            
            if (!isVisible) {
                return;
            }

            initialized = true;

            const fieldName = jQuery(select).attr('data-select2-field') || select.id;
            const dataIndex = jQuery(select).attr('data-index');
            const fieldType = jQuery(select).attr('data-field-type');
            let currentValue = select.value;

            // Try to get value from Livewire component
            try {
                if (componentContext && typeof componentContext.get === 'function') {
                    // Handle nested array properties (e.g., purchaseItems.0.product_id)
                    if (fieldType === 'product' && dataIndex !== null && dataIndex !== undefined) {
                        const purchaseItems = componentContext.get('purchaseItems');
                        if (purchaseItems && purchaseItems[dataIndex] && purchaseItems[dataIndex].product_id) {
                            currentValue = String(purchaseItems[dataIndex].product_id);
                        }
                    } else {
                        const livewireValue = componentContext.get(fieldName);
                        if (livewireValue !== null && livewireValue !== undefined) {
                            currentValue = livewireValue;
                        }
                    }
                }
            } catch (e) {
                // Ignore errors
            }
            
            // Fallback to select value if no Livewire value found
            if (!currentValue && select.value) {
                currentValue = select.value;
            }

            try {
                // Destroy existing Select2 instance if any
                if (jQuery(select).data('select2')) {
                    jQuery(select).select2('destroy');
                }

                // Initialize Select2
                jQuery(select).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: function() {
                        return jQuery(select).find('option:first').text() || 'Select...';
                    },
                    dropdownParent: jQuery(select).closest('.modal, .card, body'),
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

                // Set current value
                if (currentValue) {
                    jQuery(select).val(currentValue);
                    select.value = currentValue;
                    jQuery(select).trigger('change.select2');
                } else {
                    jQuery(select).val(null);
                    select.value = '';
                    jQuery(select).trigger('change.select2');
                }

                // Handle disabled/readonly state
                if (select.hasAttribute('readonly') || select.disabled) {
                    jQuery(select).prop('disabled', true);
                    jQuery(select).select2('enable', false);
                }

                initializedFields.add(fieldId);

                // Handle Select2 change events for Livewire
                let isUpdating = false;
                const selectFieldName = fieldName;
                const selectDataIndex = dataIndex;
                const selectFieldType = fieldType;

                jQuery(select).off('change.select2-livewire').on('change.select2-livewire', function(e) {
                    if (isUpdating) return;

                    const $select = jQuery(this);
                    let newValue = $select.val();
                    const selectElement = $select[0];
                    
                    if (newValue === null || newValue === undefined) {
                        newValue = '';
                    }
                    
                    if (selectElement) {
                        selectElement.value = newValue || '';
                    }
                    
                    try {
                        if (componentContext) {
                            isUpdating = true;
                            
                            // Handle nested array properties (e.g., purchaseItems.0.product_id)
                            if (selectDataIndex !== null && selectDataIndex !== undefined && selectFieldType === 'product') {
                                const indexNum = parseInt(selectDataIndex);
                                const valueStr = newValue ? String(newValue) : '';
                                
                                // Use the full property path for nested arrays
                                const fullPath = `purchaseItems.${indexNum}.product_id`;
                                if (componentContext.set) {
                                    componentContext.set(fullPath, valueStr);
                                }
                            } else if (componentContext.set && selectFieldName) {
                                componentContext.set(selectFieldName, newValue);
                            }
                            
                            // Reset updating flag after a short delay
                            setTimeout(() => { 
                                isUpdating = false; 
                            }, 150);
                        }
                    } catch (e) {
                        console.error('Error updating Livewire property:', e);
                        isUpdating = false;
                    }
                });

                // Handle Select2 events
                jQuery(select).on('select2:select select2:unselect select2:clear', function(e) {
                    const $select = jQuery(this);
                    setTimeout(function() {
                        $select.trigger('change.select2-livewire');
                    }, 10);
                });

                // Auto-focus search field when dropdown opens
                jQuery(select).on('select2:open', function() {
                    setTimeout(function() {
                        jQuery('.select2-search__field').focus();
                    }, 100);
                });

            } catch (e) {
                console.error('Select2 initialization error:', e);
            }
        });

        return initialized;
    };

    /**
     * Setup Livewire hooks for Select2
     */
    window.setupProductPurchaseSelect2 = function(componentContext) {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.select2) {
            console.warn('jQuery or Select2 not loaded');
            return;
        }

        function debounceMorphUpdate() {
            if (morphUpdateTimeout) {
                clearTimeout(morphUpdateTimeout);
            }
            morphUpdateTimeout = setTimeout(function() {
                initializedFields.clear();
                window.initProductPurchaseSelect2(componentContext);
            }, 300);
        }

        // Initialize immediately
        setTimeout(function() {
            window.initProductPurchaseSelect2(componentContext);
        }, 100);

        // Setup Livewire hooks if available
        if (typeof Livewire !== 'undefined') {
            // Reinitialize after morph updates
            if (typeof Livewire.hook === 'function') {
                Livewire.hook('morph.updated', function() {
                    debounceMorphUpdate();
                });

                // Clean up before morph updates
                Livewire.hook('morph.updating', function() {
                    const select2Fields = document.querySelectorAll('.product-purchase-form .select2-field');
                    select2Fields.forEach(function(select) {
                        if (jQuery(select).data('select2')) {
                            const fieldId = select.id || select.getAttribute('data-select2-field');
                            if (fieldId) {
                                initializedFields.delete(fieldId);
                            }
                        }
                    });
                });
            }
        }

        // MutationObserver for dynamically added Select2 fields
        const observer = new MutationObserver(function(mutations) {
            let shouldReinit = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            const hasSelect2Field = node.querySelector && (
                                node.querySelector('.select2-field') || 
                                (node.classList && node.classList.contains('select2-field'))
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
                    window.initProductPurchaseSelect2(componentContext);
                }, 300);
            }
        });

        // Observe the form container
        const targetNode = document.querySelector('.product-purchase-form') || document.body;
        if (targetNode) {
            observer.observe(targetNode, {
                childList: true,
                subtree: true
            });
        }
    };

})();

