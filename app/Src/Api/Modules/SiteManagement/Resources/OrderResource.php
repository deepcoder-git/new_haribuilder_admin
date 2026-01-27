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
                    $connectedProduct = Product::with('category', 'productImages')->find($productId);
                    
                    if ($connectedProduct) {
                        $connectedImageUrls = [];
                        
                        // Get connected product images
                        if ($connectedProduct->relationLoaded('productImages') && $connectedProduct->productImages && $connectedProduct->productImages->isNotEmpty()) {
                            foreach ($connectedProduct->productImages->sortBy('order') as $productImage) {
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
                        if (empty($connectedImageUrls) && $connectedProduct->image) {
                            $connectedImageUrls[] = url(Storage::url($connectedProduct->image));
                        }
                        
                        // Get quantity from order products - this is AGGREGATED quantity
                        // IMPORTANT: This quantity includes both:
                        // 1. Quantity from regular order (if product was added as regular product)
                        // 2. Quantity from custom product (if product was added to custom product)
                        // Example: If p2 was added as 15 in regular order and 5 in custom product, 
                        //          this will show 20 (aggregated total: 15 + 5 = 20)
                        // 
                        // This aggregated quantity is managed by updateCustomProduct in OrderController
                        // which adds/subtracts quantities correctly when custom products are updated
                        $orderProduct = $order->products->firstWhere('id', $productId);
                        $quantity = 0;
                        if ($orderProduct && isset($orderProduct->pivot->quantity)) {
                            // Always use aggregated quantity from order_products table
                            // This is the FULL aggregated quantity (regular + custom)
                            $quantity = (int) $orderProduct->pivot->quantity;
                        } else {
                            // Fallback: Try to get from product_details if not in order_products yet
                            // This happens for new custom products that haven't been synced yet
                            // But note: once synced, order_products will have the aggregated quantity
                            $quantity = (int) ($productDetails['quantity'] ?? 0);
                            
                            // If still 0, try to get from custom product's product_details directly
                            if ($quantity == 0) {
                                // Check if this product has a specific quantity in the custom product's product_details
                                // This is a fallback for edge cases
                                $quantity = (int) ($productDetails['quantity'] ?? 0);
                            }
                        }
                        
                        $productStatus = $order->getProductStatus($connectedProduct->store) ?? 'pending';
                        
                        // Get materials connected to this product from product_materials table
                        $productMaterials = collect();
                        
                        // Load materials relationship if not already loaded
                        if (!$connectedProduct->relationLoaded('materials')) {
                            $connectedProduct->load('materials.category', 'materials.productImages');
                        }
                        
                        foreach ($connectedProduct->materials as $material) {
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
                        $availableQty = (int) $connectedProduct->total_stock_quantity;
                        $outOfStock = $availableQty <= 0 ? 1 : 0;
                        
                        $connectedProductData = [
                            'product_id' => $connectedProduct->id,
                            'product_name' => $connectedProduct->product_name,
                            'type_name' => $connectedProduct->store?->getName(),
                            'product_status' => $productStatus,
                            'quantity' => $quantity,
                            'unit_type' => $connectedProduct->unit_type ?? null,
                            'category' => $connectedProduct->category->name ?? null,
                            'materials' => $allMaterials,
                            'is_custom' => 0,
                            'custom_images' => $connectedImageUrls,
                            'images' => $connectedImageUrls,
                            'store_type' => $connectedProduct->store?->value ?? StoreEnum::WarehouseStore->value,
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
                        $outOfStock = $availableQty <= 0 ? 1 : 0;
                        
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
                            'out_of_stock' => $outOfStock,
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
            ]);
        }
        
        // Get all product IDs from root custom_products to filter duplicates
        // These are products that are connected to ANY custom product
        // Example: If p2 is connected to a custom product, p2 will be in this list
        $customProductIds = $rootCustomProducts->pluck('product_id')->filter()->unique()->toArray();
        
        // Filter out products from regular products that exist in custom_products
        // 
        // IMPORTANT: If a product appears both in regular order AND in custom product,
        // it should ONLY appear in the custom_products section with aggregated quantity.
        // 
        // Example scenario:
        // - Order create: regular p1=>10, p2=>15, custom product added
        // - Order edit: custom product edit add p2=>5
        // - Result:
        //   * p1 (quantity: 10) appears in regular products (not in custom_products)
        //   * p2 (quantity: 20 = 15+5) appears ONLY in custom_products section with aggregated quantity
        //   * p2 does NOT appear in regular products (filtered out)
        // 
        // The aggregated quantity is stored in order_products table and includes both:
        // - Quantity from regular order (if product was added as regular product)
        // - Quantity from custom product (if product was added to custom product)
        $products = $products->reject(function ($item) use ($customProductIds) {
            // Only filter regular products (is_custom = 0), not custom products themselves
            if (isset($item['is_custom']) && $item['is_custom'] == 0) {
                // Filter out if this product_id exists in custom_products connected products
                // The aggregated quantity will be shown in the custom_products section
                // This ensures p2 shows quantity 20 (15+5) in custom_products, not in regular products
                return isset($item['product_id']) && in_array($item['product_id'], $customProductIds);
            }
            return false;
        })->values();
        
        // Ensure all quantities in the final products array are correct
        // Regular products that remain should show their quantities from order_products
        // Custom products and their connected products already have correct aggregated quantities
        
        // Format customer image
        $customerImageUrl = null;
        if ($order->customer_image) {
            $customerImageUrl = Storage::disk('public')->exists($order->customer_image)
                ? url(Storage::url($order->customer_image))
                : url('storage/' . $order->customer_image);
        }

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
            'customer_image' => $customerImageUrl,
            'driver_name' => $order->driver_name ?? null,
            'vehicle_number' => $order->vehicle_number ?? null,
            'expected_delivery_date' => $this->formatDate($order->expected_delivery_date ?? $order->sale_date),
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
