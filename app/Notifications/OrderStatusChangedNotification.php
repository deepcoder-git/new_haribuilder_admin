<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $oldStatus
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $statusLabel = match($this->order->delivery_status) {
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->order->delivery_status),
        };

        return [
            'type' => 'order_status_changed',
            'order_id' => $this->order->id,
            'message' => "Order #{$this->order->id} delivery status changed to: {$statusLabel}",
            'title' => 'Order Status Updated',
        ];
    }
}

