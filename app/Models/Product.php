<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Utility\Traits\HasSlug;
use App\Utility\Enums\ProductTypeEnum;
use App\Utility\Enums\StoreEnum;

class Product extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'status',
        'product_name',
        'slug',
        'category_id',
        'store',
        'store_manager_id',
        'unit_type',
        'country_of_manufacture',
        'image',
        'low_stock_threshold',
        'available_qty',
        'type',
        'is_product'
    ];

    protected static function getSlugSourceField(): string
    {
        return 'product_name';
    }

    protected $casts = [
        'status' => 'boolean',
        'low_stock_threshold' => 'integer',
        'available_qty' => 'integer',
        'type' => ProductTypeEnum::class,
        'store' => StoreEnum::class,
        'is_product' => 'integer'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function storeManager(): BelongsTo
    {
        return $this->belongsTo(Moderator::class, 'store_manager_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderProducts()
    {
        return $this->belongsToMany(Order::class, 'order_products')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function stockTransfers()
    {
        return $this->belongsToMany(StockTransfer::class, 'stock_transfer_products')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function wastages()
    {
        return $this->belongsToMany(Wastage::class, 'wastage_products')
                    ->withPivot('quantity', 'wastage_qty', 'unit_type')
                    ->withTimestamps();
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('order');
    }

    /**
     * Relationship: Products belong to many materials through product_materials pivot table
     * Materials are products with is_product = 0 or 2
     * Filters to only get materials (is_product IN (0, 2)), not products
     * 
     * @return BelongsToMany
     */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_materials', 'product_id', 'material_id')
                    ->whereIn('is_product', [0, 2])
                    ->withPivot('quantity', 'unit_type')
                    ->withTimestamps();
    }


    public function getFirstImageUrlAttribute(): ?string
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

    public function getImageUrlsAttribute(): array
    {
        $urls = [];
        $productImages = $this->productImages;
        
        foreach ($productImages as $productImage) {
            $urls[] = $productImage->image_url;
        }
        
        if (empty($urls) && $this->image) {
            $urls[] = \Illuminate\Support\Facades\Storage::url($this->image);
        }
        
        return $urls;
    }

    /**
     * Get image paths array (for compatibility with Material model)
     * Returns array of image paths from productImages or single image field
     */
    public function getImagePathsAttribute(): array
    {
        if (!$this->image) {
            return [];
        }

        if (is_array($this->image)) {
            return array_values(array_filter($this->image));
        }

        $decoded = json_decode($this->image, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        return [$this->image];
    }

    /**
     * Get primary image path (for compatibility with Material model)
     * Returns the first image path from image_paths
     */
    public function getPrimaryImageAttribute(): ?string
    {
        return $this->image_paths[0] ?? null;
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
     * Get material_name attribute (for compatibility with Material model)
     * Returns product_name since materials are now products with is_product = 0
     */
    public function getMaterialNameAttribute(): ?string
    {
        return $this->product_name;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope to filter products by is_product flag
     * is_product = 0 means it's a material
     * is_product = 1 means Material As Product
     * is_product = 2 means Material + Product
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param bool|int $value If bool: true = products (1,2), false = materials (0). If int: exact value.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsProduct($query, bool|int $value = true)
    {
        if (is_bool($value)) {
            return $value ? $query->whereIn('is_product', [1, 2]) : $query->where('is_product', 0);
        }
        return $query->where('is_product', $value);
    }

    /**
     * Scope to filter materials (is_product = 0)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMaterial($query)
    {
        return $query->where('is_product', 0);
    }

    /**
     * Get total stock quantity from latest stock entry
     * This represents the cumulative stock after all adjustments
     * Stock is managed separately for products (is_product = 1) and materials (is_product = 0)
     * Returns general stock (site_id = null) quantity
     * LPO store products always return 0
     */
    public function getTotalStockQuantityAttribute(): int
    {
        // LPO store products always have 0 quantity
        if ($this->store === \App\Utility\Enums\StoreEnum::LPO) {
            return 0;
        }
        
        // Get latest stock entry for this product/material (general stock, site_id = null)
        // Stock is automatically filtered by is_product flag through the relationship
        $latestStock = $this->stocks()
            ->whereNull('site_id')  // Only get general stock, not site-specific
            ->where('status', true)
            ->latest('created_at')
            ->latest('id')
            ->first();

        // If no stock entry exists, fall back to available_qty field
        if (!$latestStock) {
            return (int) ($this->available_qty ?? 0);
        }

        return (int) $latestStock->quantity;
    }
}

