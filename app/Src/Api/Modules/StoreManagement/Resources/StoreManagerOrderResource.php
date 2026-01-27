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

class StoreManagerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $role = $user?->getRole();
        
        $regularProducts = collect();
        
        $currentOrder = $this->resource;
        
        // Process only the single order (no parent/child relationships)
        $order = $currentOrder;
        
        // Ensure products relationship is loaded
        if (!$order->relationLoaded('products')) {
            $order->load('products.category', 'products.productImages');
        }
        
        // Use order products relationship instead of direct DB query
        foreach ($order->products as $product) {
            if ($this->shouldDisplayProduct($product, $role)) {
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
                    
                    // Use product's store type instead of order store
                    $productStore = $product->store ?? null;
                    $productStoreEnum = $productStore instanceof StoreEnum ? $productStore : StoreEnum::tryFrom($productStore);
                    
                    // Calculate stock status using helper function
                    $stockStatus = calculateStockStatus(
                        $product->total_stock_quantity,
                        $product->low_stock_threshold
                    );
                    
                    // Calculate available quantity
                    $availableQty = (int) $product->total_stock_quantity;
                    
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
                    
                    $regularProducts->push([
                        'product_id' => $product->id,
                        'product_name' => $product->product_name,
                        'type_name' => $productStoreEnum?->getName() ?? 'Workshop Store',
                        'product_status' => $order->getProductStatus($product->store),
                        'quantity' => $quantity,
                        'unit_type' => $product->unit_type,
                        'category' => $product->category->name ?? null,
                        'is_custom' => 0,
                        'custom_note' => null,
                        'custom_image' => null,
                        'custom_images' => [],
                        'images' => $imageUrls,
                        'store_type' => $productStoreEnum?->value ?? StoreEnum::WarehouseStore->value,
                        'low_stock' => $stockStatus['low_stock'],
                        'out_of_stock' => $stockStatus['out_of_stock'],
                        'available_qty' => $availableQty,
                        'materials' => $productMaterials->values(),
                    ]);
                }
        }
            
        // All products in orders assigned to this store manager are displayed
        // Order filtering is managed via store_manager_role and store fields at the API level
        $allProducts = $regularProducts->values();
        
        // Use the current order directly (no parent/child relationships)
        $rootOrder = $currentOrder;
        
        return [
            'id' => $rootOrder->id,
            'order_slug' => 'ORD'.$rootOrder->id,
            // 'parent_order_id' => $rootOrder->parent_order_id ?? null,
            'site_id' => $rootOrder->site->id ?? null,
            'site_name' => $rootOrder->site->name ?? null,
            'site_location' => $rootOrder->site->location ?? null,
            'status' => $rootOrder->status?->value ?? 'pending',
            'delivery_status' => $order->getProductStatus(StoreEnum::HardwareStore->value),
            'priority' => $rootOrder->priority ?? null,
            'note' => $rootOrder->note ?? null,
            'rejected_note' => $rootOrder->rejected_note ?? null,
            'customer_image' => null,
            'driver_name' => $rootOrder->driver_name ?? null,
            'vehicle_number' => $rootOrder->vehicle_number ?? null,
            'expected_delivery_date' => $this->formatDate($rootOrder->expected_delivery_date),
            'requested_date' => $this->formatDate($rootOrder->created_at),
            'created_at' => $this->formatDateTime($rootOrder->created_at),
            'updated_at' => $this->formatDateTime($rootOrder->updated_at),
            'products' => $allProducts,
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
        
        // Get product's store type
        $productStore = $product->store ?? null;
        
        // Handle both enum instance and string value
        $productStoreValue = null;
        if ($productStore instanceof StoreEnum) {
            $productStoreValue = $productStore->value;
        } elseif (is_string($productStore) || is_int($productStore)) {
            // Try to find the enum by value
            $productStoreEnum = StoreEnum::tryFrom((string)$productStore);
            if ($productStoreEnum) {
                $productStoreValue = $productStoreEnum->value;
            } else {
                // Fallback: check all enum cases to find a match
                foreach (StoreEnum::cases() as $case) {
                    if ($case->value === $productStore || $case->name === $productStore) {
                        $productStoreValue = $case->value;
                        break;
                    }
                }
            }
        }
        
        // If we couldn't determine the store type, don't show the product
        if (!$productStoreValue) {
            return false;
        }
        
        // Filter products based on store manager's role
        // Compare using enum value for more reliable comparison
        $roleValue = $role->value ?? null;
        
        if ($roleValue === RoleEnum::StoreManager->value) {
            // Hardware Store Manager: show only hardware store products
            return $productStoreValue === StoreEnum::HardwareStore->value;
        } elseif ($roleValue === RoleEnum::WorkshopStoreManager->value) {
            // Workshop store Manager: show only Workshop store products
            return $productStoreValue === StoreEnum::WarehouseStore->value;
        }
        
        // For other roles, show all products
        return true;
    }
}