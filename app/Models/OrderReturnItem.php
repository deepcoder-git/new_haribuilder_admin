<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_return_id',
        'order_id',
        'product_id',
        'ordered_quantity',
        'return_quantity',
        'unit_type',
        'adjust_stock',
    ];

    protected $casts = [
        'adjust_stock' => 'boolean',
    ];

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

