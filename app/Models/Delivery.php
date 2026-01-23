<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'site_id',
        'transport_manager_id',
        'delivery_date',
        'status'
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function transportManager()
    {
        return $this->belongsTo(Moderator::class, 'transport_manager_id');
    }
}

