<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement\Validators;

use App\Utility\Enums\PriorityEnum;
use App\Utility\Enums\StoreEnum;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderRequestValidator
{
    public static function rules(Request $request): array
    {
        return self::baseRules($request);
    }

    public static function updateRules(Request $request): array
    {
        return self::baseRules($request);
    }

    /**
     * Shared validation rules for create and update.
     */
    private static function baseRules(Request $request): array
    {
        return [
            'site_id' => 'required|integer|exists:sites,id',
            'customer_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            // expected_delivery_date must be in dd/MM/yyyy format (e.g. 25/12/2025)
            'expected_delivery_date' => 'nullable|date_format:d/m/Y',
            'priority' => ['required', Rule::enum(PriorityEnum::class)],
            'products' => 'required|array|min:1',
            'products.*.is_custom' => 'nullable|boolean',
            'products.*.product_id' => [
                'required_without:products.*.is_custom',
                'nullable',
                'integer',
                'exists:products,id',
                function ($attribute, $value, $fail) use ($request) {
                    $index = (int) explode('.', $attribute)[1];
                    $products = $request->input('products', []);
                    $isCustom = filter_var($products[$index]['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    
                    if ($isCustom && !empty($value)) {
                        $fail('product_id should not be provided when is_custom is 1.');
                    }
                    
                    if (!$isCustom && empty($value)) {
                        $fail('product_id is required when is_custom is 0 or not set.');
                    }
                }
            ],
            'products.*.quantity' => [
                'required_without:products.*.is_custom',
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    $index = (int) explode('.', $attribute)[1];
                    $products = $request->input('products', []);
                    $isCustom = filter_var($products[$index]['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    
                    if ($isCustom && !empty($value)) {
                        $fail('quantity should not be provided when is_custom is 1.');
                    }
                    
                    if (!$isCustom && empty($value)) {
                        $fail('quantity is required when is_custom is 0 or not set.');
                    }

                    // Stock availability validation for non-custom products
                    if (!$isCustom && !empty($value)) {
                        $productId = $products[$index]['product_id'] ?? null;
                        
                        if ($productId) {
                            $product = Product::find($productId);
                            
                            // Skip stock validation for LPO products
                            if ($product && $product->store === StoreEnum::LPO) {
                                return; // No stock validation for LPO products
                            }

                            // Check stock availability
                            $stockService = app(StockService::class);
                            $siteId = $request->input('site_id');
                            
                            // Get general stock (site_id = null)
                            $generalStock = $stockService->getCurrentStock((int)$productId, null);
                            
                            // Get site-specific stock if site_id is provided
                            $siteStock = 0;
                            if ($siteId) {
                                $siteStock = $stockService->getCurrentStock((int)$productId, (int)$siteId);
                            }
                            
                            // Total available stock
                            $currentStock = $generalStock + $siteStock;
                            
                            // Validate quantity against available stock
                            if ((int)$value > $currentStock) {
                                $fail("Insufficient stock for product. Available: " . $currentStock . ", Requested: " . (int)$value . ".");
                            }
                        }
                    }
                }
            ],
            'products.*.custom_note' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    $index = (int) explode('.', $attribute)[1];
                    $products = $request->input('products', []);
                    $isCustom = filter_var($products[$index]['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    
                    if (!$isCustom && !empty($value)) {
                        $fail('custom_note should not be provided when is_custom is 0.');
                    }
                }
            ],
            'products.*.custom_images' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) use ($request) {
                    $index = (int) explode('.', $attribute)[1];
                    $products = $request->input('products', []);
                    $isCustom = filter_var($products[$index]['is_custom'] ?? 0, FILTER_VALIDATE_BOOLEAN);
                    $customNote = $products[$index]['custom_note'] ?? '';
                    $customImages = $value ?? [];
                    
                    if (!$isCustom && !empty($customImages)) {
                        $fail('custom_images should not be provided when is_custom is 0.');
                    }
                    
                    if ($isCustom && empty($customImages) && empty($customNote)) {
                        $fail('Either custom_note or custom_images are required for custom products.');
                    }
                    
                    if (is_array($customImages)) {
                        foreach ($customImages as $image) {
                            if ($image && !is_string($image) && !($image instanceof \Illuminate\Http\UploadedFile)) {
                                $fail('Custom images must be valid files or base64 strings.');
                            }
                        }
                    }
                }
            ],
            'products.*.custom_images.*' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value && !is_string($value) && !($value instanceof \Illuminate\Http\UploadedFile)) {
                        $fail('Custom image must be a valid file or base64 string.');
                    }
                }
            ],
        ];
    }
}

