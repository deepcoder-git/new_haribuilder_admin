<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderReadyForCompletionNotification extends Notification
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
            'type' => 'order_ready_for_completion',
            'order_id' => $this->order->id,
            'message' => "Order #{$this->order->id} delivery has been completed by Transport Manager. Please review and finalize the order.",
            'title' => 'Order Ready for Completion',
            'link' => route('admin.orders.index', ['id' => $this->order->id]),
        ];
    }
}

