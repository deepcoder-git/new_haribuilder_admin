<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderApprovedNotification extends Notification
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
        return [
            'type' => 'order_approved',
            'order_id' => $this->order->id,
            'message' => "Order #{$this->order->id} has been approved by Store Manager. You can now assign it to a Transport Manager.",
            'title' => 'Order Approved',
            'link' => route('admin.orders.index', ['id' => $this->order->id]),
        ];
    }
}

