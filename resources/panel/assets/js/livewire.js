document.addEventListener("livewire:initialized", () => {
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
