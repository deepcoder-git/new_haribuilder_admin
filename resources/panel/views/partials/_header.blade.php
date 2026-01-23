<!--begin::Header-->
<div id="kt_app_header" class="app-header "
     data-kt-sticky="false" data-kt-sticky-activate="{default: true, lg: true}" data-kt-sticky-name="app-header-minimize"
     data-kt-sticky-offset="{default: '200px', lg: '0'}" data-kt-sticky-animation="false">
    <!--begin::Header container-->
    <div class="app-container  container-fluid d-flex align-items-stretch justify-content-between "
         id="kt_app_header_container">
        <!--begin::Sidebar mobile toggle-->
        <div class="d-flex align-items-center d-lg-none ms-n3 me-1 me-md-2" title="Show sidebar menu">
            <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
                <i class="ki-duotone ki-abstract-14 fs-2 fs-md-1"><span class="path1"></span><span class="path2"></span></i>
            </div>
        </div>
        <!--end::Sidebar mobile toggle-->
        <!--begin::Mobile logo-->
        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <a href="{{route('admin.dashboard')}}" class="d-lg-none d-flex align-items-center text-decoration-none">
                    <div class="symbol symbol-35px me-2">
                    <img src="{{ mix('build/panel/images/logo/logo.jpg') }}" alt="Hari Builders Logo" class="symbol-label" style="width: 35px; height: 35px; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                    <div style="display: none; width: 35px; height: 35px; align-items: center; justify-content: center; color: #1e3a8a !important; font-size: 1.2rem; background: #ffffff; border-radius: 0.475rem;">
                        <i class="fa-solid fa-building"></i>
                    </div>
                </div>
                <span class="text-gray-800 fw-bold fs-5">Hari Builders</span>
            </a>
        </div>
        <!--end::Mobile logo-->
        <!--begin::Header wrapper-->
        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">
            @include('panel::partials._page-title')
            @include('panel::partials.header._navbar')
        </div>
        <!--end::Header wrapper-->
    </div>
    <!--end::Header container-->
</div>
<!--end::Header-->
