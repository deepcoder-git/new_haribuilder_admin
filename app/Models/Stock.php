<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'site_id',
        'name',
        'quantity',
        'adjustment_type',
        'reference_id',
        'reference_type',
        'notes',
        'metadata',
        'status'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'status' => 'boolean',
        'metadata' => 'array'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }


    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}

