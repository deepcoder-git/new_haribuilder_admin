<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\ProductImage;
use App\Services\StockService;
use App\Utility\Enums\ProductTypeEnum;
use App\Utility\Enums\StoreEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Illuminate\Support\Str;

class MaterialImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    protected array $errors = [];
    protected int $successCount = 0;
    protected int $errorCount = 0;
    protected ?StockService $stockService = null;
    protected array $unmatchedProducts = [];
    protected ?string $unmatchedProductsFile = null;

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
            Log::info('Material Import - Column names detected', ['columns' => $columnNames]);
        }
        
        foreach ($rows as $index => $row) {
            try {
                $rowNumber = $index + 2; // +2 because index is 0-based and we skip header row
                
                // Check if row is completely empty (all values are null or empty)
                $rowValues = array_filter($row->toArray(), function($value) {
                    return $value !== null && trim((string)$value) !== '';
                });
                
                if (empty($rowValues)) {
                    // Skip completely empty rows
                    continue;
                }
                
                // Get material name - try multiple possible column names
                // Support both "Product Name" (from CSV) and "Material Name" formats
                $materialName = $this->getColumnValue($row, [
                    'product_name', 'product name', 'productname',  // CSV format
                    'material_name', 'material name', 'material', 'materialname', 'name'  // Standard format
                ]);
                
                // Skip rows without material name
                if (empty($materialName)) {
                    // Log available column names for debugging
                    $availableColumns = $row->keys()->toArray();
                    Log::warning("Material Import - Material Name missing on row {$rowNumber}", [
                        'available_columns' => $availableColumns,
                        'row_data' => $row->toArray()
                    ]);
                    // Don't throw error for empty rows, just skip them
                    continue;
                }

                // Validate and process the row
                $this->processRow($row, $rowNumber, $materialName);
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
                Log::error("Material import error on row {$rowNumber}: " . $e->getMessage(), [
                    'row_data' => $row->toArray()
                ]);
            }
        }

        // Save unmatched products to file after import completes
        $this->saveUnmatchedProductsFile();
    }
    
    protected function getColumnValue(Collection $row, array $possibleKeys): ?string
    {
        // Normalize function to handle various formats
        $normalize = function($str) {
            // Convert to lowercase, trim, and replace non-alphanumeric with underscores
            // Then remove multiple underscores
            $normalized = strtolower(trim($str));
            $normalized = preg_replace('/[^a-z0-9]/', '_', $normalized);
            $normalized = preg_replace('/_+/', '_', $normalized); // Replace multiple underscores with single
            return trim($normalized, '_');
        };
        
        foreach ($possibleKeys as $key) {
            $normalizedKey = $normalize($key);
            
            // Try exact key first (case-insensitive)
            foreach ($row->keys() as $rowKey) {
                if (strcasecmp((string)$rowKey, $key) === 0) {
                    $value = $row[$rowKey];
                    if ($value !== null && $value !== '' && trim((string)$value) !== '') {
                        return trim((string) $value);
                    }
                }
            }
            
            // Try normalized match (handles spaces, special chars, case differences)
            foreach ($row->keys() as $rowKey) {
                $normalizedRowKey = $normalize((string) $rowKey);
                
                if ($normalizedRowKey === $normalizedKey) {
                    $value = $row[$rowKey];
                    if ($value !== null && $value !== '' && trim((string)$value) !== '') {
                        return trim((string) $value);
                    }
                }
            }
        }
        
        return null;
    }

    protected function processRow(Collection $row, int $rowNumber, string $materialName): void
    {
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
        // Support both "Unit Type" from CSV and other formats
        $unitType = $this->getColumnValue($row, ['unit_type', 'unit type', 'unittype', 'unit']);
        if (empty($unitType)) {
            throw new \Exception("Unit Type is required");
        }
        
        // Trim and normalize unit type
        $unitType = trim($unitType);
        
        // Check if unit exists in units table (case-insensitive match)
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
                // Create unit if it doesn't exist (similar to category creation)
                $unit = Unit::whereRaw('LOWER(name) = LOWER(?)', [$unitType])->first();
                
                if (!$unit) {
                    // Create new unit with the exact name from CSV
                    $unit = Unit::create([
                        'name' => $unitType,
                        'status' => true,
                    ]);
                    Log::info("Material Import - Created new unit: {$unitType}");
                } else {
                    // Unit exists but is inactive, activate it
                    $unit->update(['status' => true]);
                    Log::info("Material Import - Activated existing unit: {$unitType}");
                }
            }
        } else {
            // Use the exact name from database to ensure consistency
            $unitType = $unit->name;
        }
        
        // Get available quantity - try multiple possible column names
        // Support both "Available Quantity" from CSV and other formats
        // Excel/CSV parser may convert "Available Quantity" to "available_quantity" or "available quantity"
        $availableQtyValue = $this->getColumnValue($row, [
            'available_quantity', 'available quantity', 'availablequantity',  // CSV format (Excel may convert spaces to underscores)
            'quantity', 'available_qty', 'qty', 'availableqty'  // Alternative formats
        ]);
        
        // If still not found, try to find any column that contains "quantity" or "qty"
        if ($availableQtyValue === null) {
            foreach ($row->keys() as $rowKey) {
                $normalizedKey = strtolower(trim((string)$rowKey));
                if (strpos($normalizedKey, 'quantity') !== false || strpos($normalizedKey, 'qty') !== false) {
                    $value = $row[$rowKey];
                    if ($value !== null && $value !== '' && trim((string)$value) !== '') {
                        $availableQtyValue = trim((string)$value);
                        Log::info("Material Import - Found quantity column by keyword match", [
                            'column_name' => $rowKey,
                            'value' => $availableQtyValue,
                        ]);
                        break;
                    }
                }
            }
        }
        
        // Log for debugging
        Log::info("Material Import - Available Quantity parsing", [
            'row_number' => $rowNumber,
            'material_name' => $materialName,
            'raw_value' => $availableQtyValue,
            'parsed_value' => $this->parseQuantity($availableQtyValue ?? 0),
            'row_keys' => $row->keys()->toArray(),
            'row_data_sample' => $row->take(10)->toArray(), // First 10 columns for debugging
        ]);
        
        $availableQty = $this->parseQuantity($availableQtyValue ?? 0);
        
        // Get is_product flag (0 = No, 1 = Yes) - try multiple possible column names
        // Support both "Product (0 = No, 1 = Yes)" from CSV and other formats
        $isProductValue = $this->getColumnValue($row, [
            'product (0 = no, 1 = yes)', 'product (0 = No, 1 = Yes)', 'product_0_no_1_yes',  // CSV format
            'product', 'is_product', 'is product'  // Alternative formats
        ]);
        $isProduct = $this->parseIsProduct($isProductValue ?? 0);

        // Get store - try multiple possible column names
        // Support both "Store Name" from CSV and other formats
        $storeName = $this->getColumnValue($row, [
            'store_name', 'store name', 'storename',  // CSV format
            'store'  // Alternative format
        ]);
        $store = $this->parseStore($storeName);

        // Check if material with same name already exists
        $existingMaterial = Product::where('product_name', $materialName)
            ->where('type', ProductTypeEnum::Material->value)
            ->first();

        $materialData = [
            'category_id' => $category->id,
            'unit_type' => $unitType,
            'available_qty' => $availableQty,
            'is_product' => $isProduct,
            'status' => true,
        ];

        // Add store if provided
        if ($store !== null) {
            $materialData['store'] = $store->value;
        }

        // Log material data before save
        Log::info("Material Import - Saving material", [
            'row_number' => $rowNumber,
            'material_name' => $materialName,
            'material_data' => $materialData,
            'is_existing' => $existingMaterial !== null,
        ]);

        if ($existingMaterial) {
            // Update existing material
            $existingMaterial->update($materialData);
            
            // Refresh to get updated values
            $existingMaterial->refresh();
            
            $material = $existingMaterial;
            
            Log::info("Material Import - Updated existing material", [
                'material_id' => $material->id,
                'available_qty_after_update' => $material->available_qty,
            ]);
        } else {
            // Generate unique slug
            $slug = $this->generateUniqueSlug($materialName);
            
            // Create new material
            $material = Product::create(array_merge([
                'product_name' => $materialName,
                'slug' => $slug,
                'type' => ProductTypeEnum::Material->value,
            ], $materialData));
            
            Log::info("Material Import - Created new material", [
                'material_id' => $material->id,
                'available_qty_after_create' => $material->available_qty,
            ]);
        }

        // Create stock entry if available_qty > 0 (similar to MaterialForm)
        // This ensures the quantity displays correctly using total_stock_quantity
        if ($availableQty > 0) {
            try {
                // Check if stock entry already exists for this material (general stock, site_id = null)
                $hasStockEntry = $material->stocks()
                    ->whereNull('site_id')
                    ->where('status', true)
                    ->exists();
                
                Log::info("Material Import - Stock entry check", [
                    'material_id' => $material->id,
                    'available_qty' => $availableQty,
                    'has_stock_entry' => $hasStockEntry,
                    'is_existing' => $existingMaterial !== null,
                ]);
                
                // Only create stock entry if it doesn't exist or if updating and qty changed
                if (!$hasStockEntry || ($existingMaterial && $existingMaterial->wasChanged('available_qty'))) {
                    $this->stockService->adjustMaterialStock(
                        $material->id,
                        $availableQty,
                        'adjustment',
                        null,
                        "Material imported with available quantity {$availableQty}",
                        null,
                        'Material Import'
                    );
                    
                    Log::info("Material Import - Stock entry created", [
                        'material_id' => $material->id,
                        'quantity' => $availableQty,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the import
                Log::error("Failed to create stock entry for material {$material->id}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            Log::warning("Material Import - Available quantity is 0, skipping stock entry", [
                'material_id' => $material->id,
                'material_name' => $materialName,
                'raw_quantity_value' => $availableQtyValue,
            ]);
        }

        // Handle image import for this material
        $this->handleImageImport($material, $materialName);

        $this->successCount++;
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
            return in_array($intValue, [0, 1, 2]) ? $intValue : 0;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['0', 'no', 'false'])) {
                return 0;
            }
        }
        
        return 0; // Default to 0 (material only)
    }

    /**
     * Parse store name from CSV and convert to StoreEnum value
     * 
     * @param string|null $storeName
     * @return StoreEnum|null
     */
    protected function parseStore(?string $storeName): ?StoreEnum
    {
        if (empty($storeName)) {
            return null;
        }

        // Normalize the store name
        $normalized = strtolower(trim($storeName));
        
        // Remove common variations
        $normalized = preg_replace('/\s+/', '_', $normalized); // Replace spaces with underscores
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized); // Remove special characters
        
        // Map to StoreEnum values
        // "Workshop store" or "workshop_store" -> WarehouseStore
        if (strpos($normalized, 'workshop') !== false) {
            return StoreEnum::WarehouseStore;
        }
        
        // "hardware" or "hardware_store" -> HardwareStore
        if (strpos($normalized, 'hardware') !== false) {
            return StoreEnum::HardwareStore;
        }
        
        // "lpo" -> LPO
        if (strpos($normalized, 'lpo') !== false) {
            return StoreEnum::LPO;
        }
        
        // Try direct enum value match
        try {
            return StoreEnum::from($normalized);
        } catch (\ValueError $e) {
            // If no match, log and return null
            Log::warning("Material Import - Unknown store name: {$storeName}, normalized: {$normalized}");
            return null;
        }
    }

    protected function generateUniqueSlug(string $materialName): string
    {
        $slug = Str::slug($materialName);
        $originalSlug = $slug;
        $counter = 1;

        // Check if slug already exists, if so append counter
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
            
            // Prevent infinite loop (max 10000 attempts)
            if ($counter > 10000) {
                // Use timestamp as fallback
                $slug = $originalSlug . '-' . time();
                break;
            }
        }

        return $slug;
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance
     * Returns a value between 0 and 1 (1 = identical, 0 = completely different)
     * 
     * @param string $str1
     * @param string $str2
     * @return float
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) && empty($str2)) {
            return 1.0;
        }
        
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        $maxLength = max(strlen($str1), strlen($str2));
        if ($maxLength === 0) {
            return 1.0;
        }
        
        $distance = levenshtein($str1, $str2);
        $similarity = 1 - ($distance / $maxLength);
        
        return max(0.0, min(1.0, $similarity));
    }

    /**
     * Normalize image name for comparison
     * Removes numeric prefixes (1., 2., 3., etc.) and normalizes the string
     * 
     * @param string $name
     * @return string
     */
    protected function normalizeImageName(string $name): string
    {
        // Remove leading numeric prefixes like "1.", "2.", "3.", "10.", "123.", etc.
        // Pattern matches: one or more digits followed by a dot and optional space
        $normalized = preg_replace('/^\d+\.\s*/', '', trim($name));
        
        // Convert to lowercase for case-insensitive comparison
        $normalized = strtolower($normalized);
        
        // Normalize special characters and patterns:
        // - Replace "/" with "-" (MENOL 28 / 28 -> MENOL 28 - 28)
        // But preserve "1/2" patterns first
        $normalized = preg_replace('/(\d+)\s*\/\s*(\d+)/', '$1-$2', $normalized);
        $normalized = str_replace('/', '-', $normalized);
        
        // - Replace underscores with spaces (600MM_600MM -> 600MM 600MM)
        $normalized = str_replace('_', ' ', $normalized);
        
        // - Normalize "A C" or "A-C" or "P V C" to "AC" or "PVC" (remove spaces/dashes between single letters)
        // This handles "WHITE A C SOCKET" -> "WHITE AC SOCKET" and "P V C PIPE" -> "PVC PIPE"
        // Match sequences of single letters separated by spaces or dashes
        // Use a loop to handle multiple passes for cases like "P V C" (needs 2 passes)
        $maxPasses = 5; // Maximum number of passes to handle long sequences
        for ($i = 0; $i < $maxPasses; $i++) {
            $newNormalized = preg_replace('/\b([a-z])\s+([a-z])\b/i', '$1$2', $normalized);
            if ($newNormalized === $normalized) {
                break; // No more changes, exit loop
            }
            $normalized = $newNormalized;
        }
        // Also handle dashes between single letters
        for ($i = 0; $i < $maxPasses; $i++) {
            $newNormalized = preg_replace('/\b([a-z])-([a-z])\b/i', '$1$2', $normalized);
            if ($newNormalized === $normalized) {
                break; // No more changes, exit loop
            }
            $normalized = $newNormalized;
        }
        
        // Normalize multiple spaces to single space
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Trim whitespace
        $normalized = trim($normalized);
        
        return $normalized;
    }

    /**
     * Handle image import for a material
     * Check if image exists in public/product-images folder and associate it with the product
     * 
     * @param Product $material
     * @param string $materialName
     * @return void
     */
    protected function handleImageImport(Product $material, string $materialName): void
    {
        try {
            // Path to product-images folder (public/product-images)
            $productImagesPath = public_path('product-images');
            
            // Check if the directory exists
            if (!File::exists($productImagesPath) || !File::isDirectory($productImagesPath)) {
                Log::warning("Material Import - product-images directory not found", [
                    'path' => $productImagesPath,
                    'material_name' => $materialName,
                ]);
                $this->unmatchedProducts[] = $materialName;
                return;
            }

            // Try to find image file - first try exact match, then try with numeric prefix removal
            $imageExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
            $imageFound = false;
            $sourceImagePath = null;
            $imageExtension = null;
            $matchedImageFileName = null;

            // Step 1: Try exact match first
            foreach ($imageExtensions as $ext) {
                $imageFileName = $materialName . '.' . $ext;
                $imagePath = $productImagesPath . '/' . $imageFileName;
                
                if (File::exists($imagePath)) {
                    $imageFound = true;
                    $sourceImagePath = $imagePath;
                    $imageExtension = $ext;
                    $matchedImageFileName = $imageFileName;
                    break;
                }
            }

            // Step 2: If not found, scan all image files and match by removing numeric prefixes
            if (!$imageFound) {
                // Get all files in the directory
                $allFiles = File::files($productImagesPath);
                
                // Normalize product name for comparison (lowercase, trim)
                $normalizedProductName = $this->normalizeImageName($materialName);
                
                $bestMatch = null;
                $bestMatchScore = 0;
                $bestMatchFile = null;
                
                foreach ($allFiles as $file) {
                    $fileName = $file->getFilename();
                    $fileExtension = strtolower($file->getExtension());
                    
                    // Check if file has a valid image extension
                    if (!in_array($fileExtension, $imageExtensions)) {
                        continue;
                    }
                    
                    // Remove extension from filename for comparison
                    $fileNameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                    
                    // Normalize the image filename (remove numeric prefixes, lowercase, trim)
                    $normalizedImageName = $this->normalizeImageName($fileNameWithoutExt);
                    
                    // Exact match after normalization
                    if ($normalizedImageName === $normalizedProductName) {
                        $imageFound = true;
                        $sourceImagePath = $file->getPathname();
                        $imageExtension = $fileExtension;
                        $matchedImageFileName = $fileName;
                        
                        Log::info("Material Import - Image matched with normalization", [
                            'material_name' => $materialName,
                            'image_file_name' => $fileName,
                            'normalized_product' => $normalizedProductName,
                            'normalized_image' => $normalizedImageName,
                        ]);
                        break;
                    }
                    
                    // Calculate similarity for fuzzy matching (for typos)
                    $similarity = $this->calculateSimilarity($normalizedProductName, $normalizedImageName);
                    if ($similarity > $bestMatchScore && $similarity >= 0.85) { // 85% similarity threshold
                        $bestMatchScore = $similarity;
                        $bestMatch = $file;
                        $bestMatchFile = $fileName;
                    }
                }
                
                // If no exact match but we have a good fuzzy match, use it
                if (!$imageFound && $bestMatch !== null) {
                    $imageFound = true;
                    $sourceImagePath = $bestMatch->getPathname();
                    $imageExtension = strtolower($bestMatch->getExtension());
                    $matchedImageFileName = $bestMatchFile;
                    
                    Log::info("Material Import - Image matched with fuzzy matching", [
                        'material_name' => $materialName,
                        'image_file_name' => $bestMatchFile,
                        'normalized_product' => $normalizedProductName,
                        'normalized_image' => $this->normalizeImageName(pathinfo($bestMatchFile, PATHINFO_FILENAME)),
                        'similarity_score' => round($bestMatchScore * 100, 2) . '%',
                    ]);
                }
            }

            if (!$imageFound) {
                // Image not found, add to unmatched products list
                Log::info("Material Import - Image not found for product", [
                    'material_name' => $materialName,
                    'material_id' => $material->id,
                    'searched_path' => $productImagesPath,
                ]);
                $this->unmatchedProducts[] = $materialName;
                return;
            }

            // Image found, copy it to storage and create ProductImage record
            try {
                // Generate unique filename to avoid conflicts
                $destinationFileName = Str::slug($materialName) . '-' . time() . '.' . $imageExtension;
                $destinationPath = 'products/' . $destinationFileName;
                
                // Copy image to storage
                $destinationFullPath = Storage::disk('public')->path($destinationPath);
                $destinationDir = dirname($destinationFullPath);
                
                // Ensure directory exists
                if (!File::exists($destinationDir)) {
                    File::makeDirectory($destinationDir, 0755, true);
                }
                
                // Copy the file
                File::copy($sourceImagePath, $destinationFullPath);
                
                // Check if material already has this image (prevent duplicates)
                $existingImage = ProductImage::where('product_id', $material->id)
                    ->where('image_path', $destinationPath)
                    ->first();
                
                if (!$existingImage) {
                    // Get max order for this product
                    $maxOrder = ProductImage::where('product_id', $material->id)->max('order') ?? 0;
                    
                    // Create ProductImage record
                    ProductImage::create([
                        'product_id' => $material->id,
                        'image_path' => $destinationPath,
                        'image_name' => $materialName . '.' . $imageExtension,
                        'order' => $maxOrder + 1,
                    ]);
                    
                    Log::info("Material Import - Image imported successfully", [
                        'material_name' => $materialName,
                        'material_id' => $material->id,
                        'matched_image_file' => $matchedImageFileName,
                        'source_path' => $sourceImagePath,
                        'destination_path' => $destinationPath,
                    ]);
                } else {
                    Log::info("Material Import - Image already exists for product", [
                        'material_name' => $materialName,
                        'material_id' => $material->id,
                        'image_path' => $destinationPath,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the import
                Log::error("Material Import - Failed to import image for product", [
                    'material_name' => $materialName,
                    'material_id' => $material->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Add to unmatched list if image copy failed
                $this->unmatchedProducts[] = $materialName;
            }
        } catch (\Exception $e) {
            // Log error but don't fail the import
            Log::error("Material Import - Error in handleImageImport", [
                'material_name' => $materialName,
                'material_id' => $material->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Add to unmatched list on error
            $this->unmatchedProducts[] = $materialName;
        }
    }

    /**
     * Save unmatched products to a file
     * 
     * @return void
     */
    protected function saveUnmatchedProductsFile(): void
    {
        if (empty($this->unmatchedProducts)) {
            return;
        }

        try {
            // Ensure storage/app/imports directory exists
            $importsDir = storage_path('app/imports');
            if (!File::exists($importsDir)) {
                File::makeDirectory($importsDir, 0755, true);
            }

            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $fileName = "unmatched_products_{$timestamp}.csv";
            $filePath = $importsDir . '/' . $fileName;

            // Create CSV content
            $lines = [];
            
            // CSV Header
            $lines[] = "S.No,Product Name";
            
            // CSV Data rows - properly escape values that might contain commas or quotes
            foreach ($this->unmatchedProducts as $index => $productName) {
                $serialNumber = $index + 1;
                // Escape product name if it contains commas, quotes, or newlines
                $escapedProductName = $this->escapeCsvValue($productName);
                $lines[] = "{$serialNumber},{$escapedProductName}";
            }

            // Join all lines with newline character
            $content = implode("\n", $lines);
            
            // Add BOM for UTF-8 to ensure proper Excel compatibility
            $bom = "\xEF\xBB\xBF";
            $content = $bom . $content;

            File::put($filePath, $content);
            
            $this->unmatchedProductsFile = $filePath;
            
            Log::info("Material Import - Unmatched products saved to CSV file", [
                'file_path' => $filePath,
                'count' => count($this->unmatchedProducts),
            ]);
        } catch (\Exception $e) {
            Log::error("Material Import - Failed to save unmatched products file", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Escape CSV value properly
     * If value contains comma, quote, or newline, wrap it in quotes and escape internal quotes
     * 
     * @param string $value
     * @return string
     */
    protected function escapeCsvValue(string $value): string
    {
        // If value contains comma, quote, or newline, wrap in quotes and escape internal quotes
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            // Escape double quotes by doubling them
            $value = str_replace('"', '""', $value);
            // Wrap in quotes
            $value = '"' . $value . '"';
        }
        return $value;
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

    public function getUnmatchedProducts(): array
    {
        return $this->unmatchedProducts;
    }

    public function getUnmatchedProductsFile(): ?string
    {
        return $this->unmatchedProductsFile;
    }

    public function getUnmatchedProductsCount(): int
    {
        return count($this->unmatchedProducts);
    }
}