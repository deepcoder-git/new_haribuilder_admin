<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <title>{{ $title?($title.' | '.config('app.name')):config('app.name') }}</title>
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
    @stack('header')
    @livewireStyles
</head>
<body id="kt_body" class="app-blank">
@include('panel::partials._page-loader')
<div class="d-flex flex-column flex-root" id="kt_app_root">{{ $slot }}</div>
<script src="{{ mix('build/panel/vendors/plugins.bundle.js') }}"></script>
<script src="{{ mix('build/panel/vendors/scripts.bundle.js') }}"></script>
<script>
    const SystemLoader = {
        loader: null,
        
        init() {
            if (!this.loader) {
                this.loader = document.getElementById('system-loader');
            }
        },
        
        hidePageLoader() {
            if (this.loader) {
                this.loader.classList.add('hide');
            }
        }
    };
    document.addEventListener('DOMContentLoaded', function() {
        SystemLoader.init();
        setTimeout(() => {
            SystemLoader.hidePageLoader();
        }, 300);
        
        if (typeof KTApp !== 'undefined' && KTApp.hidePageLoading) {
            KTApp.hidePageLoading();
        }
    });
    window.addEventListener('load', function() {
        SystemLoader.init();
        SystemLoader.hidePageLoader();
        
        if (typeof KTApp !== 'undefined' && KTApp.hidePageLoading) {
            KTApp.hidePageLoading();
        }
    });
</script>
@livewireScripts
<script src="{{mix('build/panel/js/livewire.js')}}"></script>
</body>
</html>
