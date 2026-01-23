<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCustomProductImage extends Model
{
    protected $table = 'order_custom_product_images';

    protected $fillable = [
        'order_custom_product_id',
        'image_path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function orderCustomProduct(): BelongsTo
    {
        return $this->belongsTo(OrderCustomProduct::class);
    }

    /**
     * Get full URL for the image
     */
    public function getImageUrlAttribute(): string
    {
        if (empty($this->image_path)) {
            return '';
        }

        if (preg_match('#^https?://#i', $this->image_path)) {
            return $this->image_path;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->exists($this->image_path)
            ? url(\Illuminate\Support\Facades\Storage::url($this->image_path))
            : url('storage/' . $this->image_path);
    }
}

