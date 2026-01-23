
<div class="app-navbar flex-shrink-0">
    <!-- Birthday Icon Dropdown -->
    
    <!-- Theme Mode Switcher (Session Selector can be here if present) -->
    <div class="app-navbar-item ms-1 ms-md-4">
        @include('panel::partials.theme-mode._main')
    </div>
    
    <!-- Notification Bell -->
    @livewire('admin.modules.notification.notification-bell')
    
    <!-- User Profile Icon -->
    <div class="app-navbar-item ms-1 ms-md-4" id="kt_header_user_menu_toggle">
        <div class="cursor-pointer symbol symbol-35px"
             data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
             data-kt-menu-attach="parent"
             data-kt-menu-placement="bottom-end">
            <img src="{{auth()->user()->getfirstMediaUrl('profile_image')}}" class="rounded-3" alt="user"/>
        </div>
        @include('panel::partials.menus._user-account-menu')
    </div>
</div>
