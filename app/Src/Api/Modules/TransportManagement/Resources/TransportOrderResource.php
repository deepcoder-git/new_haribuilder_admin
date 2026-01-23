<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\TransportManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Utility\Enums\StoreEnum;
use App\Models\Product;
use Carbon\Carbon;
use App\Utility\Enums\RoleEnum;

class TransportOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $role = $user?->getRole();
        
        $regularProducts = collect();
        $customProducts = collect();
        $getproductDetails = collect();
        
        $currentOrder = $this->resource;
        // parent_order_id has been removed - orders are now managed independently by is_lpo flag
        // Process only the current order
        $orders = collect([$currentOrder]);

        foreach ($orders as $order) {
            $orderProducts = DB::table('order_products')
                ->where('order_id', $order->id)
                ->get();

            foreach ($orderProducts as $orderProduct) {
                $product = $order->products->firstWhere('id', $orderProduct->product_id);
                if ($product instanceof Product && $this->shouldDisplayProduct($product, $role)) {
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
                    
                    $regularProducts->push([
                        'product_id' => $product->id,
                        'product_name' => $product->product_name,
                        'type_name' => $product->store?->getName() ?? 'Workshop Store',
                        'product_status' => $order->delivery_status ?? 'pending',
                        'quantity' => (int) $orderProduct->quantity,
                        'unit' => $product->unit_type,
                        'category' => $product->category->name ?? null,
                        'is_custom' => 0,
                        'custom_note' => null,
                        'custom_image' => null,
                        'custom_images' => [],
                        'images' => $imageUrls,
                        'store_type' => $product->store?->value ?? StoreEnum::WarehouseStore->value,
                    ]);
                }
            }
            
            $customProductsModel = \App\Models\OrderCustomProduct::where('order_id', $order->id)->get();
            
            foreach ($customProductsModel as $customProduct) {
                $imageUrls = [];
                
                $productImages = DB::table('order_custom_product_images')
                    ->where('order_custom_product_id', $customProduct->id)
                    ->orderBy('sort_order')
                    ->get();
                
                foreach ($productImages as $productImage) {
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
                
                $productDetails = $customProduct->product_details ?? [];
                $product = $customProduct->product;
                $connectedProductsData = [];

                // Get all product IDs from both product_ids column and product_details.product_id
                $productIds = $customProduct->getAllProductIds();

                // Process all product_ids (also check for actual_pcs if no product_ids)
                if (!empty($productIds) || (isset($productDetails['actual_pcs']) && $productDetails['actual_pcs'] > 0)) {
                    foreach ($productIds as $productId) {
                        $getproductRes = Product::find($productId);
                        
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

                            // Get quantity from order_products for this specific product
                            $orderProduct = DB::table('order_products')
                                ->where('order_id', $order->id)
                                ->where('product_id', $productId)
                                ->first();
                            
                            $connectedProductData = [
                                'product_id' => $getproductRes->id,
                                'product_name' => $getproductRes->product_name,
                                'type_name' => $getproductRes->store?->getName() ?? 'Workshop Store',
                                'product_status' => $order->delivery_status ?? 'pending',
                                'quantity' => $orderProduct ? (int) $orderProduct->quantity : ($getproductRes->quantity ?? 0),
                                'unit' => $getproductRes->unit_type ?? null,
                                'category' => $getproductRes->category->name ?? null,
                                'is_custom' => 0,
                                'custom_images' => [],
                                'images' => $connectedImageUrls,
                                'store_type' => $getproductRes->store?->value ?? StoreEnum::WarehouseStore->value,
                            ];

                            $connectedProductsData[] = $connectedProductData;
                            $getproductDetails->push($connectedProductData);
                        }
                    }
                }

                // Get display product ID (first product ID from all sources)
                $displayProductId = $customProduct->getDisplayProductId();
                
                // Get the first product for display name if available
                $displayProduct = null;
                if ($displayProductId) {
                    $displayProduct = Product::find($displayProductId);
                }

                // Process materials array with dynamic m* fields extraction (m1, m2, m3, m4, etc.)
                $materialsArray = [];
                if (isset($productDetails['materials']) && is_array($productDetails['materials'])) {
                    foreach ($productDetails['materials'] as $material) {
                        $processedMaterial = [
                            'material_id' => $material['material_id'] ?? null,
                            'actual_pcs' => $material['actual_pcs'] ?? null,
                        ];
                        
                        // Dynamically extract all m* fields (m1, m2, m3, m4, etc.) and sort them
                        $mFields = [];
                        foreach ($material as $key => $value) {
                            if (preg_match('/^m\d+$/', $key)) {
                                // Extract the number from m* field (e.g., m1 -> 1, m2 -> 2)
                                preg_match('/^m(\d+)$/', $key, $matches);
                                $mNumber = isset($matches[1]) ? (int)$matches[1] : 0;
                                $mFields[$mNumber] = ['key' => $key, 'value' => $value];
                            }
                        }
                        
                        // Sort m* fields by number (m1, m2, m3, m4, etc.) and add to processed material
                        ksort($mFields);
                        foreach ($mFields as $mField) {
                            $processedMaterial[$mField['key']] = $mField['value'];
                        }
                        
                        $materialsArray[] = $processedMaterial;
                    }
                }

                $customProductData = [
                    'custom_product_id' => $customProduct->id,
                    'product_id' => $displayProductId,
                    'product_name' => $displayProduct?->product_name ?? ($product instanceof Product ? $product->product_name : 'Custom Product'),
                    'type_name' => 'Workshop Store',
                    'product_status' => $order->delivery_status ?? 'pending',
                    'quantity' => (int) ($productDetails['quantity'] ?? 0),
                    'calqty' => (int) ($productDetails['quantity'] ?? 0),
                    'pcs' => $productDetails['pcs'] ?? null,
                    'unit' => $customProduct->unit?->name ?? null,
                    'unit_id' => $productDetails['unit_id'] ?? null,
                    'category' => $displayProduct?->category->name ?? (($product instanceof Product) ? ($product->category->name ?? null) : null),
                    'h1' => $productDetails['h1'] ?? null,
                    'h2' => $productDetails['h2'] ?? null,
                    'w1' => $productDetails['w1'] ?? null,
                    'w2' => $productDetails['w2'] ?? null,
                    'is_custom' => 1,
                    'custom_note' => $customProduct->custom_note ?? '',
                    'custom_images' => $imageUrls,
                    'store_type' => StoreEnum::WarehouseStore->value,
                    'materials' => $materialsArray,
                    'custom_products' => $connectedProductsData,
                ];
                
                $customProducts->push($customProductData);
            }
        }

        $regularProducts = $regularProducts->merge($getproductDetails)->values();
        
        // Allow both regular products and custom products to be displayed
        // Even if they reference the same product_id, show both entries
        // Use unique based on product_id + is_custom to allow same product_id with different types
        $productsArray = $customProducts->merge($regularProducts)->values()->unique(function ($item) {
            return ($item['product_id'] ?? $item['custom_product_id'] ?? '') . '_' . ($item['is_custom'] ?? 0);
        });
        
        // Use the current order directly (no parent/child relationships)
        $rootOrder = $currentOrder;
        
        $customerImageUrl = null;
        if ($rootOrder->customer_image) {
            $customerImageUrl = Storage::disk('public')->exists($rootOrder->customer_image) 
                ? url(Storage::url($rootOrder->customer_image))
                : url('storage/' . $rootOrder->customer_image);
        }
        
        return [
            'id' => $currentOrder->id,
            'order_slug' => 'ORD'.$currentOrder->id,
            'site_id' => $rootOrder->site->id ?? null,
            'site_name' => $rootOrder->site->name ?? null,
            'site_location' => $rootOrder->site->location ?? null,
            'status' => $currentOrder->status?->value ?? 'pending',
            'delivery_status' => $currentOrder->delivery_status ?? 'pending',
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
        if (!$role) {
            return true;
        }

        // For TransportManager: only show Workshop store and LPO products, exclude hardware store
        if ($role === RoleEnum::TransportManager) {
            return $product->store === StoreEnum::WarehouseStore || $product->store === StoreEnum::LPO;
        }

        if ($role === RoleEnum::StoreManager) {
            return $product->store === StoreEnum::HardwareStore;
        }

        if ($role === RoleEnum::WorkshopStoreManager) {
            return $product->store === StoreEnum::WarehouseStore;
        }

        return true;
    }
}

