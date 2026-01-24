<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransportManagerAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        // Load relationships if not already loaded
        if (!$this->order->relationLoaded('site')) {
            $this->order->load('site');
        }
        
        $siteName = $this->order->site->name ?? 'Unknown Site';
        $productName = 'Mixed Products';
        
        return [
            'type' => 'transport_manager_assigned',
            'order_id' => $this->order->id,
            'title' => 'Order Assigned to You',
            'message' => "You have been assigned to Order #{$this->order->id} for {$siteName}. {$productName}.",
            'link' => route('admin.orders.view', $this->order->id),
        ];
    }
}

