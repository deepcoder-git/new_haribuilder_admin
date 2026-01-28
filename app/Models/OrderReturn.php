<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'site_id',
        'manager_id',
        'type',
        'date',
        'status',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Moderator::class, 'manager_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}

