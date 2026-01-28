<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use App\Models\OrderCustomProduct;
use App\Models\Supplier;
use Carbon\Carbon;
use App\Utility\Enums\StoreEnum;

class OrderResource extends JsonResource
{
    /**
     * Transform the order resource into an array.
     * Returns: order details with products array
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        
        // Ensure relationships are loaded
        if (!$order->relationLoaded('site')) {
            $order->load('site');
        }
        if (!$order->relationLoaded('products')) {
            $order->load('products.category', 'products.productImages');
        }
        if (!$order->relationLoaded('customProducts')) {
            $order->load('customProducts.images');
        }
        
        // Load suppliers for LPO products
        $supplierMapping = $order->supplier_id ?? [];
        $supplierIds = [];
        if (is_array($supplierMapping) && !empty($supplierMapping)) {
            $supplierIds = array_values(array_unique(array_filter($supplierMapping)));
        }
        $suppliers = !empty($supplierIds) ? Supplier::whereIn('id', $supplierIds)->get()->keyBy('id') : collect();
        
        // Process regular products
        $products = collect();
        $rootCustomProducts = collect(); // Collect all custom_products at root level (connected products from custom products)
        
        foreach ($order->products as $product) {
            $imageUrls = [];
            
            // Get product images
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
            
            // Fallback to product image if no product images
            if (empty($imageUrls) && $product->image) {
                $imageUrls[] = url(Storage::url($product->image));
            }
            
            // Get product store type
            $productStore = $product->store ?? null;
            $productStoreEnum = null;
            if ($productStore instanceof StoreEnum) {
                $productStoreEnum = $productStore;
            } elseif ($productStore !== null && (is_string($productStore) || is_int($productStore))) {
                $productStoreEnum = StoreEnum::tryFrom((string)$productStore);
            }
            
            // Get quantity from pivot (order_products table)
            // This quantity is aggregated: includes both regular order quantity and any custom product additions
            // Example: If p2 was added as 15 in regular order and 5 in custom product, this will show 20
            $quantity = (int) ($product->pivot->quantity ?? 0);
            
            // Get supplier information for LPO products
            $supplierData = null;
            if ($productStoreEnum === StoreEnum::LPO) {
                $supplierId = $supplierMapping[(string)$product->id] ?? null;
                if ($supplierId && $suppliers->has($supplierId)) {
                    $supplier = $suppliers->get($supplierId);
                    
                    // Get supplier-specific status from product_status
                    $supplierStatus = null;
                    $productStatusData = $order->product_status ?? [];
                    $lpoStatuses = $productStatusData['lpo'] ?? [];
                    if (is_array($lpoStatuses) && isset($lpoStatuses[(string)$supplierId])) {
                        $supplierStatus = $lpoStatuses[(string)$supplierId];
                    }
                    // Default to 'pending' if status not found
                    if ($supplierStatus === null) {
                        $supplierStatus = 'pending';
                    }
                    
                    $supplierData = [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'supplier_type' => $supplier->supplier_type ?? null,
                        'email' => $supplier->email ?? null,
                        'phone' => $supplier->phone ?? null,
                        'address' => $supplier->address ?? null,
                        'tin_number' => $supplier->tin_number ?? null,
                        'status' => $supplierStatus,
                    ];
                }
            }
            
            // Get product status - for LPO, get supplier-specific status
            $productStatus = null;
            if ($productStoreEnum === StoreEnum::LPO) {
                $supplierId = $supplierMapping[(string)$product->id] ?? null;
                if ($supplierId) {
                    $productStatus = $order->getProductStatus($product->store, $supplierId);
                    // Default to 'pending' if status not found for this supplier
                    if ($productStatus === null) {
                        $productStatus = 'pending';
                    }
                } else {
                    $productStatus = 'pending';
                }
            } else {
                $productStatus = $order->getProductStatus($product->store);
            }
            
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
            
            $productData = [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
                'type_name' => $productStoreEnum?->getName() ?? 'Hardware Store',
                'product_status' => $productStatus,
                'quantity' => $quantity,
                'unit_type' => $product->unit_type ?? null,
                'category' => $product->category->name ?? null,
                'is_custom' => 0,
                'images' => $imageUrls,
                'store_type' => $productStoreEnum?->value ?? StoreEnum::HardwareStore->value,
                'materials' => $productMaterials->values(),
                'available_qty' => $availableQty,
                'out_of_stock' => $outOfStock,
            ];
            
            // Add supplier data for LPO products
            if ($supplierData) {
                $productData['supplier'] = $supplierData;
                $productData['supplier_id'] = $supplierData['id'];
            }
            
            $products->push($productData);
        }
        
        // Process custom products
        foreach ($order->customProducts as $customProduct) {
            $customImageUrls = [];
            $connectedProductsData = [];
            
            // Get custom product images
            if ($customProduct->relationLoaded('images') && $customProduct->images && $customProduct->images->isNotEmpty()) {
                foreach ($customProduct->images as $image) {
                    $imagePath = $image->image_path;
                    
                    if (!empty($imagePath)) {
                        if (preg_match('#^https?://#i', $imagePath)) {
                            $customImageUrls[] = $imagePath;
                        } else {
                            $customImageUrls[] = Storage::disk('public')->exists($imagePath)
                                ? url(Storage::url($imagePath))
                                : url('storage/' . $imagePath);
                        }
                    }
                }
            }
            
            $productDetails = $customProduct->product_details ?? [];
            
            // Get all product IDs from both product_ids column and product_details.product_id
            $productIds = $customProduct->getAllProductIds();
            
            // Process connected products from product_ids
            if (!empty($productIds)) {
                foreach ($productIds as $productId) {
                    // Load the connected product model
                    $connectedProductModel = Product::with('category', 'productImages')->find($productId);
                    
                    if ($connectedProductModel) {
                        $connectedImageUrls = [];
                        
                        // Get connected product images
                        if ($connectedProductModel->relationLoaded('productImages') && $connectedProductModel->productImages && $connectedProductModel->productImages->isNotEmpty()) {
                            foreach ($connectedProductModel->productImages->sortBy('order') as $productImage) {
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
                        
                        // Fallback to product image if no product images
                        if (empty($connectedImageUrls) && $connectedProductModel->image) {
                            $connectedImageUrls[] = url(Storage::url($connectedProductModel->image));
                        }
                        
                        // Get quantity from custom product's connected_products array
                        // Custom product connected products are stored separately from regular order products
                        // This allows regular products and custom product connected products to have separate quantities
                        // Example: Regular p2 can have quantity 15, and custom product's connected p2 can have quantity 5
                        $quantity = 0;
                        $connectedProducts = $productDetails['connected_products'] ?? [];
                        if (is_array($connectedProducts) && !empty($connectedProducts)) {
                            // Find this product in connected_products array
                            foreach ($connectedProducts as $connectedProductItem) {
                                if (isset($connectedProductItem['product_id']) && (int) $connectedProductItem['product_id'] === $productId) {
                                    $quantity = (int) ($connectedProductItem['quantity'] ?? 0);
                                    break;
                                }
                            }
                        }
                        
                        // Fallback: If not found in connected_products, try legacy product_details quantity
                        // This handles backward compatibility for custom products created before connected_products was added
                        if ($quantity == 0) {
                            // Check if this is a single product custom product (product_id matches)
                            $productDetailsProductId = $productDetails['product_id'] ?? null;
                            if ($productDetailsProductId) {
                                if (is_array($productDetailsProductId)) {
                                    // Multiple products - check if this product_id is in the array
                                    if (in_array($productId, array_map('intval', $productDetailsProductId))) {
                                        $quantity = (int) ($productDetails['quantity'] ?? 0);
                                    }
                                } else {
                                    // Single product - check if product_id matches
                                    if ((int) $productDetailsProductId === $productId) {
                                        $quantity = (int) ($productDetails['quantity'] ?? 0);
                                    }
                                }
                            }
                        }
                        
                        // Determine store enum safely for the connected product
                        $connectedProductStore = $connectedProductModel->store ?? null;
                        $connectedProductStoreEnum = null;
                        if ($connectedProductStore instanceof StoreEnum) {
                            $connectedProductStoreEnum = $connectedProductStore;
                        } elseif ($connectedProductStore !== null && (is_string($connectedProductStore) || is_int($connectedProductStore))) {
                            $connectedProductStoreEnum = StoreEnum::tryFrom((string)$connectedProductStore);
                        }

                        $productStatus = $order->getProductStatus($connectedProductStoreEnum?->value ?? $connectedProductStore) ?? 'pending';
                        
                        // Get materials connected to this product from product_materials table
                        $productMaterials = collect();
                        
                        // Load materials relationship if not already loaded
                        if (!$connectedProductModel->relationLoaded('materials')) {
                            $connectedProductModel->load('materials.category', 'materials.productImages');
                        }
                        
                        foreach ($connectedProductModel->materials as $material) {
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
                        // Also fetch material information from products table
                        $materialsArray = [];
                        if (isset($productDetails['materials']) && is_array($productDetails['materials'])) {
                            foreach ($productDetails['materials'] as $material) {
                                $materialId = isset($material['material_id']) ? (int) $material['material_id'] : null;
                                
                                if (!$materialId) {
                                    continue;
                                }
                                
                                // Get material from products table
                                $materialModel = Product::where('id', $materialId)
                                    ->whereIn('is_product', [0, 2]) // Materials have is_product = 0 or 2
                                    ->with('category', 'productImages')
                                    ->first();
                                
                                $processedMaterial = [
                                    'material_id' => $materialId,
                                    'actual_pcs' => $material['actual_pcs'] ?? null,
                                ];
                                
                                // Add material information if found
                                if ($materialModel) {
                                    $materialImageUrls = [];
                                    
                                    // Get material images
                                    if ($materialModel->relationLoaded('productImages') && $materialModel->productImages && $materialModel->productImages->isNotEmpty()) {
                                        foreach ($materialModel->productImages->sortBy('order') as $productImage) {
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
                                    if (empty($materialImageUrls) && $materialModel->image) {
                                        $materialImageUrls[] = url(Storage::url($materialModel->image));
                                    }
                                    
                                    $processedMaterial['material_name'] = $materialModel->product_name;
                                    $processedMaterial['category'] = $materialModel->category->name ?? null;
                                    $processedMaterial['images'] = $materialImageUrls;
                                    $processedMaterial['unit_type'] = $materialModel->unit_type ?? null;
                                }
                                
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
                                
                                // Add calculated_quantity if it exists
                                if (isset($material['calculated_quantity']) && $material['calculated_quantity'] !== null) {
                                    $processedMaterial['calculated_quantity'] = (float) $material['calculated_quantity'];
                                }
                                
                                // Get quantity (prioritize cal_qty, then calculated_quantity, then actual_pcs)
                                $materialQuantity = $material['cal_qty'] ?? 
                                                   $material['calculated_quantity'] ?? 
                                                   $material['actual_pcs'] ?? 
                                                   0;
                                $processedMaterial['quantity'] = (float) $materialQuantity;
                                
                                $materialsArray[] = $processedMaterial;
                            }
                        }
                        
                        // Merge product_materials with custom product materials (from product_details)
                        $allMaterials = $productMaterials->merge(collect($materialsArray))->values();

                        // Calculate available quantity and out of stock status
                        $availableQty = (int) $connectedProductModel->total_stock_quantity;
                        $outOfStock = $availableQty <= 0 ? 1 : 0;
                        
                        $connectedProductData = [
                            'product_id' => $connectedProductModel->id,
                            'product_name' => $connectedProductModel->product_name,
                            'type_name' => $connectedProductStoreEnum?->getName(),
                            'product_status' => $productStatus,
                            'quantity' => $quantity,
                            'unit_type' => $connectedProductModel->unit_type ?? null,
                            'category' => $connectedProductModel->category->name ?? null,
                            'materials' => $allMaterials,
                            'is_custom' => 0,
                            'custom_images' => $connectedImageUrls,
                            'images' => $connectedImageUrls,
                            'store_type' => $connectedProductStoreEnum?->value ?? StoreEnum::WarehouseStore->value,
                            'available_qty' => $availableQty,
                            'out_of_stock' => $outOfStock,
                        ];
                        
                        $connectedProductsData[] = $connectedProductData;
                        // Add to root level custom_products collection (for root level display)
                        $rootCustomProducts->push($connectedProductData);
                        // DO NOT add to regular products - these should only be in custom_products
                    }
                }
            }
            
            // Get display product ID (first product ID from all sources)
            $displayProductId = $customProduct->getDisplayProductId();
            $displayProduct = $displayProductId ? Product::with('category')->find($displayProductId) : null;
            
            // Process materials array with dynamic m* fields extraction (m1, m2, m3, m4, etc.)
            // Also fetch material information from products table and products that use these materials
            $materialsArray = [];
            if (isset($productDetails['materials']) && is_array($productDetails['materials'])) {
                // Get materials with quantities using the new method
                $materialsWithQuantities = $customProduct->getMaterialsWithQuantitiesFromProductDetails();
                
                foreach ($productDetails['materials'] as $material) {
                    $materialId = isset($material['material_id']) ? (int) $material['material_id'] : null;
                    
                    if (!$materialId) {
                        continue;
                    }
                    
                    // Get material from products table
                    $materialModel = Product::where('id', $materialId)
                        ->whereIn('is_product', [0, 2]) // Materials have is_product = 0 or 2
                        ->with('category', 'productImages')
                        ->first();
                    
                    $processedMaterial = [
                        'material_id' => $materialId,
                        'actual_pcs' => $material['actual_pcs'] ?? null,
                    ];
                    
                    // Add material information if found
                    if ($materialModel) {
                        $materialImageUrls = [];
                        
                        // Get material images
                        if ($materialModel->relationLoaded('productImages') && $materialModel->productImages && $materialModel->productImages->isNotEmpty()) {
                            foreach ($materialModel->productImages->sortBy('order') as $productImage) {
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
                        if (empty($materialImageUrls) && $materialModel->image) {
                            $materialImageUrls[] = url(Storage::url($materialModel->image));
                        }
                        
                        $processedMaterial['material_name'] = $materialModel->product_name;
                        $processedMaterial['category'] = $materialModel->category->name ?? null;
                        $processedMaterial['images'] = $materialImageUrls;
                        $processedMaterial['unit_type'] = $materialModel->unit_type ?? null;
                    }
                    
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
                    
                    // Add calculated_quantity if it exists
                    if (isset($material['calculated_quantity']) && $material['calculated_quantity'] !== null) {
                        $processedMaterial['calculated_quantity'] = $material['calculated_quantity'];
                    }
                    
                    // Get quantity (prioritize cal_qty, then calculated_quantity, then actual_pcs)
                    $quantity = $material['cal_qty'] ?? 
                               $material['calculated_quantity'] ?? 
                               $material['actual_pcs'] ?? 
                               0;
                    $processedMaterial['quantity'] = $quantity;
                    
                    $materialsArray[] = $processedMaterial;
                }
                
                // Get products that use these materials and their quantities
                $productsFromMaterials = $customProduct->getProductsWithQuantitiesFromMaterials();
                
                // Add products from materials to connected products if not already present
                foreach ($productsFromMaterials as $productData) {
                    $productId = $productData['product_id'];
                    $productQuantity = $productData['quantity'];
                    $product = $productData['product'];
                    
                    // Check if product is already in connectedProductsData
                    $existingProduct = collect($connectedProductsData)->firstWhere('product_id', $productId);
                    
                    if (!$existingProduct) {
                        // Product not in connected products, add it
                        $productImageUrls = [];
                        
                        // Get product images
                        if ($product->relationLoaded('productImages') && $product->productImages && $product->productImages->isNotEmpty()) {
                            foreach ($product->productImages->sortBy('order') as $productImage) {
                                $imagePath = $productImage->image_url;
                                
                                if (!empty($imagePath)) {
                                    if (preg_match('#^https?://#i', $imagePath)) {
                                        $productImageUrls[] = $imagePath;
                                    } else {
                                        $productImageUrls[] = url(Storage::url($imagePath));
                                    }
                                }
                            }
                        }
                        
                        // Fallback to product image if no product images
                        if (empty($productImageUrls) && $product->image) {
                            $productImageUrls[] = url(Storage::url($product->image));
                        }
                        
                        // Get quantity from order products (aggregated) or use calculated quantity from materials
                        $orderProduct = $order->products->firstWhere('id', $productId);
                        $finalQuantity = 0;
                        if ($orderProduct && isset($orderProduct->pivot->quantity)) {
                            // Use aggregated quantity from order_products table
                            $finalQuantity = (int) $orderProduct->pivot->quantity;
                        } else {
                            // Use calculated quantity from materials
                            $finalQuantity = (int) round($productQuantity);
                        }
                        
                        $productStatus = $order->getProductStatus($product->store) ?? 'pending';
                        
                        // Get materials connected to this product from product_materials table
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
                            $materialQuantity = ($material->pivot->quantity ?? 0);
                            // Multiply by product quantity in order
                            $totalMaterialQuantity = $materialQuantity * $finalQuantity;
                            
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
                        $stockStatus = calculateStockStatus(
                            $product->total_stock_quantity,
                            $product->low_stock_threshold
                        );
                        $connectedProductData = [
                            'product_id' => $productId,
                            'product_name' => $product->product_name,
                            'type_name' => $product->store?->getName(),
                            'product_status' => $productStatus,
                            'quantity' => $finalQuantity,
                            'unit_type' => $product->unit_type ?? null,
                            'category' => $product->category->name ?? null,
                            'materials' => $productMaterials->values(),
                            'is_custom' => 0,
                            'custom_images' => $productImageUrls,
                            'images' => $productImageUrls,
                            'store_type' => $product->store?->value ?? StoreEnum::WarehouseStore->value,
                            'available_qty' => $availableQty,
                            'out_of_stock' => $stockStatus['out_of_stock'],
                            'low_stock' => $stockStatus['low_stock'],
                        ];
                        
                        $connectedProductsData[] = $connectedProductData;
                        // Add to root level custom_products collection (for root level display)
                        $rootCustomProducts->push($connectedProductData);
                    } else {
                        // Product already exists, update quantity if needed
                        // Quantity is already aggregated in order_products, so we keep it as is
                    }
                }
            }
            
            // Safely calculate stock status for display product (it may be missing)
            if ($displayProduct) {
                $stockStatus = calculateStockStatus(
                    $displayProduct->total_stock_quantity,
                    $displayProduct->low_stock_threshold
                );

                $availableQty = (int) $displayProduct->total_stock_quantity;
            } else {
                // Fallback when display product no longer exists
                $stockStatus = [
                    'out_of_stock' => 0,
                    'low_stock' => 0,
                ];
                $availableQty = 0;
            }

            $products->push([
                'product_id' => $displayProductId,
                'custom_product_id' => $customProduct->id,
                'type_name' => 'Workshop Store',
                'is_custom' => 1,
                'product_status' => 'pending',
                'quantity' => $productDetails['quantity'] ?? null,
                'unit_id' => $productDetails['unit_id'] ?? null,
                'materials' => $materialsArray,
                'custom_note' => $customProduct->custom_note ?? null,
                'custom_images' => $customImageUrls,
                'store_type' => StoreEnum::WarehouseStore->value,
                'custom_products' => $connectedProductsData,
                // 'low_stock' => $stockStatus['low_stock'],
                // 'out_of_stock' => $stockStatus['out_of_stock'],
                // 'available_qty' => $availableQty,
            ]);
        }
        
        // Get all product IDs from root custom_products (connected products from custom products)
        $customProductIds = $rootCustomProducts->pluck('product_id')->filter()->unique()->toArray();
        
        // Get all product IDs from regular order_products (products added as regular products)
        $regularProductIds = $order->products->pluck('id')->filter()->unique()->toArray();
        
        // Filter out products from regular products that exist ONLY in custom_products (not in regular order)
        // 
        // IMPORTANT: 
        // - If a product exists in BOTH regular order AND custom product → show in BOTH places
        // - If a product exists ONLY in custom product (not in regular order) → show ONLY under custom product
        // 
        // Example scenario:
        // - Order create: regular p1=>10, p2=>15
        // - Custom product created: connected to p2 (qty: 5) and p3 (qty: 8)
        // - Result:
        //   * p1 (quantity: 10) appears in regular products (not in custom_products)
        //   * p2 (quantity: 15) appears in regular products AND p2 (quantity: 5) appears in custom_products
        //   * p3 (quantity: 8) appears ONLY in custom_products (not in regular products - filtered out)
        $products = $products->reject(function ($item) use ($customProductIds, $regularProductIds) {
            // Only filter regular products (is_custom = 0), not custom products themselves
            if (isset($item['is_custom']) && $item['is_custom'] == 0) {
                $productId = $item['product_id'] ?? null;
                
                if ($productId && in_array($productId, $customProductIds)) {
                    // Product exists in custom_products
                    // Only filter out if it does NOT exist in regular order_products
                    // (meaning it exists ONLY in custom product, not in regular order)
                    return !in_array($productId, $regularProductIds);
                }
            }
            return false;
        })->values();
        
        // Ensure all quantities in the final products array are correct
        // Regular products that remain should show their quantities from order_products
        // Custom products and their connected products already have correct aggregated quantities
        
        // Get LPO suppliers information
        $lpoSuppliers = collect();
        $lpoProductStatus = $order->product_status['lpo'] ?? [];
        if (is_array($lpoProductStatus) && !empty($lpoProductStatus)) {
            foreach ($lpoProductStatus as $supplierId => $status) {
                if ($suppliers->has($supplierId)) {
                    $supplier = $suppliers->get($supplierId);
                    $lpoSuppliers->push([
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'supplier_type' => $supplier->supplier_type ?? null,
                        'email' => $supplier->email ?? null,
                        'phone' => $supplier->phone ?? null,
                        'address' => $supplier->address ?? null,
                        'tin_number' => $supplier->tin_number ?? null,
                        'status' => $status,
                    ]);
                }
            }
        }
        
        return [
            'id' => $order->id,
            'order_slug' => 'ORD' . $order->id,
            'site_id' => $order->site->id ?? null,
            'site_name' => $order->site->name ?? null,
            'site_location' => $order->site->location ?? null,
            'status' => $this->calculateOrderStatus($order),
            'delivery_status' => $this->calculateOrderStatus($order),
            'priority' => $order->priority ?? null,
            'note' => $order->note ?? null,
            'rejected_note' => $order->rejected_note ?? null,
            'customer_image' => null,
            'driver_name' => $order->driver_name ?? null,
            'vehicle_number' => $order->vehicle_number ?? null,
            'expected_delivery_date' => $this->formatDate($order->expected_delivery_date),
            'requested_date' => $this->formatDate($order->created_at),
            'created_at' => $this->formatDate($order->created_at),
            'updated_at' => $this->formatDate($order->updated_at),
            'products' => $products->values(),
            // 'lpo_suppliers' => $lpoSuppliers->values(), // LPO suppliers with their statuses
            // 'product_status' => $order->product_status ?? $order->initializeProductStatus(),
        ];
    }
    
    /**
     * Calculate order status based on hardware, warehouse, and LPO product statuses
     * Uses the Order model's calculateOrderStatusFromProductStatuses method for consistency
     * 
     * Rules:
     * 1. Single product type → use that type's status directly
     * 2. Multiple product types:
     *    - Pending: if ANY is pending
     *    - Approved: if ALL are approved OR any one rejected + rest approved
     *    - Delivered: if ALL are delivered OR any one rejected + rest delivered
     *    - Rejected: if ALL are rejected
     *    - Out for delivery: if ALL are out for delivery
     *    - Special cases:
     *      * outfordelivery + rejected = Out for delivery
     *      * delivered + rejected = Delivered
     *      * outfordelivery + in_transit = Out for delivery
     * 
     * @param \App\Models\Order $order
     * @return string
     */
    private function calculateOrderStatus($order): string
    {
        // Use the Order model's method for consistency
        // This ensures the same logic is used everywhere
        return $order->calculateOrderStatusFromProductStatuses();
    }
    
    /**
     * Map product status to order status
     * 
     * @param string $productStatus
     * @return string
     */
    private function mapProductStatusToOrderStatus(string $productStatus): string
    {
        return match($productStatus) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'pending' => 'pending',
            'outofdelivery' => 'outofdelivery',
            'delivered' => 'delivered',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }
    
    /**
     * Format date as "Today", "Yesterday", or "d/m/Y"
     */
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
}