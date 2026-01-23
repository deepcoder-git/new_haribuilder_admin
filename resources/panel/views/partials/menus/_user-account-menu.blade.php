<!--begin::User account menu-->
<div
    class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
    data-kt-menu="true">
    <!--begin::Menu item-->
    <div class="menu-item px-5">
        <a href="{{route('admin.profile.index')}}" class="menu-link px-5">
            My Profile
        </a>
    </div>
    <div class="menu-item px-5">
        <a href="{{route('admin.profile.change-password')}}" class="menu-link px-5">
            Change Password
        </a>
    </div>
    <!--end::Menu item-->
    <!--begin::Menu item-->
    <div class="menu-item px-5">
        <form action="{{ route('admin.logout') }}" id="logout-user" method="post">@csrf</form>
        <a href="javascript:void(0)" class="menu-link px-5"
           onclick="document.getElementById('logout-user').submit();">
            Logout
        </a>
    </div>
    <!--end::Menu item-->
</div>
<!--end::User account menu-->
