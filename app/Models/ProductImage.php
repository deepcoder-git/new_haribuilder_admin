<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'image_name',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (empty($this->image_path)) {
            return '';
        }

        // If it's already an absolute URL, return it as is
        if (preg_match('#^https?://#i', $this->image_path)) {
            return $this->image_path;
        }

        // Otherwise, prepend /storage/ and convert to full URL
        $relativeUrl = '/storage/' . $this->image_path;
        return url($relativeUrl);
    }
}
