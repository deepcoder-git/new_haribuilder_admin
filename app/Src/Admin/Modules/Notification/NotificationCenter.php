<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\Notification;

use App\Utility\Livewire\BaseCrudComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class NotificationCenter extends BaseCrudComponent
{
    public int $unreadCount = 0;

    protected function getModelClass(): string
    {
        return \Illuminate\Notifications\DatabaseNotification::class;
    }

    protected function getViewName(): string
    {
        return 'admin::Notification.views.center';
    }

    protected function getValidationRules(): array
    {
        return [];
    }

    protected function getFormData(): array
    {
        return [];
    }

    protected function setFormData($model): void
    {
    }

    protected function resetForm(): void
    {
    }

    protected function getQuery()
    {
        $user = Auth::guard('moderator')->user();
        if (!$user) {
            return \Illuminate\Notifications\DatabaseNotification::query()->whereRaw('1 = 0');
        }

        return \Illuminate\Notifications\DatabaseNotification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->latest();
    }

    public function getUnreadCountProperty(): int
    {
        $user = Auth::guard('moderator')->user();
        if (!$user) {
            return 0;
        }

        return \Illuminate\Notifications\DatabaseNotification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int|string $id, ?string $redirectUrl = null): void
    {
        $user = Auth::guard('moderator')->user();
        if (!$user) {
            return;
        }

        $notification = \Illuminate\Notifications\DatabaseNotification::where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->first();
            
        if ($notification) {
            $notification->update(['read_at' => now()]);
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Notification marked as read']);
            $this->dispatch('notification-count-updated');
            $this->resetPage();
            
            // Redirect if URL provided (for notification links)
            if ($redirectUrl) {
                $this->redirect($redirectUrl, navigate: true);
            }
        }
    }

    public function markAllAsRead(): void
    {
        $user = Auth::guard('moderator')->user();
        if (!$user) {
            return;
        }

        \Illuminate\Notifications\DatabaseNotification::where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
            
        $this->dispatch('show-toast', ['type' => 'success', 'message' => 'All notifications marked as read']);
        $this->dispatch('notification-count-updated');
        $this->resetPage();
    }

    public function delete(int|string $id): void
    {
        $user = Auth::guard('moderator')->user();
        if (!$user) {
            return;
        }

        $notification = \Illuminate\Notifications\DatabaseNotification::where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->first();
            
        if ($notification) {
            $notification->delete();
            $this->dispatch('show-toast', ['type' => 'success', 'message' => 'Notification deleted']);
            $this->dispatch('notification-count-updated');
            $this->resetPage();
        }
    }

    public function getItemsProperty()
    {
        return $this->getQuery()->paginate($this->perPage);
    }

    public function render(): View
    {
        try {
            $breadcrumbUrl = route('admin.notifications.index');
        } catch (\Exception $e) {
            $breadcrumbUrl = '#';
        }

        return view($this->getViewName(), [
            'items' => $this->getItemsProperty(),
            'unreadCount' => $this->unreadCount,
        ])->layout('panel::layout.app', [
            'title' => 'Notifications',
            'breadcrumb' => [['Notifications', $breadcrumbUrl]],
        ]);
    }
}

