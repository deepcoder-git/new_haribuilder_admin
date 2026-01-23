<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'purchase_date',
        'purchase_number',
        'total_amount',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_amount' => 'integer',
        'status' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductPurchaseItem::class, 'product_purchase_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Moderator::class, 'created_by');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_purchase_items', 'product_purchase_id', 'product_id')
                    ->withPivot('quantity', 'unit_price', 'total_price')
                    ->withTimestamps();
    }
}

