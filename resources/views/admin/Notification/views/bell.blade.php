<div class="app-navbar-item ms-1 ms-md-4 position-relative">
    <a href="{{ route('admin.notifications.index') }}" 
       class="position-relative"
       data-bs-toggle="tooltip"
       title="Notifications ({{ $unreadCount }} unread)"
       style="text-decoration: none; display: inline-block;">
        <i class="fa-solid fa-bell fs-3" style="color: #1e3a8a !important;"></i>
        
        @if($unreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                  style="font-size: 0.65rem; padding: 0.25em 0.5em; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; border: 2px solid #ffffff; transform: translate(-30%, -30%); font-weight: 600;">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </a>
</div>

