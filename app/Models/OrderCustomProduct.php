<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCustomProduct extends Model
{
    use HasFactory;
    protected $table = 'order_custom_products';

    protected $fillable = [
        'order_id',
        'product_details',
        'custom_note',
        'product_ids',
    ];

    protected $casts = [
        'product_details' => 'array',
        'product_ids' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get product relationship using product_id from product_details JSON
     * Note: This is not a true Eloquent relationship due to JSON storage
     */
    public function getProductAttribute()
    {
        $productDetails = $this->product_details ?? [];
        $productId = $productDetails['product_id'] ?? null;
        
        if (!$productId) {
            return null;
        }
        
        // Handle both single product_id and array of product_ids
        if (is_array($productId)) {
            // If it's an array, get the first product_id
            $productId = !empty($productId) ? $productId[0] : null;
            if (!$productId) {
                return null;
            }
        }
        
        $product = Product::find($productId);
        
        // Ensure we return a single model, not a collection
        return $product instanceof \Illuminate\Database\Eloquent\Collection 
            ? $product->first() 
            : $product;
    }

    /**
     * Get unit relationship using unit_id from product_details JSON
     */
    public function getUnitAttribute()
    {
        $productDetails = $this->product_details ?? [];
        $unitId = $productDetails['unit_id'] ?? null;
        return $unitId ? Unit::find($unitId) : null;
    }

    /**
     * Get product_id from product_details
     */
    public function getProductIdAttribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['product_id'] ?? null;
    }

    /**
     * Get quantity from product_details
     */
    public function getQuantityAttribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['quantity'] ?? null;
    }

    /**
     * Get h1 from product_details
     */
    public function getHeight1Attribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['h1'] ?? null;
    }

    /**
     * Get h2 from product_details
     */
    public function getHeight2Attribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['h2'] ?? null;
    }

    /**
     * Get w1 from product_details
     */
    public function getWidth1Attribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['w1'] ?? null;
    }

    /**
     * Get w2 from product_details
     */
    public function getWidth2Attribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['w2'] ?? null;
    }

    /**
     * Get unit_id from product_details
     */
    public function getUnitIdAttribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['unit_id'] ?? null;
    }

    /**
     * Get actual_pcs (pieces) from product_details
     */
    public function getActualPcsAttribute()
    {
        $productDetails = $this->product_details ?? [];
        return $productDetails['actual_pcs'] ?? null;
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderCustomProductImage::class, 'order_custom_product_id')->orderBy('sort_order');
    }

    /**
     * Get custom images from new images table
     */
    public function getCustomImagesAttribute(): array
    {
        // Load images relationship if not already loaded
        if (!$this->relationLoaded('images')) {
            $this->load('images');
        }
        
        if ($this->images->isNotEmpty()) {
            return $this->images->pluck('image_path')->toArray();
        }
        
        return [];
    }

    /**
     * Get products from product_ids array
     * Returns a collection of Product models
     */
    public function getProductsFromIdsAttribute()
    {
        $productIds = $this->product_ids ?? [];
        
        if (empty($productIds) || !is_array($productIds)) {
            return collect([]);
        }
        
        // Ensure product_ids are integers
        $productIds = array_map('intval', array_filter($productIds, 'is_numeric'));
        
        if (empty($productIds)) {
            return collect([]);
        }
        
        return \App\Models\Product::whereIn('id', $productIds)->get();
    }

    /**
     * Get product_ids as array of integers
     */
    public function getProductIdsArrayAttribute(): array
    {
        $productIds = $this->product_ids ?? [];
        
        if (!is_array($productIds)) {
            return [];
        }
        
        return array_map('intval', array_filter($productIds, 'is_numeric'));
    }

    /**
     * Get all product IDs from both product_ids column and product_details.product_id
     * Returns a unified array of product IDs (integers)
     * 
     * @return array Array of product IDs as integers
     */
    public function getAllProductIds(): array
    {
        $allProductIds = [];
        
        // First, check product_ids column (JSON array)
        $productIdsColumn = $this->product_ids ?? [];
        if (is_array($productIdsColumn) && !empty($productIdsColumn)) {
            $allProductIds = array_merge($allProductIds, array_map('intval', array_filter($productIdsColumn, 'is_numeric')));
        }
        
        // Remove duplicates and return
        return array_values(array_unique($allProductIds));
    }

    /**
     * Get display product ID (for showing in response)
     * Returns the first product ID from either source
     * 
     * @return int|null
     */
    public function getDisplayProductId(): ?int
    {
        $allProductIds = $this->getAllProductIds();
        return !empty($allProductIds) ? $allProductIds[0] : null;
    }

    /**
     * Get material IDs from product_details.materials array
     * Extracts material_id from each material in the materials array
     * 
     * @return array Array of material IDs as integers
     */
    public function getMaterialIdsFromProductDetails(): array
    {
        $productDetails = $this->product_details ?? [];
        $materials = $productDetails['materials'] ?? [];
        
        if (!is_array($materials) || empty($materials)) {
            return [];
        }
        
        $materialIds = [];
        foreach ($materials as $material) {
            if (isset($material['material_id']) && is_numeric($material['material_id'])) {
                $materialIds[] = (int) $material['material_id'];
            }
        }
        
        return array_values(array_unique($materialIds));
    }

    /**
     * Get materials from products table using material_ids from product_details
     * Returns a collection of Product models (materials have is_product = 0)
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMaterialsFromProductDetails()
    {
        $materialIds = $this->getMaterialIdsFromProductDetails();
        
        if (empty($materialIds)) {
            return collect([]);
        }
        
        return Product::whereIn('id', $materialIds)
            ->whereIn('is_product', [0, 2]) // Materials have is_product = 0 or 2
            ->get();
    }

    /**
     * Get products that use materials from product_details
     * Finds products that have these materials in product_materials pivot table
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductsUsingMaterialsFromProductDetails()
    {
        $materialIds = $this->getMaterialIdsFromProductDetails();
        
        if (empty($materialIds)) {
            return collect([]);
        }
        
        // Get products that use these materials through product_materials pivot table
        return Product::whereHas('materials', function ($query) use ($materialIds) {
            $query->whereIn('products.id', $materialIds);
        })
        ->whereIn('is_product', [1, 2]) // Products have is_product = 1 or 2
        ->get();
    }

    /**
     * Get materials with quantities from product_details
     * Returns array with material_id, material info, and quantities
     * 
     * @return array Array of material data with quantities
     */
    public function getMaterialsWithQuantitiesFromProductDetails(): array
    {
        $productDetails = $this->product_details ?? [];
        $materials = $productDetails['materials'] ?? [];
        
        if (!is_array($materials) || empty($materials)) {
            return [];
        }
        
        $materialIds = $this->getMaterialIdsFromProductDetails();
        $materialsCollection = $this->getMaterialsFromProductDetails();
        $materialsKeyed = $materialsCollection->keyBy('id');
        
        $result = [];
        foreach ($materials as $materialData) {
            $materialId = isset($materialData['material_id']) ? (int) $materialData['material_id'] : null;
            
            if (!$materialId || !$materialsKeyed->has($materialId)) {
                continue;
            }
            
            $material = $materialsKeyed->get($materialId);
            
            // Get quantity from material data (prioritize cal_qty, then calculated_quantity, then actual_pcs)
            $quantity = $materialData['cal_qty'] ?? 
                       $materialData['calculated_quantity'] ?? 
                       $materialData['actual_pcs'] ?? 
                       0;
            
            $result[] = [
                'material_id' => $materialId,
                'material' => $material,
                'actual_pcs' => $materialData['actual_pcs'] ?? null,
                'calculated_quantity' => $materialData['calculated_quantity'] ?? null,
                'cal_qty' => $materialData['cal_qty'] ?? null,
                'quantity' => (float) $quantity,
                'measurements' => $materialData['measurements'] ?? [],
            ];
        }
        
        return $result;
    }

    /**
     * Get products with quantities that use materials from product_details
     * Returns products with their quantities based on material usage
     * 
     * @return array Array of product data with quantities
     */
    public function getProductsWithQuantitiesFromMaterials(): array
    {
        $materialsWithQuantities = $this->getMaterialsWithQuantitiesFromProductDetails();
        
        if (empty($materialsWithQuantities)) {
            return [];
        }
        
        $result = [];
        $productQuantities = []; // Track quantities per product_id
        
        foreach ($materialsWithQuantities as $materialData) {
            $materialId = $materialData['material_id'];
            $materialQuantity = $materialData['quantity'];
            
            // Get products that use this material
            $products = Product::whereHas('materials', function ($query) use ($materialId) {
                $query->where('products.id', $materialId);
            })
            ->whereIn('is_product', [1, 2])
            ->with('materials')
            ->get();
            
            foreach ($products as $product) {
                // Get material quantity from pivot (product_materials table)
                $materialPivot = $product->materials->firstWhere('id', $materialId);
                $materialPivotQuantity = $materialPivot ? (float) ($materialPivot->pivot->quantity ?? 0) : 0;
                
                // Calculate product quantity: material_quantity / material_pivot_quantity
                // If material_pivot_quantity is 0, skip to avoid division by zero
                if ($materialPivotQuantity > 0) {
                    $calculatedProductQuantity = $materialQuantity / $materialPivotQuantity;
                    
                    // Initialize or add to product quantity
                    if (!isset($productQuantities[$product->id])) {
                        $productQuantities[$product->id] = [
                            'product' => $product,
                            'quantity' => 0,
                        ];
                    }
                    
                    // Sum up quantities for products that use multiple materials
                    $productQuantities[$product->id]['quantity'] += $calculatedProductQuantity;
                }
            }
        }
        
        // Convert to array format
        foreach ($productQuantities as $productId => $data) {
            $result[] = [
                'product_id' => $productId,
                'product' => $data['product'],
                'quantity' => $data['quantity'],
            ];
        }
        
        return $result;
    }
}

