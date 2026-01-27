<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrderCustomProduct;
use App\Models\OrderCustomProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCustomProductManager
{
    /**
     * Create a custom product with all details
     *
     * @param int $orderId
     * @param array $data Product details including: product_id, h1, h2, w1, w2, quantity, unit_id, custom_note
     * @param array $imagePaths Array of image paths to associate with the product
     * @return OrderCustomProduct
     */
    public function create(int $orderId, array $data, array $imagePaths = []): OrderCustomProduct
    {
        // Prepare product details as JSON
        $productDetails = $this->prepareProductDetails($data);

        // Extract custom_note (not part of product_details)
        $customNote = $data['custom_note'] ?? null;

        // Prepare product_ids (separate column, like admin panel)
        $productIds = $this->prepareProductIds($data['product_ids'] ?? null);

        // Process products array if provided (store in product_details as connected_products)
        // DO NOT sync to order_products table - keep custom product quantities separate from regular order products
        $connectedProducts = [];
        if (isset($data['products']) && is_array($data['products']) && !empty($data['products'])) {
            foreach ($data['products'] as $productData) {
                if (isset($productData['product_id']) && $productData['product_id'] !== null && $productData['product_id'] !== '') {
                    $productId = (int) $productData['product_id'];
                    
                    // Map calqty → quantity if quantity is not already set
                    if (isset($productData['calqty']) && !isset($productData['quantity'])) {
                        $productData['quantity'] = $productData['calqty'];
                    }
                    
                    // Store product data with quantity in connected_products array
                    $productQuantity = $productData['calqty'] ?? $productData['quantity'] ?? 1; // Default to 1 if not provided
                    
                    $connectedProducts[] = [
                        'product_id' => $productId,
                        'quantity' => (int) $productQuantity,
                    ];
                }
            }
        } elseif (!empty($productIds)) {
            // If product_ids are provided but no products array, check if product_ids contain quantity info
            // Support both formats:
            // 1. Simple array: [1, 2, 3] -> use default quantity 1 for each
            // 2. Array of objects: [{"product_id": 1, "quantity": 2}, ...] -> use provided quantities
            // 3. Try to extract quantity from product_details for single product
            
            $hasQuantityInfo = false;
            foreach ($productIds as $index => $item) {
                if (is_array($item) && isset($item['product_id'])) {
                    // Format: [{"product_id": 1, "quantity": 2}, ...]
                    $hasQuantityInfo = true;
                    $productId = (int) $item['product_id'];
                    $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                    $connectedProducts[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity > 0 ? $quantity : 1,
                    ];
                }
            }
            
            if (!$hasQuantityInfo) {
                // Simple array format: [1, 2, 3]
                // Try to extract quantity from product_details or data for single product
                $quantityFromDetails = null;
                if (!empty($productDetails) && isset($productDetails['quantity'])) {
                    $quantityFromDetails = (float) $productDetails['quantity'];
                } elseif (isset($data['quantity']) && $data['quantity'] !== '' && $data['quantity'] !== null) {
                    $quantityFromDetails = (float) $data['quantity'];
                }
                
                // Check if there's a product_quantities mapping
                $productQuantities = $data['product_quantities'] ?? null;
                if (is_array($productQuantities)) {
                    // Use provided quantities mapping
                    foreach ($productIds as $productId) {
                        $productId = (int) $productId;
                        $quantity = isset($productQuantities[$productId]) ? (int) $productQuantities[$productId] : 1;
                        $connectedProducts[] = [
                            'product_id' => $productId,
                            'quantity' => $quantity > 0 ? $quantity : 1,
                        ];
                    }
                } elseif (count($productIds) === 1 && $quantityFromDetails !== null && $quantityFromDetails > 0) {
                    // Single product_id with quantity from product_details
                    $connectedProducts[] = [
                        'product_id' => $productIds[0],
                        'quantity' => (int) $quantityFromDetails,
                    ];
                } else {
                    // Multiple product_ids or no quantity available, default to quantity 1 for each
                    foreach ($productIds as $productId) {
                        $productId = (int) $productId;
                        $connectedProducts[] = [
                            'product_id' => $productId,
                            'quantity' => 1,
                        ];
                    }
                }
            }
        }

        // Store connected products in product_details
        if (!empty($connectedProducts)) {
            $productDetails['connected_products'] = $connectedProducts;
        }

        // Create the custom product
        $customProduct = OrderCustomProduct::create([
            'order_id' => $orderId,
            'product_details' => $productDetails,
            'custom_note' => $customNote ? trim($customNote) : null,
            'product_ids' => $productIds,
        ]);

        // Save images if provided
        if (!empty($imagePaths)) {
            $this->saveImages($customProduct->id, $imagePaths);
        }

        return $customProduct;
    }

    /**
     * Sync products to order_products table with quantity aggregation
     *
     * @param int $orderId
     * @param array $productsToSync Array of [product_id => ['product_id' => int, 'quantity' => int]]
     * @return void
     */
    private function syncProductsToOrderProducts(int $orderId, array $productsToSync): void
    {
        // Get all existing order products for this order
        $existingOrderProducts = DB::table('order_products')
            ->where('order_id', $orderId)
            ->get()
            ->keyBy('product_id');

        // Process each product to sync
        foreach ($productsToSync as $productId => $productInfo) {
            $productId = (int) $productId;
            $newQuantity = (int) ($productInfo['quantity'] ?? 0);
            
            if ($productId <= 0 || $newQuantity <= 0) {
                continue;
            }

            $existingOrderProduct = $existingOrderProducts[$productId] ?? null;

            if ($existingOrderProduct) {
                // Product already exists in order_products, aggregate quantities
                $existingQuantity = (int) ($existingOrderProduct->quantity ?? 0);
                $totalQuantity = $existingQuantity + $newQuantity;

                DB::table('order_products')
                    ->where('order_id', $orderId)
                    ->where('product_id', $productId)
                    ->update([
                        'quantity' => $totalQuantity,
                        'updated_at' => now(),
                    ]);
            } else {
                // Product doesn't exist, insert new entry
                DB::table('order_products')->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => $newQuantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Update a custom product
     *
     * @param int $customProductId
     * @param array $data Updated product details
     * @param array|null $imagePaths New image paths (null = no change, [] = delete all, [paths] = replace all)
     * @param array|null $existingImagesToKeep Existing image paths to keep
     * @return OrderCustomProduct
     */
    public function update(int $customProductId, array $data, ?array $imagePaths = null, ?array $existingImagesToKeep = null): OrderCustomProduct
    {
        $customProduct = OrderCustomProduct::findOrFail($customProductId);

        // Update product details if provided
        if (isset($data['product_details']) || $this->hasProductDetailFields($data) || isset($data['connected_products'])) {
            $currentDetails = $customProduct->product_details ?? [];
            
            if (isset($data['product_details'])) {
                // Direct product_details provided (for bulk updates)
                $newDetails = is_array($data['product_details']) ? $data['product_details'] : [];
            } else {
                // Individual fields provided - merge with existing and prepare
                $mergedData = array_merge($currentDetails, $data);
                $newDetails = $this->prepareProductDetails($mergedData);
            }
            
            // Handle connected_products separately if provided directly (not through product_details)
            if (isset($data['connected_products']) && !isset($data['product_details'])) {
                $newDetails['connected_products'] = $data['connected_products'];
            }
            
            $customProduct->product_details = $newDetails;
        }

        // Update custom note if provided
        if (array_key_exists('custom_note', $data)) {
            $customProduct->custom_note = !empty($data['custom_note']) ? trim($data['custom_note']) : null;
        }

        // Update product_ids if provided (separate column, like admin panel)
        if (array_key_exists('product_ids', $data)) {
            $customProduct->product_ids = $this->prepareProductIds($data['product_ids']);
        }

        $customProduct->save();

        // Handle image updates
        if ($imagePaths !== null && $existingImagesToKeep !== null) {
            // Both new images and existing images to keep - combine them
            $this->keepOnlySpecifiedImages($customProductId, $existingImagesToKeep);
            $this->saveImages($customProductId, $imagePaths);
        } elseif ($imagePaths !== null) {
            // Only new images provided - replace all
            $this->deleteAllImages($customProductId);
            if (!empty($imagePaths)) {
                $this->saveImages($customProductId, $imagePaths);
            }
        } elseif ($existingImagesToKeep !== null) {
            // Only existing images to keep
            $this->keepOnlySpecifiedImages($customProductId, $existingImagesToKeep);
        }

        return $customProduct->fresh();
    }

    /**
     * Prepare product details array from input data
     *
     * @param array $data
     * @return array
     */
    private function prepareProductDetails(array $data): array
    {
        $details = [];

        // Store product_id if provided and not empty (can be integer or array)
        if (isset($data['product_id']) && $data['product_id'] !== '' && $data['product_id'] !== null) {
            if (is_array($data['product_id'])) {
                $details['product_id'] = array_map('intval', array_filter($data['product_id']));
            } else {
                $details['product_id'] = (int) $data['product_id'];
            }
        }

        $h1 = null;
        if (isset($data['h1']) && $data['h1'] !== '' && $data['h1'] !== null) {
            $h1 = (int) $data['h1'];
            $details['h1'] = $h1;
        }

        $h2 = null;
        if (isset($data['h2']) && $data['h2'] !== '' && $data['h2'] !== null) {
            $h2 = (int) $data['h2'];
            $details['h2'] = $h2;
        }

        // Store w1 (width 1) if provided
        $w1 = null;
        if (isset($data['w1']) && $data['w1'] !== '' && $data['w1'] !== null) {
            $w1 = (int) $data['w1'];
            $details['w1'] = $w1;
        }

        // Store w2 (width 2) if provided
        $w2 = null;
        if (isset($data['w2']) && $data['w2'] !== '' && $data['w2'] !== null) {
            $w2 = (int) $data['w2'];
            $details['w2'] = $w2;
        }

        // Store actual_pcs (pieces) if provided
        $actualPcs = null;
        if (isset($data['actual_pcs']) && $data['actual_pcs'] !== '' && $data['actual_pcs'] !== null) {
            $actualPcs = (int) $data['actual_pcs'];
            $details['actual_pcs'] = $actualPcs;
        }

        // Calculate quantity
        // Priority:
        //   1) materials[] array (sum of (h1+h2+w1+w2) * pcs per material)
        //   2) h1*w1 or h2*w2 (single-line) then multiply by pcs if provided
        $calculatedQuantity = null;
        
        // 1) Material-wise calculation (if materials array is provided)
        if (isset($data['materials']) && is_array($data['materials']) && !empty($data['materials'])) {
            $materials = [];
            $materialsTotal = 0;

            foreach ($data['materials'] as $material) {
                // Support both actual_pcs and pcs in materials (for backward compatibility)
                $mPcs = 0;
                if (isset($material['actual_pcs']) && $material['actual_pcs'] !== '') {
                    $mPcs = (int) $material['actual_pcs'];
                } elseif (isset($material['pcs']) && $material['pcs'] !== '') {
                    $mPcs = (int) $material['pcs'];
                }

                $lineQty = 0;
                $sumOfMeasurements = 0;

                // Priority 1: Calculate from measurements array if provided
                if (isset($material['measurements']) && is_array($material['measurements']) && !empty($material['measurements'])) {
                    // Sum all values in measurements array
                    foreach ($material['measurements'] as $measurement) {
                        if (is_numeric($measurement)) {
                            $sumOfMeasurements += (float) $measurement;
                        }
                    }
                    // Calculate quantity: (sum of measurements) * actual_pcs
                    $lineQty = $sumOfMeasurements * $mPcs;
                } else {
                    // Priority 2: Sum all m* fields (m1, m2, m3, etc.) dynamically
                    $sumOfMFields = 0;
                    $mFields = [];
                    foreach ($material as $key => $value) {
                        // Match m* fields (m1, m2, m3, m10, m99, etc.)
                        if (preg_match('/^m\d+$/', $key)) {
                            $mValue = isset($value) && $value !== '' ? (float) $value : 0;
                            $sumOfMFields += $mValue;
                            $mFields[$key] = $mValue;
                        }
                    }

                    // Calculate quantity: (sum of all m* fields) * actual_pcs
                    $lineQty = $sumOfMFields * $mPcs;
                }

                $materialsTotal += $lineQty;

                // Normalize and store each material row in JSON
                $normalizedMaterial = [
                    'material_id' => $material['material_id'] ?? null,
                    'actual_pcs' => $mPcs,
                    'calculated_quantity' => $lineQty,
                    'cal_qty' => $lineQty, // Also store as cal_qty for consistency
                ];
                
                // Add measurements array if provided
                if (isset($material['measurements']) && is_array($material['measurements'])) {
                    $normalizedMaterial['measurements'] = $material['measurements'];
                }
                
                // Add all m* fields to normalized material (if measurements not used)
                if (!isset($material['measurements']) || empty($material['measurements'])) {
                    $mFields = [];
                    foreach ($material as $key => $value) {
                        if (preg_match('/^m\d+$/', $key)) {
                            $mValue = isset($value) && $value !== '' ? (float) $value : 0;
                            $mFields[$key] = $mValue;
                        }
                    }
                    foreach ($mFields as $mKey => $mValue) {
                        $normalizedMaterial[$mKey] = $mValue;
                    }
                }
                
                $materials[] = $normalizedMaterial;
            }

            $details['materials'] = $materials;
            $calculatedQuantity = $materialsTotal;
        } else {
            // 2) Single-line dimension-based calculation (backward compatible)
            // Use same formula as calculate API: (h1 + h2 + w1 + w2) * actual_pcs
            $sumOfDimensions = 0;
            if ($h1 !== null) $sumOfDimensions += $h1;
            if ($h2 !== null) $sumOfDimensions += $h2;
            if ($w1 !== null) $sumOfDimensions += $w1;
            if ($w2 !== null) $sumOfDimensions += $w2;
            
            if ($sumOfDimensions > 0 && $actualPcs !== null) {
                $calculatedQuantity = $sumOfDimensions * $actualPcs;
            }
        }
        
        // Store calculated quantity if available, otherwise use provided quantity
        if ($calculatedQuantity !== null) {
            $details['quantity'] = $calculatedQuantity;
        } elseif (isset($data['quantity']) && $data['quantity'] !== '' && $data['quantity'] !== null) {
            $details['quantity'] = (float) $data['quantity'];
        }

        // Store unit_id if provided and not empty
        if (isset($data['unit_id']) && $data['unit_id'] !== '' && $data['unit_id'] !== null) {
            $details['unit_id'] = (int) $data['unit_id'];
        }

        // Store connected_products if provided (for custom product connected products)
        // This allows custom product connected products to have separate quantities from regular order products
        if (isset($data['connected_products']) && is_array($data['connected_products'])) {
            $details['connected_products'] = $data['connected_products'];
        }

        return $details;
    }

    /**
     * Check if data contains product detail fields
     *
     * @param array $data
     * @return bool
     */
    private function hasProductDetailFields(array $data): bool
    {
        $productDetailFields = ['product_id', 'h1', 'h2', 'w1', 'w2', 'quantity', 'actual_pcs'];
        return !empty(array_intersect_key($data, array_flip($productDetailFields)));
    }

    /**
     * Prepare product_ids array from input data
     * Handles different data types: array, string (JSON or comma-separated), single value
     * Same logic as admin panel OrderForm
     *
     * @param mixed $productIds
     * @return array
     */
    private function prepareProductIds($productIds): array
    {
        // If null or empty, return empty array
        if ($productIds === null || $productIds === '') {
            return [];
        }

        // If already an array, process it
        if (is_array($productIds)) {
            // Ensure they're integers and filter
            return array_values(array_unique(array_filter(array_map(function($id) {
                if (is_numeric($id)) {
                    return (int)$id;
                }
                if (is_string($id) && is_numeric($id)) {
                    return (int)$id;
                }
                return null;
            }, $productIds), fn($id) => $id !== null && $id > 0)));
        }

        // If string, try to decode as JSON first
        if (is_string($productIds)) {
            $decoded = json_decode($productIds, true);
            if (is_array($decoded)) {
                return $this->prepareProductIds($decoded);
            }
            
            // If comma-separated, split it
            if (strpos($productIds, ',') !== false) {
                $split = array_map('trim', explode(',', $productIds));
                return $this->prepareProductIds($split);
            }
            
            // If single numeric string, convert to array
            if (is_numeric($productIds)) {
                return [(int)$productIds];
            }
        }

        // If single numeric value
        if (is_numeric($productIds)) {
            return [(int)$productIds];
        }

        // Default: return empty array
        return [];
    }

    /**
     * Save images for a custom product
     *
     * @param int $customProductId
     * @param array $imagePaths
     * @return void
     */
    public function saveImages(int $customProductId, array $imagePaths): void
    {
        $sortOrder = 0;
        $savedCount = 0;
        
        foreach ($imagePaths as $imagePath) {
            if (!empty($imagePath) && is_string($imagePath)) {
                try {
                    OrderCustomProductImage::create([
                        'order_custom_product_id' => $customProductId,
                        'image_path' => $imagePath,
                        'sort_order' => $sortOrder++,
                    ]);
                    $savedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to save custom product image', [
                        'custom_product_id' => $customProductId,
                        'image_path' => $imagePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('Invalid image path skipped', [
                    'custom_product_id' => $customProductId,
                    'image_path' => $imagePath,
                    'type' => gettype($imagePath),
                ]);
            }
        }
    }

    /**
     * Delete all images for a custom product
     *
     * @param int $customProductId
     * @return void
     */
    public function deleteAllImages(int $customProductId): void
    {
        OrderCustomProductImage::where('order_custom_product_id', $customProductId)->delete();
    }

    /**
     * Keep only specified images, delete the rest
     *
     * @param int $customProductId
     * @param array $imagePathsToKeep
     * @return void
     */
    private function keepOnlySpecifiedImages(int $customProductId, array $imagePathsToKeep): void
    {
        // Delete all existing images
        $this->deleteAllImages($customProductId);

        // Recreate only the ones to keep
        if (!empty($imagePathsToKeep)) {
            $this->saveImages($customProductId, $imagePathsToKeep);
        }
    }

    /**
     * Get product details as array with proper structure
     *
     * @param OrderCustomProduct $customProduct
     * @return array
     */
    public function getProductDetails(OrderCustomProduct $customProduct): array
    {
        $details = $customProduct->product_details ?? [];
        
        return [
            'product_id' => $details['product_id'] ?? null,
            'h1' => $details['h1'] ?? null,
            'h2' => $details['h2'] ?? null,
            'w1' => $details['w1'] ?? null,
            'w2' => $details['w2'] ?? null,
            // Underlying storage uses computed quantity; expose quantity and actual_pcs for API consumers
            'quantity' => $details['quantity'] ?? null,
            'actual_pcs' => $details['actual_pcs'] ?? null,
            'unit_id' => $details['unit_id'] ?? null,
            
        ];
    }

    /**
     * Format custom product for API response
     *
     * @param OrderCustomProduct $customProduct
     * @param array $imageUrls
     * @return array
     */
    public function formatForResponse(OrderCustomProduct $customProduct, array $imageUrls = []): array
    {
        $details = $this->getProductDetails($customProduct);
        
        return [
            'id' => $customProduct->id,
            'custom_product_id' => $customProduct->id,
            'type' => 'custom',
            'is_custom' => 1,
            'product_id' => $details['product_id'],
            'product_name' => $customProduct->product?->product_name ?? 'Custom Product',
            'h1' => $details['h1'],
            'h2' => $details['h2'],
            'w1' => $details['w1'],
            'w2' => $details['w2'],
            // For responses, use actual_pcs and quantity
            'quantity' => $details['quantity'],
            'calqty' => $details['quantity'], // Calculated quantity (same as quantity)
            'actual_pcs' => $details['actual_pcs'],
            'unit_id' => $details['unit_id'],
            'unit' => $customProduct->unit?->name ?? null,
            'custom_note' => $customProduct->custom_note ?? '',
            'custom_images' => $imageUrls,
        ];
    }
}