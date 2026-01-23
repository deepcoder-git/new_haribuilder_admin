<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // To get active units
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // To get inactive units
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Check if this unit is assigned to any products
     * 
     * @return bool
     */
    public function isAssignedToProducts(): bool
    {
        return Product::where('unit_type', $this->name)->exists();
    }

    /**
     * Get the count of products using this unit
     * 
     * @return int
     */
    public function getProductsCountAttribute(): int
    {
        return Product::where('unit_type', $this->name)->count();
    }

    /**
     * Get the count of products (is_product = 1 or 2) using this unit
     * 
     * @return int
     */
    public function getProductCountAttribute(): int
    {
        return Product::where('unit_type', $this->name)
            ->whereIn('is_product', [1, 2])
            ->count();
    }

    /**
     * Get the count of materials (is_product = 0) using this unit
     * 
     * @return int
     */
    public function getMaterialCountAttribute(): int
    {
        return Product::where('unit_type', $this->name)
            ->where('is_product', 0)
            ->count();
    }
}

