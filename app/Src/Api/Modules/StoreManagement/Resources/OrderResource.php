<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\StoreManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Utility\Enums\RoleEnum;
use App\Utility\Enums\StoreEnum;
use App\Utility\Enums\ProductTypeEnum;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Check store manager type to determine response format
        // Get store manager dynamically (store_manager_role column has been removed)
        $storeManager = $this->storeManager(); // Call as method, not property
        $storeManagerRole = $storeManager?->getRole();
        $isWarehouseStoreManager = $storeManagerRole === RoleEnum::WorkshopStoreManager;
        
        // Determine store type name
        $storeTypeName = null;
        if ($storeManagerRole === RoleEnum::WorkshopStoreManager) {
            $storeTypeName = StoreEnum::WarehouseStore->getName();
        } elseif ($storeManagerRole === RoleEnum::StoreManager) {
            $storeTypeName = StoreEnum::HardwareStore->getName();
        }
        
        $regularProducts = collect();
        $customProducts = collect();
        
        // Determine if this is a Hardware Store manager
        $isHardwareStoreManager = $storeManagerRole === RoleEnum::StoreManager;
        
        // Ensure products relationship is loaded
        if (!$this->relationLoaded('products')) {
            $this->load('products.category', 'products.productImages');
        }
        
        // Use order products relationship instead of direct DB query
        foreach ($this->products as $product) {
            // Product filtering is now managed at the order level via store_manager_role and store fields
            // All products in orders assigned to this store manager are displayed
            
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
            
            // Use product store type (store column removed from orders table)
            $productStore = $product->store ?? StoreEnum::WarehouseStore;
            
            // Get quantity from pivot
            $quantity = (int) ($product->pivot->quantity ?? 0);
            
            // Get materials connected to this product
            $productMaterials = collect();
            
            // Load materials relationship if not already loaded
            if (!$product->relationLoaded('materials')) {
                $product->load('materials.category', 'materials.productImages');
            }
            
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
            
            // Calculate available quantity and out of stock status
            $availableQty = (int) $product->total_stock_quantity;
            $outOfStock = $availableQty <= 0 ? 1 : 0;
            
            $orderStatus = $this->status?->value ?? $this->status ?? 'pending';

            $regularProducts->push([
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                // Cast quantity to integer for API response (e.g. 10.00 => 10)
                'quantity' => $quantity,
                    'unit_type' => $product->unit_type,
                    'category' => $product->category->name ?? null,
                    'type_name' => ($productStore instanceof StoreEnum ? $productStore->getName() : StoreEnum::WarehouseStore->getName()),
                    // Use main order status instead of deprecated delivery_status column
                    'product_status' => $orderStatus,
                    'is_custom' => 0,
                    'custom_note' => null,
                    'custom_image' => null,
                    'custom_images' => [],
                    'images' => $imageUrls,
                    'materials' => $productMaterials->values(),
                    'available_qty' => $availableQty,
                    'out_of_stock' => $outOfStock,
                ]);
        }
        
        // Use order customProducts relationship instead of direct DB query
        if (!$this->relationLoaded('customProducts')) {
            $this->load('customProducts.images');
        }
        $customProductsModel = $this->customProducts;
        
        foreach ($customProductsModel as $customProduct) {
            // Get product details from JSON
            $productDetails = $customProduct->product_details ?? [];
            $product = $customProduct->product;
            $connectedProductsData = [];
            
            // // For Hardware Store managers, only show custom products with hardware store products
            // if ($isHardwareStoreManager && (!$product || $product->store !== StoreEnum::HardwareStore)) {
            //     continue;
            // }
            
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
            
            // Get all product IDs from both product_ids column and product_details.product_id
            $productIds = $customProduct->getAllProductIds();
            
            // Process all product_ids (also check for actual_pcs if no product_ids)
            if (!empty($productIds) || (isset($productDetails['actual_pcs']) && $productDetails['actual_pcs'] > 0)) {
                foreach ($productIds as $productId) {
                    $getproductRes = \App\Models\Product::find($productId);

                    if ($getproductRes) {
                        $product = $this->products->firstWhere('id', $getproductRes->id);
                        $connectedImageUrls = [];
                        
                        if ($product && $product->relationLoaded('productImages') && $product->productImages && $product->productImages->isNotEmpty()) {
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
                        
                        if (empty($connectedImageUrls) && $product && $product->image) {
                            $connectedImageUrls[] = url(Storage::url($product->image));
                        }

                        // Get quantity from custom product's connected_products array
                        // Custom product connected products are stored separately from regular order products
                        $quantity = 0;
                        $connectedProducts = $productDetails['connected_products'] ?? [];
                        if (is_array($connectedProducts) && !empty($connectedProducts)) {
                            // Find this product in connected_products array
                            foreach ($connectedProducts as $connectedProduct) {
                                if (isset($connectedProduct['product_id']) && (int) $connectedProduct['product_id'] === $productId) {
                                    $quantity = (int) ($connectedProduct['quantity'] ?? 0);
                                    break;
                                }
                            }
                        }
                        
                        // Fallback: If not found in connected_products, try legacy product_details quantity
                        if ($quantity == 0) {
                            $productDetailsProductId = $productDetails['product_id'] ?? null;
                            if ($productDetailsProductId) {
                                if (is_array($productDetailsProductId)) {
                                    if (in_array($productId, array_map('intval', $productDetailsProductId))) {
                                        $quantity = (int) ($productDetails['quantity'] ?? 0);
                                    }
                                } else {
                                    if ((int) $productDetailsProductId === $productId) {
                                        $quantity = (int) ($productDetails['quantity'] ?? 0);
                                    }
                                }
                            }
                        }
                        
                        // Use product store type (store column removed from orders table)
                        $productStore = $getproductRes->store ?? StoreEnum::WarehouseStore;

                        // Get materials connected to this product
                        $productMaterials = collect();
                        
                        // Load materials relationship if not already loaded
                        if (!$getproductRes->relationLoaded('materials')) {
                            $getproductRes->load('materials.category', 'materials.productImages');
                        }
                        
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

                        // Calculate available quantity and out of stock status
                        $availableQty = (int) $getproductRes->total_stock_quantity;
                        $outOfStock = $availableQty <= 0 ? 1 : 0;
                        
                        $orderStatus = $this->status?->value ?? $this->status ?? 'pending';

                        $connectedProductData = [
                            'product_id' => $getproductRes->id,
                            'product_name' => $getproductRes->product_name,
                            'type_name' => ($productStore instanceof StoreEnum ? $productStore->getName() : StoreEnum::WarehouseStore->getName()),
                            // Use main order status instead of deprecated delivery_status column
                            'product_status' => $orderStatus,
                            'quantity' => $quantity,
                            'unit_type' => $getproductRes->unit_type ?? null,
                            'category' => $getproductRes->category->name ?? null,
                            'is_custom' => 0,
                            'custom_note' => null,
                            'custom_image' => null,
                            'custom_images' => [],
                            'images' => $connectedImageUrls,
                            'materials' => $productMaterials->values(),
                            'available_qty' => $availableQty,
                            'out_of_stock' => $outOfStock,
                        ];
                        
                        $connectedProductsData[] = $connectedProductData;
                    }
                }
            }
            
            // Get display product ID (first product ID from all sources)
            $displayProductId = $customProduct->getDisplayProductId();
            
            // Get the first product for display name if available
            $displayProduct = null;
            if ($displayProductId) {
                $displayProduct = \App\Models\Product::find($displayProductId);
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

            $orderStatus = $this->status?->value ?? $this->status ?? 'pending';

            if ($isWarehouseStoreManager) {
                // Workshop format: custom products at root with regular products nested
                $customProducts->push([
                    'product_id' => $customProduct->id ?? null,
                    'custom_product_id' => $customProduct->id ?? null,
                    'product_name' => $displayProduct?->product_name ?? $product?->product_name ?? 'Custom Product',
                    'quantity' => (int) ($productDetails['quantity'] ?? 0),
                    'unit_type' => $customProduct->unit?->name ?? null,
                    'unit_id' => $productDetails['unit_id'] ?? null,
                    'category' => $displayProduct?->category->name ?? $product?->category->name ?? null,
                    'type_name' =>'Workshop Store',
                    // Use main order status instead of deprecated delivery_status column
                    'product_status' => $orderStatus,
                    'is_custom' => 1,
                    'custom_note' => $customProduct->custom_note ?? '',
                    'custom_images' => $imageUrls,
                    'materials' => $materialsArray,
                    'custom_products' => $connectedProductsData,
                ]);
            } else {
                // Hardware format: custom products in standard format (will be merged with regular products)
                // Use product store type if available, otherwise default to workshop
                $productStore = $product?->store ?? $displayProduct?->store ?? StoreEnum::WarehouseStore;
                
                $customProducts->push([
                    'product_id' => $customProduct->id ?? null,
                    'custom_product_id' => $customProduct->id ?? null,
                    'product_name' => $displayProduct?->product_name ?? $product?->product_name ?? 'Custom Product',
                    'quantity' => (int) ($productDetails['quantity'] ?? 0),
                    'unit_type' => $customProduct->unit?->name ?? null,
                    'unit_id' => $productDetails['unit_id'] ?? null,
                    'category' => $displayProduct?->category->name ?? $product?->category->name ?? null,
                    'type_name' => ($productStore instanceof StoreEnum ? $productStore->getName() : StoreEnum::WarehouseStore->getName()),
                    // Use main order status instead of deprecated delivery_status column
                    'product_status' => $orderStatus,
                    'is_custom' => 1,
                    'custom_note' => $customProduct->custom_note ?? '',
                    'custom_images' => $imageUrls,
                    'materials' => $materialsArray,
                ]);
            }
        }
        
        // Allow both regular products and custom products to be displayed
        // Even if they reference the same product_id, show both entries
        // Determine products array based on store manager type
        if ($isWarehouseStoreManager) {
            // Workshop: Custom products (is_custom = 1) at root, regular products nested in custom_products
            // But also include regular products separately if they exist
            $productsArray = $customProducts->merge($regularProducts)->values()->unique(function ($item) {
                return ($item['product_id'] ?? $item['custom_product_id'] ?? '') . '_' . ($item['is_custom'] ?? 0);
            });
        } else {
            // Hardware: Merge all products together, allow duplicates if one is custom and one is regular
            $productsArray = $regularProducts->merge($customProducts)->values()->unique(function ($item) {
                return ($item['product_id'] ?? $item['custom_product_id'] ?? '') . '_' . ($item['is_custom'] ?? 0);
            });
        }
        
        return [
            'id' => $this->id,
            // 'order_slug' => 'ORD'.$this->id,
            'site_id' => $this->site->id ?? null,
            'site_name' => $this->site->name ?? null,
            'site_manager_id' => $this->site_manager_id ?? null,
            // Derived from product types / moderator role; underlying column removed
            'store_manager_role' => $storeManagerRole?->value ?? null,
            // Store column removed - store type determined from products
            'store_manager_id' => $this->storeManager()?->id ?? null, // For backward compatibility
            'site_location' => $this->site->location ?? null,
            'status' => $this->status?->value ?? 'pending',
            // 'status_name' => $this->status?->getName() ?? 'Pending',
            'delivery_status' => $this->delivery_status ?? 'pending',
            'priority' => $this->priority ?? null,
            'note' => $this->note ?? null,
            'rejected_note' => $this->rejected_note ?? null,
            'expected_delivery_date' => $this->formatDate($this->expected_delivery_date),
            'requested_date' => $this->formatDate($this->created_at),
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            'products' => $productsArray,
            'driver_name' => $this->driver_name ?? null,
            'vehicle_number' => $this->vehicle_number ?? null
        ];
    }

    /**
     * Format date: show "today", "yesterday", or formatted date
     */
    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        // Handle both Carbon instances and strings
        $dateObj = $date instanceof Carbon 
            ? $date->copy() 
            : Carbon::parse($date);
        
        // Normalize to start of day for accurate comparison
        $dateObj->startOfDay();
        
        $today = Carbon::today()->startOfDay();
        $yesterday = Carbon::yesterday()->startOfDay();

        // Compare using date string format for reliability
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

    /**
     * Format datetime: show "today", "yesterday", or formatted datetime
     */
    private function formatDateTime($dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        // Handle both Carbon instances and strings
        $dateObj = $dateTime instanceof Carbon 
            ? $dateTime->copy() 
            : Carbon::parse($dateTime);
        
        // Get date-only for comparison
        $dateOnly = $dateObj->copy()->startOfDay();
        
        $today = Carbon::today()->startOfDay();
        $yesterday = Carbon::yesterday()->startOfDay();

        // Compare using date string format for reliability
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
}