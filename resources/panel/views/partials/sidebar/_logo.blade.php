<!--begin::Logo-->
<div class="app-sidebar-logo py-4" id="kt_app_sidebar_logo" style="background: #1e3a8a; border-bottom: 3px solid rgba(255,255,255,0.1); box-shadow: none; position: relative; display: flex; align-items: center; justify-content: center;">
    <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center justify-content-center text-decoration-none">
        <div class="symbol symbol-50px">
            <img src="{{ mix('build/panel/images/logo/logo.jpg') }}" alt="Hari Builders Logo" class="symbol-label" style="width: 50px; height: 50px; object-fit: contain; background: transparent;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
            <div style="display: none; width: 50px; height: 50px; align-items: center; justify-content: center; color: #1e3a8a !important; font-size: 1.5rem; background: #ffffff; border-radius: 0.475rem;">
                <i class="fa-solid fa-building"></i>
            </div>
        </div>
    </a>
    <div
        id="kt_app_sidebar_toggle"
        class="app-sidebar-toggle btn btn-icon btn-sm h-30px w-30px position-absolute"
        data-kt-toggle="true"
        data-kt-toggle-state="active"
        data-kt-toggle-target="body"
        data-kt-toggle-name="app-sidebar-minimize"
        style="top: 50%; right: -15px; transform: translateY(-50%); background: #ffffff !important; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; z-index: 10;">
        <i class="fa-solid fa-angles-left" style="color: #1e3a8a; transition: transform 0.3s ease; font-size: 0.875rem;"></i>
    </div>
    <!--end::Sidebar toggle-->
</div>
<!--end::Logo-->
