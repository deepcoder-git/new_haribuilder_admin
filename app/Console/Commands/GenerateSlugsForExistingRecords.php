<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\Site;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class GenerateSlugsForExistingRecords extends Command
{
    protected $signature = 'slugs:generate {--model=all : Model name (all, product, site, category, supplier)}';

    protected $description = 'Generate slugs for existing records in the database';

    public function handle()
    {
        $model = $this->option('model');

        if ($model === 'all' || $model === 'product') {
            if (Schema::hasTable('products') && Schema::hasColumn('products', 'slug')) {
            $this->generateSlugsForModel(Product::class, 'product_name', 'products');
            } else {
                $this->info('Skipping products: slug column does not exist.');
            }
        }

        if ($model === 'all' || $model === 'site') {
            $this->generateSlugsForModel(Site::class, 'name', 'sites');
        }

        if ($model === 'all' || $model === 'category') {
            $this->generateSlugsForModel(Category::class, 'name', 'categories');
        }

        if ($model === 'all' || $model === 'supplier') {
            $this->generateSlugsForModel(Supplier::class, 'name', 'suppliers');
        }

        $this->info('Slug generation completed!');
    }

    protected function generateSlugsForModel(string $modelClass, string $sourceField, string $modelName): void
    {
        $this->info("Generating slugs for {$modelName}...");

        $records = $modelClass::whereNull('slug')->orWhere('slug', '')->get();
        $count = 0;

        foreach ($records as $record) {
            $sourceValue = $record->{$sourceField} ?? '';

            if (empty($sourceValue)) {
                continue;
            }

            $slug = Str::slug($sourceValue);
            $originalSlug = $slug;
            $counter = 1;

            while ($modelClass::where('slug', $slug)->where('id', '!=', $record->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $record->slug = $slug;
            $record->save();
            $count++;
        }

        $this->info("Generated {$count} slugs for {$modelName}");
    }
}
