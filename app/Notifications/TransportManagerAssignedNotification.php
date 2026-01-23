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
        if (!$this->order->relationLoaded('product')) {
            $this->order->load('product');
        }
        
        $siteName = $this->order->site->name ?? 'Unknown Site';
        $productName = $this->order->product->product_name ?? 'Unknown Product';
        
        return [
            'type' => 'transport_manager_assigned',
            'order_id' => $this->order->id,
            'title' => 'Order Assigned to You',
            'message' => "You have been assigned to Order #{$this->order->id} for {$siteName}. Product: {$productName}, Quantity: {$this->order->quantity}. Click to view order details and manage delivery.",
            'link' => route('admin.deliveries.index') . '?order_id=' . $this->order->id . '&edit=1',
        ];
    }
}

