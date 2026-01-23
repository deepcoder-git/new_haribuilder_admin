<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCreatedNotification extends Notification
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
        if (!$this->order->relationLoaded('siteManager')) {
            $this->order->load('siteManager');
        }
        if (!$this->order->relationLoaded('site')) {
            $this->order->load('site');
        }
        if (!$this->order->relationLoaded('product')) {
            $this->order->load('product');
        }
        
        $siteManagerName = $this->order->siteManager->name ?? 'Site Supervisor';
        $siteName = $this->order->site->name ?? 'Unknown Site';
        $productName = $this->order->product->product_name ?? 'Unknown Product';
        
        return [
            'type' => 'order_created',
            'order_id' => $this->order->id,
            'title' => 'New Order Created',
            'message' => "New order #{$this->order->id} has been created by {$siteManagerName} for {$siteName}. Product: {$productName}, Quantity: {$this->order->quantity}",
            'link' => route('admin.orders.index', ['id' => $this->order->id]),
        ];
    }
}

