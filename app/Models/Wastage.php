<?php

declare(strict_types=1);

namespace App\Models;

use App\Utility\Enums\WastageTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Wastage extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'manager_id',
        'site_id',
        'order_id',
        'date',
        'reason',
    ];

    protected $casts = [
        'type' => WastageTypeEnum::class,
        'date' => 'date',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Moderator::class, 'manager_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wastage_products')
                    ->withPivot('quantity', 'wastage_qty', 'unit_type', 'adjust_stock')
                    ->withTimestamps();
    }
}

