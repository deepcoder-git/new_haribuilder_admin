<!--begin::Sidebar-->
<div id="kt_app_sidebar" class="app-sidebar flex-column" 
     style="background: #1e3a8a; border-right: 1px solid rgba(255,255,255,0.1);"
     data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}"
     data-kt-drawer-overlay="true" data-kt-drawer-width="260px" data-kt-drawer-direction="start"
     data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
    @include('panel::partials.sidebar._logo')
    @include('panel::partials.sidebar._menu')
</div>
<!--end::Sidebar-->

<style>
/* Sidebar Color Scheme - Blue Background */
/* Main Sidebar Background: #1e3a8a (Blue) */
#kt_app_sidebar {
    background: #1e3a8a !important;
    border-right: 1px solid rgba(255,255,255,0.1) !important;
}

/* Logo Header Background: #1e3a8a (Blue) */
#kt_app_sidebar_logo {
    background: #1e3a8a !important;
    border-bottom: 3px solid rgba(255,255,255,0.1) !important;
}

/* Custom Scrollbar for Sidebar */
#kt_app_sidebar::-webkit-scrollbar {
    width: 6px;
}

#kt_app_sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
}

#kt_app_sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

#kt_app_sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}
</style>
