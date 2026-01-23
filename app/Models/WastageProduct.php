<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastageProduct extends Model
{
    use HasFactory;

    protected $table = 'wastage_products';

    protected $fillable = [
        'wastage_id',
        'product_id',
        'quantity',
        'wastage_qty',
        'unit_type',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'wastage_qty' => 'decimal:2',
    ];

    public function wastage(): BelongsTo
    {
        return $this->belongsTo(Wastage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

