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
            let currentValue = select.value;

            // Try to get value from Livewire component
            try {
                if (componentContext && typeof componentContext.get === 'function') {
                    const livewireValue = componentContext.get(fieldName);
                    if (livewireValue !== null && livewireValue !== undefined) {
                        currentValue = livewireValue;
                    }
                }
            } catch (e) {
                // Ignore errors
            }

            try {
                // Destroy existing Select2 instance if any
                if (jQuery(select).data('select2')) {
                    jQuery(select).select2('destroy');
                }

                // Determine dropdown parent - use body for table rows to prevent clipping
                let dropdownParent = jQuery('body');
                const closestModal = jQuery(select).closest('.modal');
                const closestCard = jQuery(select).closest('.card');
                const isInTable = jQuery(select).closest('table').length > 0;
                
                // For table rows, always use body to prevent overflow clipping
                if (isInTable) {
                    dropdownParent = jQuery('body');
                } else if (closestModal.length) {
                    dropdownParent = closestModal;
                } else if (closestCard.length) {
                    dropdownParent = closestCard;
                }

                // Initialize Select2
                jQuery(select).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: function() {
                        return jQuery(select).find('option:first').text() || 'Select...';
                    },
                    dropdownParent: dropdownParent,
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

                jQuery(select).off('change.select2-livewire').on('change.select2-livewire', function(e) {
                    if (isUpdating) return;

                    const newValue = jQuery(this).val();
                    
                    try {
                        if (componentContext && typeof componentContext.set === 'function') {
                            isUpdating = true;
                            componentContext.set(selectFieldName, newValue);
                            
                            // Reset updating flag after a short delay
                            setTimeout(() => { 
                                isUpdating = false; 
                            }, 100);
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
        if (typeof Livewire === 'undefined') {
            console.warn('Livewire not loaded');
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.initProductPurchaseSelect2(componentContext);
            }, 100);
        });

        // Livewire hooks
        document.addEventListener('livewire:init', function() {
            // Initialize after Livewire is ready
            setTimeout(function() {
                window.initProductPurchaseSelect2(componentContext);
            }, 200);

            // Reinitialize after morph updates
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
        }, { passive: true });

        // Reinitialize after Livewire updates
        document.addEventListener('livewire:update', function() {
            setTimeout(function() {
                const formVisible = document.querySelector('.product-purchase-form');
                if (formVisible && formVisible.offsetParent !== null) {
                    initializedFields.clear();
                    window.initProductPurchaseSelect2(componentContext);
                }
            }, 300);
        }, { passive: true });

        // Reinitialize after navigation
        document.addEventListener('livewire:navigated', function() {
            initializedFields.clear();
            setTimeout(function() {
                window.initProductPurchaseSelect2(componentContext);
            }, 300);
        }, { passive: true });

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

    // Auto-initialize when script loads (for non-Livewire contexts)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.initProductPurchaseSelect2();
            }, 100);
        });
    } else {
        setTimeout(function() {
            window.initProductPurchaseSelect2();
        }, 100);
    }

})();

