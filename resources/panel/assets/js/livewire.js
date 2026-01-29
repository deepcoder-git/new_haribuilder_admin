document.addEventListener("livewire:initialized", () => {
    const livewireOverlay = document.getElementById('livewire-loading-overlay');
    let requestCount = 0;
    let loadingTimeout = null;

    const showLivewireLoader = () => {
        if (livewireOverlay) {
            livewireOverlay.classList.add('show');
        }
    };

    const hideLivewireLoader = () => {
        if (livewireOverlay) {
            livewireOverlay.classList.remove('show');
        }
    };

    /**
     * Determine if a Livewire request is a "background" interaction
     * (typing in inputs / simple field syncing) where we don't want
     * to block the whole page with the global loader.
     */
    const isBackgroundInteraction = (payload) => {
        if (!payload) return false;

        // Livewire v3: input updates are sent via `updates` with type `syncInput`
        if (Array.isArray(payload.updates) && payload.updates.length > 0) {
            const onlySyncInputs = payload.updates.every(update => update.type === 'syncInput');
            if (onlySyncInputs) {
                return true;
            }
        }

        // Fallback: if there is an actionQueue, assume it's a method call (button click, save, etc.)
        // and we DO want the loader.
        return false;
    };

    // Show loader only for non-background Livewire requests
    Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
        const background = isBackgroundInteraction(payload);

        if (!background) {
            requestCount++;

            // Clear any pending hide timeout
            if (loadingTimeout) {
                clearTimeout(loadingTimeout);
                loadingTimeout = null;
            }

            // Show loader after a slightly longer delay to avoid flashing
            // on quick interactions (especially dropdown changes).
            const delay = 400;
            loadingTimeout = setTimeout(() => {
                if (requestCount > 0) {
                    showLivewireLoader();
                }
            }, delay);
        }

        // Handle successful response
        succeed(({ status, json }) => {
            if (!background) {
                requestCount = Math.max(0, requestCount - 1);
                if (requestCount === 0) {
                    if (loadingTimeout) {
                        clearTimeout(loadingTimeout);
                        loadingTimeout = null;
                    }
                    hideLivewireLoader();
                }
            }
        });

        // Handle failed response
        fail(({ status, content, preventDefault }) => {
            if (!background) {
                requestCount = Math.max(0, requestCount - 1);
                if (requestCount === 0) {
                    if (loadingTimeout) {
                        clearTimeout(loadingTimeout);
                        loadingTimeout = null;
                    }
                    hideLivewireLoader();
                }
            }
            
            if (!content.includes("<script")) {
                try {
                    const errorContent = JSON.parse(content);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(errorContent.message || 'An error occurred');
                    } else {
                        alert(errorContent.message || 'An error occurred');
                    }
                } catch (e) {
                    if (typeof toastr !== 'undefined') {
                        toastr.error('An unexpected error occurred');
                    } else {
                        alert('An unexpected error occurred');
                    }
                }
                preventDefault();
            }
        });
    });

    // Listen for toast notifications (Livewire 3)
    Livewire.on('show-toast', (data) => {
        let eventData;
        if (Array.isArray(data)) {
            eventData = data[0] || data;
        } else if (typeof data === 'object' && data !== null) {
            eventData = data;
        } else {
            return;
        }
        
        const type = eventData?.type || 'info';
        const message = eventData?.message || 'Notification';
        
        if (typeof toastr === 'undefined') {
            alert(message);
            return;
        }
        
        switch(type) {
            case 'success':
                toastr.success(message);
                break;
            case 'error':
                toastr.error(message);
                break;
            case 'warning':
                toastr.warning(message);
                break;
            case 'info':
            default:
                toastr.info(message);
                break;
        }
    });
});
