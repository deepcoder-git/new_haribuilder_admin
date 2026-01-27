<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Services\StockService;
use App\Utility\Enums\StoreEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ProductImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    protected array $errors = [];
    protected int $successCount = 0;
    protected int $errorCount = 0;
    protected ?StockService $stockService = null;

    public function __construct()
    {
        $this->stockService = app(StockService::class);
    }

    public function collection(Collection $rows): void
    {
        // Log column names from first row for debugging
        if ($rows->isNotEmpty()) {
            $firstRow = $rows->first();
            $columnNames = $firstRow->keys()->toArray();
            Log::info('Product Import - Column names detected', ['columns' => $columnNames]);
        }
        
        foreach ($rows as $index => $row) {
            try {
                $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row
                
                // Get store name - try multiple possible column names
                $storeName = $this->getColumnValue($row, ['store_name', 'store name', 'store', 'storename']);
                $productName = $this->getColumnValue($row, ['product_name', 'product name', 'product', 'productname']);
                
                // Skip empty rows
                if (empty($storeName) && empty($productName)) {
                    continue;
                }

                // Validate required fields
                if (empty($storeName)) {
                    // Log available column names for debugging
                    $availableColumns = $row->keys()->toArray();
                    Log::warning("Product Import - Store Name missing on row {$rowNumber}", [
                        'available_columns' => $availableColumns,
                        'row_data' => $row->toArray()
                    ]);
                    throw new \Exception("Store Name is required. Available columns: " . implode(', ', $availableColumns));
                }
                
                if (empty($productName)) {
                    throw new \Exception("Product Name is required");
                }

                // Validate and process the row
                $this->processRow($row, $rowNumber, $storeName, $productName);
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
                Log::error("Product import error on row {$rowNumber}: " . $e->getMessage());
            }
        }
    }
    
    protected function getColumnValue(Collection $row, array $possibleKeys): ?string
    {
        // Normalize function to handle various formats
        $normalize = function($str) {
            return strtolower(trim(preg_replace('/[^a-z0-9]/', '_', $str)));
        };
        
        foreach ($possibleKeys as $key) {
            $normalizedKey = $normalize($key);
            
            // Try exact key first
            if (isset($row[$key]) && !empty($row[$key])) {
                $value = $row[$key];
                if ($value !== null && $value !== '') {
                    return trim((string) $value);
                }
            }
            
            // Try case-insensitive and special character-insensitive match
            foreach ($row->keys() as $rowKey) {
                $normalizedRowKey = $normalize((string) $rowKey);
                
                if ($normalizedRowKey === $normalizedKey) {
                    $value = $row[$rowKey];
                    if ($value !== null && $value !== '') {
                        return trim((string) $value);
                    }
                }
            }
        }
        
        return null;
    }

    protected function processRow(Collection $row, int $rowNumber, string $storeName, string $productName): void
    {
        // Get store enum value
        $normalizedStoreName = $this->normalizeStoreName($storeName);
        $store = $this->getStoreEnum($normalizedStoreName);
        
        if (!$store) {
            throw new \Exception("Invalid store name: '{$storeName}'. Valid stores are: Hardware Store, Workshop store, LPO(Local Purchase Order)");
        }

        // Get or create category - try multiple possible column names
        $categoryName = $this->getColumnValue($row, ['category', 'category_name', 'category name']);
        if (empty($categoryName)) {
            throw new \Exception("Category is required");
        }

        $category = Category::where('name', $categoryName)->first();
        if (!$category) {
            // Create category if it doesn't exist
            $category = Category::create([
                'name' => $categoryName,
                'status' => true,
            ]);
        }

        // Get unit type - try multiple possible column names (REQUIRED)
        $unitType = $this->getColumnValue($row, ['unit_type', 'unit type', 'unit', 'unittype']);
        if (empty($unitType)) {
            throw new \Exception("Unit Type is required");
        }
        
        // Validate unit type exists in units table (case-insensitive match)
        $unit = Unit::whereRaw('LOWER(name) = LOWER(?)', [$unitType])
            ->where('status', true)
            ->first();
        
        if (!$unit) {
            // Try to find similar unit names (handle plural/singular variations)
            $normalizedInput = strtolower(trim($unitType));
            $allUnits = Unit::where('status', true)->get();
            
            $matchedUnit = $allUnits->first(function($u) use ($normalizedInput) {
                $unitName = strtolower(trim($u->name));
                // Exact match
                if ($unitName === $normalizedInput) {
                    return true;
                }
                // Handle common plural/singular variations
                $variations = [
                    $normalizedInput . 's',  // bag -> bags
                    rtrim($normalizedInput, 's'),  // bags -> bag
                    rtrim($normalizedInput, 'es'), // boxes -> box
                    $normalizedInput . 'es',  // box -> boxes
                ];
                return in_array($unitName, $variations);
            });
            
            if ($matchedUnit) {
                $unit = $matchedUnit;
                $unitType = $unit->name; // Use the correct name from database
            } else {
                // Get list of available units for better error message
                $availableUnits = Unit::where('status', true)->pluck('name')->implode(', ');
                throw new \Exception("Unit Type '{$unitType}' does not exist. Available units: {$availableUnits}");
            }
        } else {
            // Use the exact name from database to ensure consistency
            $unitType = $unit->name;
        }
        
        // Get available quantity - try multiple possible column names
        $availableQtyValue = $this->getColumnValue($row, ['available_quantity', 'available quantity', 'quantity', 'available_qty', 'qty']);
        $availableQty = $this->parseQuantity($availableQtyValue ?? 0);
        
        // Get is_product flag (0 = No, 1 = Yes) - try multiple possible column names
        $isProductValue = $this->getColumnValue($row, ['product_0_no_1_yes', 'product (0 = no, 1 = yes)', 'product', 'is_product', 'is product']);
        $isProduct = $this->parseIsProduct($isProductValue ?? 1);

        // Check if product with same name and store already exists
        $existingProduct = Product::where('product_name', $productName)
            ->where('store', $store->value)
            ->first();

        if ($existingProduct) {
            // Update existing product
            $existingProduct->update([
                'category_id' => $category->id,
                'unit_type' => $unitType,
                'available_qty' => $availableQty,
                'is_product' => $isProduct,
                'status' => true,
            ]);
            
            $product = $existingProduct;
        } else {
            // Create new product
            $product = Product::create([
                'product_name' => $productName,
                'category_id' => $category->id,
                'store' => $store->value,
                'unit_type' => $unitType,
                'available_qty' => $availableQty,
                'is_product' => $isProduct,
                'status' => true,
            ]);
        }

        // Create stock entry if available_qty > 0 (similar to ProductForm)
        // This ensures the quantity displays correctly using total_stock_quantity
        if ($availableQty > 0) {
            try {
                // Check if stock entry already exists for this product (general stock, site_id = null)
                $hasStockEntry = $product->stocks()
                    ->whereNull('site_id')
                    ->where('status', true)
                    ->exists();
                
                // Only create stock entry if it doesn't exist or if updating and qty changed
                if (!$hasStockEntry || ($existingProduct && $existingProduct->wasChanged('available_qty'))) {
                    $this->stockService->adjustStock(
                        $product->id,
                        $availableQty,
                        'adjustment',
                        null,
                        "Product imported with available quantity {$availableQty}",
                        null,
                        'Product Import'
                    );
                }
            } catch (\Exception $e) {
                // Log error but don't fail the import
                Log::warning("Failed to create stock entry for product {$product->id}: " . $e->getMessage());
            }
        }

        $this->successCount++;
    }

    protected function normalizeStoreName(string $storeName): string
    {
        $storeName = trim($storeName);
        
        if (empty($storeName)) {
            return $storeName;
        }
        
        $lowerStoreName = strtolower($storeName);
        
        // Normalize common variations
        $normalizations = [
            // Exact matches first
            'hardware store' => 'Hardware Store',
            'warehouse store' => 'Workshop store',
            'lpo(local purchase order)' => 'LPO(Local Purchase Order)',
            'lpo (local purchase order)' => 'LPO(Local Purchase Order)',
            // Partial matches
            'hardware' => 'Hardware Store',
            'workshop' => 'Workshop store',
            'lpo' => 'LPO(Local Purchase Order)',
            // Workshop store should map to Hardware Store (or we can add it as a new store)
            'workshop store' => 'Hardware Store',
            'workshop' => 'Hardware Store',
        ];

        // Check for exact match first
        if (isset($normalizations[$lowerStoreName])) {
            return $normalizations[$lowerStoreName];
        }
        
        // Check for partial matches
        foreach ($normalizations as $key => $value) {
            if (str_contains($lowerStoreName, $key) || str_contains($key, $lowerStoreName)) {
                return $value;
            }
        }

        // If no match found, return original (might be a valid store name)
        return $storeName;
    }

    protected function getStoreEnum(?string $storeName): ?StoreEnum
    {
        if (empty($storeName)) {
            return null;
        }

        foreach (StoreEnum::cases() as $store) {
            if ($store->getName() === $storeName) {
                return $store;
            }
        }

        return null;
    }

    protected function parseQuantity($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        if (is_string($value)) {
            $value = trim($value);
            // Extract numeric value from strings like "39 BOX", "50 Pcs", etc.
            if (preg_match('/^(\d+)/', $value, $matches)) {
                return (int) $matches[1];
            }
            // If it's a pure number string
            if (is_numeric($value)) {
                return (int) $value;
            }
        }
        
        return 0;
    }

    protected function parseIsProduct($value): int
    {
        if (is_numeric($value)) {
            $intValue = (int) $value;
            return in_array($intValue, [0, 1, 2]) ? $intValue : 1;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['0', 'no', 'false'])) {
                return 0;
            }
        }
        
        return 1; // Default to 1 (product)
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape_character' => '\\',
            'input_encoding' => 'UTF-8',
        ];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }
}

