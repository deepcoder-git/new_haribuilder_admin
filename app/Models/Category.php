<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // To get active category
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // To get inactive category
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Check if this category is assigned to any products
     * 
     * @return bool
     */
    public function isAssignedToProducts(): bool
    {
        return Product::where('category_id', $this->id)->exists();
    }

    /**
     * Get the count of products using this category
     * 
     * @return int
     */
    public function getProductsCountAttribute(): int
    {
        return Product::where('category_id', $this->id)->count();
    }

    /**
     * Get the count of products (is_product = 1 or 2) using this category
     * 
     * @return int
     */
    public function getProductCountAttribute(): int
    {
        return Product::where('category_id', $this->id)
            ->whereIn('is_product', [1, 2])
            ->count();
    }

    /**
     * Get the count of materials (is_product = 0) using this category
     * 
     * @return int
     */
    public function getMaterialCountAttribute(): int
    {
        return Product::where('category_id', $this->id)
            ->where('is_product', 0)
            ->count();
    }
}
