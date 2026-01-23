<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public Delivery $delivery
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'delivery_completed',
            'order_id' => $this->order->id,
            'delivery_id' => $this->delivery->id,
            'message' => "Delivery for order #{$this->order->id} has been completed. Please review and complete the order.",
            'title' => 'Delivery Completed',
        ];
    }
}

