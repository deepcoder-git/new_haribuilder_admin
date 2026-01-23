<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ $title?(ucwords($title).' | '.config('app.name')):config('app.name') }}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ mix('build/panel/images/logo/favicon.svg') }}" />
    <link rel="icon" type="image/x-icon" href="{{ mix('build/panel/images/logo/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('build/favicon_io/apple-touch-icon.png') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('build/favicon_io/favicon-32x32.png') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('build/favicon_io/favicon-16x16.png') }}" />
    <link rel="manifest" href="{{ asset('build/favicon_io/site.webmanifest') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{ mix('build/panel/vendors/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ mix('build/panel/vendors/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="{{ mix('build/panel/css/common.css') }}">
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Custom Toastr Success Theme Color */
        #toast-container > .toast-success {
            background-color: #1e3a8a !important;
            border-color: #1e3a8a !important;
        }
        #toast-container > .toast-success:hover {
            background-color: #1e40af !important;
            border-color: #1e40af !important;
        }
        #toast-container > .toast-success .toast-progress {
            background-color: #3b82f6 !important;
        }
        #toast-container > .toast-success .toast-close-button {
            color: #ffffff !important;
            opacity: 0.8;
        }
        #toast-container > .toast-success .toast-close-button:hover {
            opacity: 1;
        }
        #toast-container > .toast-success .toast-title {
            color: #ffffff !important;
            font-weight: 600;
        }
        #toast-container > .toast-success .toast-message {
            color: #ffffff !important;
        }
        
        /* Custom Toastr Error/Delete Red Color */
        #toast-container > .toast-error {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        #toast-container > .toast-error:hover {
            background-color: #c82333 !important;
            border-color: #c82333 !important;
        }
        #toast-container > .toast-error .toast-progress {
            background-color: #e4606d !important;
        }
        #toast-container > .toast-error .toast-close-button {
            color: #ffffff !important;
            opacity: 0.8;
        }
        #toast-container > .toast-error .toast-close-button:hover {
            opacity: 1;
        }
        #toast-container > .toast-error .toast-title {
            color: #ffffff !important;
            font-weight: 600;
        }
        #toast-container > .toast-error .toast-message {
            color: #ffffff !important;
        }
    </style>
    @stack('header')
    @livewireStyles
</head>
<body id="kt_app_body" data-kt-app-page-loading-enabled="false" data-kt-app-page-loading="off"
      data-kt-app-header-fixed="true"
      data-kt-sticky-app-header-minimize="off"
      data-kt-app-layout="dark-sidebar" data-kt-app-header-fixed="true" data-kt-app-sidebar-enabled="true"
      data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-hoverable="true" data-kt-app-sidebar-push-header="true"
      data-kt-app-header-minimize="on"
      data-kt-app-sidebar-push-toolbar="true" data-kt-app-sidebar-push-footer="true" class="app-default">
<script src="{{ mix('build/panel/js/theme-mode.js') }}"></script>
@include('panel::partials._page-loader')
<div id="livewire-loading-overlay" class="livewire-loading-overlay">
    <div class="livewire-loading-spinner"></div>
</div>
<div class="d-flex flex-column flex-root app-root" id="kt_app_root">
    <div class="app-page  flex-column flex-column-fluid" id="kt_app_page">
        @include('panel::partials._header')
        <div class="app-wrapper  flex-column flex-row-fluid" id="kt_app_wrapper">
            @include('panel::partials.sidebar.main')
            <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                <div class="d-flex flex-column flex-column-fluid">
                    <div id="kt_app_content" class="app-content flex-column-fluid">
                        <div id="kt_app_content_container" class="app-container container-fluid">{{ $slot }}</div>
                    </div>
                </div>
                @include('panel::partials._footer')
            </div>
        </div>
    </div>
</div>
@include('panel::partials._scrolltop')
<script src="{{ mix('build/panel/vendors/plugins.bundle.js') }}"></script>
<script src="{{ mix('build/panel/vendors/scripts.bundle.js') }}"></script>
{{-- <script>
    // System Loader Management
    const SystemLoader = {
        loader: null,
        livewireOverlay: null,
        
        init() {
            if (!this.loader) {
                this.loader = document.getElementById('system-loader');
            }
            if (!this.livewireOverlay) {
                this.livewireOverlay = document.getElementById('livewire-loading-overlay');
            }
        },
        
        
        hidePageLoader() {
            if (this.loader) {
                this.loader.classList.add('hide');
            }
        },
        
        showLivewireLoader() {
            if (this.livewireOverlay) {
                this.livewireOverlay.classList.add('show');
            }
        },
        
        hideLivewireLoader() {
            if (this.livewireOverlay) {
                this.livewireOverlay.classList.remove('show');
            }
        }
    };

    // Hide page loader when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        SystemLoader.init();
        // Slight delay to show the loader animation
        setTimeout(() => {
            SystemLoader.hidePageLoader();
        }, 300);
        
        if (typeof KTApp !== 'undefined' && KTApp.hidePageLoading) {
            KTApp.hidePageLoading();
        }
    });

    // Also hide on window load as fallback
    window.addEventListener('load', function() {
        SystemLoader.init();
        SystemLoader.hidePageLoader();
        
        if (typeof KTApp !== 'undefined' && KTApp.hidePageLoading) {
            KTApp.hidePageLoading();
        }
    });
</script> --}}
<script src="{{ mix('build/panel/js/module-theme.js') }}"></script>
<script src="{{ mix('build/panel/js/select2-init.js') }}"></script>
@livewireScripts
<!-- Toastr JS - Load after Livewire -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    // Configure Toastr - must be after toastr.js loads
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof toastr !== 'undefined') {
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": true,
                "progressBar": true,
                "positionClass": "toast-top-right",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };
            console.log('Toastr configured successfully');
        } else {
            console.error('Toastr failed to load');
        }
    });
</script>
<script src="{{mix('build/panel/js/livewire.js')}}"></script>
@stack('footer')
</body>
</html>
