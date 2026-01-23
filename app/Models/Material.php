<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Material Model
 * 
 * Materials are stored in the products table with is_product = 0
 * This model provides a Material interface while using the Product model
 */
class Material extends Product
{
    /**
     * The table associated with the model.
     * Materials are stored in the products table
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * Boot the model.
     * Automatically filter by is_product = 0 for all queries
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('material', function ($builder) {
            $builder->whereIn('is_product', [0, 2]);
        });

        // Set is_product = 0 when creating new materials
        static::creating(function ($material) {
            $material->is_product = 0;
        });
    }

    /**
     * Get material_name attribute (alias for product_name)
     * 
     * @return string|null
     */
    public function getMaterialNameAttribute(): ?string
    {
        return $this->product_name;
    }

    /**
     * Set material_name attribute (alias for product_name)
     * 
     * @param string $value
     */
    public function setMaterialNameAttribute(string $value): void
    {
        $this->product_name = $value;
    }

    /**
     * Get primary image path
     * Returns the first image path from productImages or single image field
     * 
     * @return string|null
     */
    public function getPrimaryImageAttribute(): ?string
    {
        $firstImage = $this->productImages()->first();
        if ($firstImage) {
            return $firstImage->image_path;
        }
        
        return $this->image;
    }

    /**
     * Get primary image URL
     * Returns the first image URL from productImages or single image field
     * 
     * @return string|null
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        $firstImage = $this->productImages()->first();
        if ($firstImage) {
            return $firstImage->image_url;
        }
        
        if ($this->image) {
            return \Illuminate\Support\Facades\Storage::url($this->image);
        }
        
        return null;
    }

    /**
     * Relationship: Materials belong to many products through product_materials pivot table
     * Filters to only get products (is_product IN (1, 2)), not materials
     * 
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_materials', 'material_id', 'product_id')
                    ->whereIn('is_product', [1, 2])
                    ->withPivot('quantity', 'unit_type')
                    ->withTimestamps();
    }

    /**
     * Relationship: Materials have many stock entries
     * Stock entries for materials are filtered by material_id or product_id where is_product = 0
     * 
     * @return HasMany
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'product_id');
    }
}

