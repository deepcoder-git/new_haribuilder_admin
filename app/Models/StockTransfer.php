<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_site_id',
        'to_site_id',
        'transfer_date',
        'transfer_status',
        'notes',
        'status'
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'status' => 'boolean'
    ];

    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'stock_transfer_products')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}

