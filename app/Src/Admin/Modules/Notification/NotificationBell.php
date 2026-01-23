<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Notification;

use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->updateUnreadCount();
    }

    public function updateUnreadCount(): void
    {
        $user = Auth::guard('moderator')->user();
        if (!$user) {
            $this->unreadCount = 0;
            return;
        }

        $this->unreadCount = \Illuminate\Notifications\DatabaseNotification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    #[On('notification-count-updated')]
    public function refreshCount(): void
    {
        $this->updateUnreadCount();
    }

    public function render()
    {
        return view('admin::Notification.views.bell');
    }
}

