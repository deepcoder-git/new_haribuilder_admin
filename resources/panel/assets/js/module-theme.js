(function() {
    'use strict';

    const DEFAULT_THEME_COLOR = '#1e3a8a';

    function adjustBrightness(hex, percent) {
        const num = parseInt(hex.replace('#', ''), 16);
        const r = Math.min(255, Math.max(0, (num >> 16) + percent));
        const g = Math.min(255, Math.max(0, ((num >> 8) & 0x00FF) + percent));
        const b = Math.min(255, Math.max(0, (num & 0x0000FF) + percent));
        return '#' + ((r << 16) | (g << 8) | b).toString(16).padStart(6, '0');
    }

    function applyDefaultTheme() {
        const color = DEFAULT_THEME_COLOR;

        document.documentElement.style.setProperty('--module-theme-color', color);
        
        const sidebar = document.getElementById('kt_app_sidebar');
        const sidebarLogo = document.getElementById('kt_app_sidebar_logo');
        
        if (sidebar) {
            sidebar.style.setProperty('background', color, 'important');
        }
        
        if (sidebarLogo) {
            sidebarLogo.style.setProperty('background', color, 'important');
        }

        const styleId = 'module-theme-dynamic-styles';
        let styleElement = document.getElementById(styleId);
        
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = styleId;
            document.head.appendChild(styleElement);
        }

        const darkerColor = adjustBrightness(color, -20);
        const lighterColor = adjustBrightness(color, 20);

        styleElement.textContent = `
            :root {
                --module-theme-color: ${color};
                --module-theme-color-darker: ${darkerColor};
                --module-theme-color-lighter: ${lighterColor};
            }
            
            #kt_app_sidebar {
                background: ${color} !important;
            }
            
            #kt_app_sidebar_logo {
                background: ${color} !important;
            }
            
            .btn-primary {
                background-color: ${color} !important;
                border-color: ${color} !important;
            }
            
            .btn-primary:hover,
            .btn-primary:focus,
            .btn-primary:active,
            .btn-primary.active {
                background-color: ${darkerColor} !important;
                border-color: ${darkerColor} !important;
            }
            
            .table thead th {
                border-bottom-color: ${color} !important;
                color: ${color} !important;
            }
            
            .card-title h1,
            .card-title h2,
            .card-title h3,
            .card-title h4,
            .card-title h5,
            .card-title h6 {
                color: ${color} !important;
            }
            
            .link-primary,
            a.text-primary {
                color: ${color} !important;
            }
            
            .badge-primary,
            .badge.bg-primary {
                background-color: ${color} !important;
            }
            
            .alert-primary {
                background-color: ${adjustBrightness(color, 80)} !important;
                border-color: ${color} !important;
                color: ${color} !important;
            }
            
            .select2-container--default .select2-selection--single:focus,
            .select2-container--default.select2-container--focus .select2-selection--single {
                border-color: ${color} !important;
            }
            
            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: ${color} !important;
                color: #ffffff !important;
            }
            
            .select2-container--default .select2-results__option[aria-selected=true] {
                background-color: ${color} !important;
                color: #ffffff !important;
            }
            
            .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
                border-color: transparent transparent ${color} transparent !important;
            }
            
            .select2-dropdown {
                border-color: ${color} !important;
            }
            
            .select2-container--default .select2-search--dropdown .select2-search__field:focus {
                border-color: ${color} !important;
            }
            
            select.form-select:focus,
            select.form-select-solid:focus,
            select:focus {
                border-color: ${color} !important;
                box-shadow: 0 0 0 3px ${adjustBrightness(color, 80)} !important;
            }
            
            select.form-select:hover,
            select.form-select-solid:hover,
            select:hover {
                border-color: ${color} !important;
            }
        `;
    }

    function initializeTheme() {
        applyDefaultTheme();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTheme);
    } else {
        initializeTheme();
    }
})();
