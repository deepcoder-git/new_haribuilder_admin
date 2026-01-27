<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\StoreManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Utility\Enums\StoreEnum;
use App\Utility\Enums\RoleEnum;
use App\Models\Product;

class WorkshopStoreManagerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $role = $user?->getRole();
        
        $regularProducts = collect();
        $customProducts = collect();
        $rootCustomProducts = collect(); // Collect all custom_products at root level (connected products from custom products)
        
        $currentOrder = $this->resource;
        
        // Process only the single order (no parent/child relationships)
        $order = $currentOrder;
        
        // Ensure products relationship is loaded
        if (!$order->relationLoaded('products')) {
            $order->load('products.category', 'products.productImages');
        }
        
        // Use order products relationship instead of direct DB query
        foreach ($order->products as $product) {
            if ($product instanceof Product && $this->shouldDisplayProduct($product, $role)) {
                // Get quantity from pivot
                $quantity = (int) ($product->pivot->quantity ?? 0);
                    $imageUrls = [];
                    
                    if ($product->relationLoaded('productImages') && $product->productImages && $product->productImages->isNotEmpty()) {
                        foreach ($product->productImages->sortBy('order') as $productImage) {
                            $imagePath = $productImage->image_url;

                            if (!empty($imagePath)) {
                                if (preg_match('#^https?://#i', $imagePath)) {
                                    $imageUrls[] = $imagePath;
                                } else {
                                    $imageUrls[] = url(Storage::url($imagePath));
                                }
                            }
                        }
                    }
                    
                    if (empty($imageUrls) && $product->image) {
                        $imageUrls[] = url(Storage::url($product->image));
                    }
                    
                // Use product's store type instead of order store (null-safe)
                $productStore = $product->store ?? null;
                $productStoreEnum = null;

                if ($productStore instanceof StoreEnum) {
                    $productStoreEnum = $productStore;
                } elseif ($productStore !== null && (is_string($productStore) || is_int($productStore))) {
                    $productStoreEnum = StoreEnum::tryFrom((string) $productStore);
                }
                    
                    // Calculate stock status using helper function
                    $stockStatus = calculateStockStatus(
                        $product->total_stock_quantity,
                        $product->low_stock_threshold
                    );
                    
                    // Calculate available quantity
                    $availableQty = (int) $product->total_stock_quantity;
                    
                    // Get materials connected to this product
                    $productMaterials = collect();
                    
                    // Ensure materials + their categories/images are eager loaded (avoid lazy loading)
                    $product->loadMissing('materials.category', 'materials.productImages');
                    
                    foreach ($product->materials as $material) {
                        $materialImageUrls = [];
                        
                        // Get material images
                        if ($material->relationLoaded('productImages') && $material->productImages && $material->productImages->isNotEmpty()) {
                            foreach ($material->productImages->sortBy('order') as $productImage) {
                                $imagePath = $productImage->image_url;
                                
                                if (!empty($imagePath)) {
                                    if (preg_match('#^https?://#i', $imagePath)) {
                                        $materialImageUrls[] = $imagePath;
                                    } else {
                                        $materialImageUrls[] = url(Storage::url($imagePath));
                                    }
                                }
                            }
                        }
                        
                        // Fallback to material image if no product images
                        if (empty($materialImageUrls) && $material->image) {
                            $materialImageUrls[] = url(Storage::url($material->image));
                        }
                        
                        // Get quantity from pivot (product_materials table)
                        $materialQuantity = (float) ($material->pivot->quantity ?? 0);
                        // Multiply by product quantity in order
                        $totalMaterialQuantity = $materialQuantity * $quantity;
                        
                        $productMaterials->push([
                            'material_id' => $material->id,
                            'material_name' => $material->product_name,
                            'quantity' => $totalMaterialQuantity,
                            'unit_type' => $material->pivot->unit_type ?? $material->unit_type ?? null,
                            'category' => $material->category->name ?? null,
                            'images' => $materialImageUrls,
                        ]);
                    }
                    
                    $regularProducts->push([
                        'product_id' => $product->id,
                        'product_name' => $product->product_name,
                        'type_name' => $productStoreEnum?->getName() ?? 'Workshop Store',
                        'product_status' => $order->getProductStatus($product->store),
                        'quantity' => $quantity,
                        'unit_type' => $product->unit_type,
                        'category' => $product->category->name ?? null,
                        'is_custom' => 0,
                        'custom_images' => $imageUrls,
                        'images' => $imageUrls,
                        'store_type' => $productStoreEnum?->value ?? StoreEnum::WarehouseStore->value,
                        'low_stock' => $stockStatus['low_stock'],
                        'out_of_stock' => $stockStatus['out_of_stock'],
                        'available_qty' => $availableQty,
                        'materials' => $productMaterials->values(),
                    ]);
                }
        }
        
        // Workshop Store Manager: Always show custom products (p2 - warehouse related)
        // Custom products are always displayed regardless of store type
        // Use order customProducts relationship instead of direct DB query
        if (!$order->relationLoaded('customProducts')) {
            $order->load('customProducts.images');
        }
        $customProductsModel = $order->customProducts;
        
        foreach ($customProductsModel as $customProduct) {
                $imageUrls = [];
                
                // Use customProduct images relationship instead of direct DB query
                if ($customProduct->relationLoaded('images') && $customProduct->images->isNotEmpty()) {
                    foreach ($customProduct->images as $productImage) {
                        if ($productImage->image_path) {
                            if (preg_match('#^https?://#i', $productImage->image_path)) {
                                $imageUrls[] = $productImage->image_path;
                            } else {
                                $imageUrls[] = Storage::disk('public')->exists($productImage->image_path) 
                                    ? url(Storage::url($productImage->image_path)) 
                                    : url('storage/' . $productImage->image_path);
                            }
                        }
                    }
                }
                
                $productDetails = $customProduct->product_details ?? [];
                $product = $customProduct->product;
                $connectedProductsData = [];

                // Get all product IDs from both product_ids column and product_details.product_id
                $productIds = $customProduct->getAllProductIds();

                // Process all product_ids (also check for actual_pcs if no product_ids)
                if (!empty($productIds) || (isset($productDetails['actual_pcs']) && $productDetails['actual_pcs'] > 0)) {
                    foreach ($productIds as $productId) {
                        // Eager load category to avoid lazy-loading violations
                        $getproductRes = Product::with('category')->find($productId);
                        
                        if ($getproductRes instanceof Product && $this->shouldDisplayProduct($getproductRes, $role)) {
                            $product = $order->products->firstWhere('id', $getproductRes->id);
                            $connectedImageUrls = [];
                            
                            if ($product instanceof Product && $product->relationLoaded('productImages') && $product->productImages && $product->productImages->isNotEmpty()) {
                                foreach ($product->productImages->sortBy('order') as $productImage) {
                                    $imagePath = $productImage->image_url;

                                    if (!empty($imagePath)) {
                                        if (preg_match('#^https?://#i', $imagePath)) {
                                            $connectedImageUrls[] = $imagePath;
                                        } else {
                                            $connectedImageUrls[] = url(Storage::url($imagePath));
                                        }
                                    }
                                }
                            }
                            
                            if (empty($connectedImageUrls) && $product instanceof Product && $product->image) {
                                $connectedImageUrls[] = url(Storage::url($product->image));
                            }

                            // Get quantity from order products relationship
                            $orderProduct = $order->products->firstWhere('id', $productId);
                            $quantity = $orderProduct ? (int) ($orderProduct->pivot->quantity ?? 0) : ($getproductRes->quantity ?? 0);
                            
                // Use product's store type instead of order store (null-safe)
                $productStore = $getproductRes->store ?? null;
                $productStoreEnum = null;

                if ($productStore instanceof StoreEnum) {
                    $productStoreEnum = $productStore;
                } elseif ($productStore !== null && (is_string($productStore) || is_int($productStore))) {
                    $productStoreEnum = StoreEnum::tryFrom((string) $productStore);
                }
                            
                            // Calculate stock status using helper function
                            $stockStatus = calculateStockStatus(
                                $getproductRes->total_stock_quantity,
                                $getproductRes->low_stock_threshold
                            );
                            
                            // Calculate available quantity
                            $availableQty = (int) $getproductRes->total_stock_quantity;
                            
                            // Get materials connected to this product from product_materials table
                            $productMaterials = collect();
                            
                            // Ensure materials + their categories/images are eager loaded (avoid lazy loading)
                            $getproductRes->loadMissing('materials.category', 'materials.productImages');
                            
                            foreach ($getproductRes->materials as $material) {
                                $materialImageUrls = [];
                                
                                // Get material images
                                if ($material->relationLoaded('productImages') && $material->productImages && $material->productImages->isNotEmpty()) {
                                    foreach ($material->productImages->sortBy('order') as $productImage) {
                                        $imagePath = $productImage->image_url;
                                        
                                        if (!empty($imagePath)) {
                                            if (preg_match('#^https?://#i', $imagePath)) {
                                                $materialImageUrls[] = $imagePath;
                                            } else {
                                                $materialImageUrls[] = url(Storage::url($imagePath));
                                            }
                                        }
                                    }
                                }
                                
                                // Fallback to material image if no product images
                                if (empty($materialImageUrls) && $material->image) {
                                    $materialImageUrls[] = url(Storage::url($material->image));
                                }
                                
                                // Get quantity from pivot (product_materials table)
                                $materialQuantity = (float) ($material->pivot->quantity ?? 0);
                                // Multiply by product quantity in order
                                $totalMaterialQuantity = $materialQuantity * $quantity;
                                
                                $productMaterials->push([
                                    'material_id' => $material->id,
                                    'material_name' => $material->product_name,
                                    'quantity' => $totalMaterialQuantity,
                                    'unit_type' => $material->pivot->unit_type ?? $material->unit_type ?? null,
                                    'category' => $material->category->name ?? null,
                                    'images' => $materialImageUrls,
                                ]);
                            }
                            
                            // Process materials array with dynamic m* fields extraction (m1, m2, m3, m4, etc.)
                            $materialsArray = [];
                            if (isset($productDetails['materials']) && is_array($productDetails['materials'])) {
                                foreach ($productDetails['materials'] as $material) {
                                    $processedMaterial = [
                                        'material_id' => $material['material_id'] ?? null,
                                        'actual_pcs' => $material['actual_pcs'] ?? null,
                                    ];
                                    
                                    // Priority 1: If measurements array already exists, use it directly
                                    if (isset($material['measurements']) && is_array($material['measurements'])) {
                                        $processedMaterial['measurements'] = $material['measurements'];
                                    } else {
                                        // Priority 2: Dynamically extract all m* fields (m1, m2, m3, m4, etc.) and sort them
                                        $mFields = [];
                                        foreach ($material as $key => $value) {
                                            if (preg_match('/^m\d+$/', $key)) {
                                                // Extract the number from m* field (e.g., m1 -> 1, m2 -> 2)
                                                preg_match('/^m(\d+)$/', $key, $matches);
                                                $mNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                                                $mFields[$mNumber] = $value;
                                            }
                                        }
                                        
                                        // Sort m* fields by number (m1, m2, m3, m4, etc.) and create measurements array
                                        if (!empty($mFields)) {
                                            ksort($mFields);
                                            $processedMaterial['measurements'] = array_values($mFields);
                                        }
                                    }
                                    
                                    // Add cal_qty if it exists in the material
                                    if (isset($material['cal_qty']) && $material['cal_qty'] !== null) {
                                        $processedMaterial['cal_qty'] = (float) $material['cal_qty'];
                                    }
                                    
                                    $materialsArray[] = $processedMaterial;
                                }
                            }
                            
                            // Merge product_materials with custom product materials (from product_details)
                            $allMaterials = $productMaterials->merge(collect($materialsArray))->values();
                            
                            $connectedProductData = [
                                'product_id' => $getproductRes->id,
                                'product_name' => $getproductRes->product_name,
                                'type_name' => $productStoreEnum?->getName() ?? 'Workshop Store',
                                'product_status' => $order->getProductStatus($getproductRes->store),
                                'quantity' => $quantity,
                                'unit_type' => $getproductRes->unit_type ?? null,
                                'category' => $getproductRes->category->name ?? null,
                                'materials' => $allMaterials,
                                'is_custom' => 0,
                                'custom_images' => $connectedImageUrls,
                                'images' => $connectedImageUrls,
                                'store_type' => $productStoreEnum?->value ?? StoreEnum::WarehouseStore->value,
                                'low_stock' => $stockStatus['low_stock'],
                                'out_of_stock' => $stockStatus['out_of_stock'],
                                'available_qty' => $availableQty,
                            ];

                            $connectedProductsData[] = $connectedProductData;
                            // Add to root level custom_products collection (for root level display)
                            $rootCustomProducts->push($connectedProductData);
                            // DO NOT add to getproductDetails or regularProducts - these should only be in custom_products
                        }
                    }
                }

                // Get display product ID (first product ID from all sources)
                $displayProductId = $customProduct->getDisplayProductId();
                
                // Get the first product for display name if available
                $displayProduct = null;
                if ($displayProductId) {
                    // Eager load category to avoid lazy-loading violations
                    $displayProduct = Product::with('category')->find($displayProductId);
                }

                // Process materials array with dynamic m* fields extraction (m1, m2, m3, m4, etc.)
                $materialsArray = [];
                if (isset($productDetails['materials']) && is_array($productDetails['materials'])) {
                    foreach ($productDetails['materials'] as $material) {
                        $processedMaterial = [
                            'material_id' => $material['material_id'] ?? null,
                            'actual_pcs' => $material['actual_pcs'] ?? null,
                        ];
                        
                        // Priority 1: If measurements array already exists, use it directly
                        if (isset($material['measurements']) && is_array($material['measurements'])) {
                            $processedMaterial['measurements'] = $material['measurements'];
                        } else {
                            // Priority 2: Dynamically extract all m* fields (m1, m2, m3, m4, etc.) and sort them
                            $mFields = [];
                            foreach ($material as $key => $value) {
                                if (preg_match('/^m\d+$/', $key)) {
                                    // Extract the number from m* field (e.g., m1 -> 1, m2 -> 2)
                                    preg_match('/^m(\d+)$/', $key, $matches);
                                    $mNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                                    $mFields[$mNumber] = $value;
                                }
                            }
                            
                            // Sort m* fields by number (m1, m2, m3, m4, etc.) and create measurements array
                            if (!empty($mFields)) {
                                ksort($mFields);
                                $processedMaterial['measurements'] = array_values($mFields);
                            }
                        }
                        
                        // Add cal_qty if it exists in the material
                        if (isset($material['cal_qty']) && $material['cal_qty'] !== null) {
                            $processedMaterial['cal_qty'] = $material['cal_qty'];
                        }
                        
                        $materialsArray[] = $processedMaterial;
                    }
                }

                $customProductData = [
                    'custom_product_id' => $customProduct->id,
                    'product_id' => $displayProductId,
                    'product_name' => $displayProduct?->product_name ?? ($product instanceof Product ? $product->product_name : 'Custom Product'),
                    'type_name' => 'Workshop Store',
                    'product_status' => $order->getProductStatus(StoreEnum::WarehouseStore),
                    'quantity' => (int) ($productDetails['quantity'] ?? 0),
                    'unit_type' => $customProduct->unit?->name ?? null,
                    'unit_id' => $productDetails['unit_id'] ?? null,
                    'category' => $displayProduct?->category->name ?? (($product instanceof Product) ? ($product->category->name ?? null) : null),
                    'is_custom' => 1,
                    'custom_note' => $customProduct->custom_note ?? '',
                    'custom_images' => $imageUrls,
                    'store_type' => StoreEnum::WarehouseStore->value,
                    'materials' => $materialsArray,
                    'custom_products' => $connectedProductsData,
                ];
                
            $customProducts->push($customProductData);
        }

        // Get all product IDs from root custom_products to filter duplicates
        $customProductIds = $rootCustomProducts->pluck('product_id')->filter()->unique()->toArray();
        
        // Filter out products from regularProducts that exist in custom_products
        // Connected products from custom products should NOT appear in root products array
        $regularProducts = $regularProducts->reject(function ($item) use ($customProductIds) {
            return isset($item['product_id']) && in_array($item['product_id'], $customProductIds);
        })->values();
        
        // Merge custom products and regular products (no duplicates)
        // Connected products from custom products are only in custom_products key, not in root products
        $productsArray = $customProducts->merge($regularProducts)->values();
        
        // Use the current order directly (no parent/child relationships)
        $rootOrder = $currentOrder;
        
        $customerImageUrl = null;
        if ($rootOrder->customer_image) {
            $customerImageUrl = Storage::disk('public')->exists($rootOrder->customer_image) 
                ? url(Storage::url($rootOrder->customer_image))
                : url('storage/' . $rootOrder->customer_image);
        }

        // dd($order->getProductStatus(StoreEnum::WarehouseStore->value));
        
        return [
            'id' => $currentOrder->id,
            'order_slug' => 'ORD'.$currentOrder->id,
            'site_id' => $rootOrder->site->id ?? null,
            'site_name' => $rootOrder->site->name ?? null,
            'site_location' => $rootOrder->site->location ?? null,
            'status' => $currentOrder->status?->value ?? 'pending',
            'delivery_status' => $order->getProductStatus(StoreEnum::WarehouseStore->value),
            'priority' => $rootOrder->priority ?? null,
            'note' => $rootOrder->note ?? null,
            'rejected_note' => $rootOrder->rejected_note ?? null,
            'customer_image' => $customerImageUrl,
            'driver_name' => $rootOrder->driver_name ?? null,
            'vehicle_number' => $rootOrder->vehicle_number ?? null,
            'expected_delivery_date' => $this->formatDate($rootOrder->expected_delivery_date ?? $rootOrder->sale_date),
            'requested_date' => $this->formatDate($rootOrder->created_at),
            'created_at' => $this->formatDateTime($rootOrder->created_at),
            'updated_at' => $this->formatDateTime($rootOrder->updated_at),
            'products' => $productsArray,
            // 'custom_products' => $rootCustomProducts->values(),
        ];
    }

    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        $dateObj = $date instanceof Carbon 
            ? $date->copy() 
            : Carbon::parse($date);
        
        $dateObj->startOfDay();
        
        $today = Carbon::today()->startOfDay();
        $yesterday = Carbon::yesterday()->startOfDay();

        $dateString = $dateObj->toDateString();
        $todayString = $today->toDateString();
        $yesterdayString = $yesterday->toDateString();

        if ($dateString === $todayString) {
            return 'Today';
        } elseif ($dateString === $yesterdayString) {
            return 'Yesterday';
        }

        return $dateObj->format('d/m/Y');
    }

    private function formatDateTime($dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        $dateObj = $dateTime instanceof Carbon 
            ? $dateTime->copy() 
            : Carbon::parse($dateTime);
        
        $dateOnly = $dateObj->copy()->startOfDay();
        
        $today = Carbon::today()->startOfDay();
        $yesterday = Carbon::yesterday()->startOfDay();

        $dateString = $dateOnly->toDateString();
        $todayString = $today->toDateString();
        $yesterdayString = $yesterday->toDateString();

        if ($dateString === $todayString) {
            return 'Today';
        } elseif ($dateString === $yesterdayString) {
            return 'Yesterday';
        }

        return $dateObj->format('d/m/Y');
    }

    private function shouldDisplayProduct(Product $product, ?RoleEnum $role): bool
    {
        // If no role, show all products (for backward compatibility)
        if (!$role) {
            return true;
        }
        
        // Get product's store type (null-safe)
        $productStore = $product->store ?? null;
        $productStoreEnum = null;

        if ($productStore instanceof StoreEnum) {
            $productStoreEnum = $productStore;
        } elseif ($productStore !== null && (is_string($productStore) || is_int($productStore))) {
            $productStoreEnum = StoreEnum::tryFrom((string) $productStore);
        }
        
        // Workshop Store Manager (P2): show only Workshop store products
        if ($role === RoleEnum::WorkshopStoreManager) {
            return $productStoreEnum === StoreEnum::WarehouseStore;
        }
        
        // For other roles, show all products
        return true;
    }
}

