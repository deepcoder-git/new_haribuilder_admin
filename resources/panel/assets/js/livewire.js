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

    // Show loader when Livewire request starts (with small delay to prevent flash on quick requests)
    Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
        requestCount++;
        
        // Clear any pending hide timeout
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
            loadingTimeout = null;
        }
        
        // Show loader after 150ms delay (prevents flash on quick requests)
        loadingTimeout = setTimeout(() => {
            if (requestCount > 0) {
                showLivewireLoader();
            }
        }, 150);

        // Handle successful response
        succeed(({ status, json }) => {
            requestCount = Math.max(0, requestCount - 1);
            if (requestCount === 0) {
                if (loadingTimeout) {
                    clearTimeout(loadingTimeout);
                    loadingTimeout = null;
                }
                hideLivewireLoader();
            }
        });

        // Handle failed response
        fail(({ status, content, preventDefault }) => {
            requestCount = Math.max(0, requestCount - 1);
            if (requestCount === 0) {
                if (loadingTimeout) {
                    clearTimeout(loadingTimeout);
                    loadingTimeout = null;
                }
                hideLivewireLoader();
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
