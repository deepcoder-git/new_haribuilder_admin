<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Utility\Enums\ProductTypeEnum;
use Carbon\Carbon;

class ProductLoadTestSeeder extends Seeder
{
    public function run(): void
    {
        $count = 100_000;
        $chunkSize = 1_000;
        $now = Carbon::now();

        $categoryId = Category::value('id') ?? Category::create([
            'name' => 'Load Test Category',
            'status' => true,
        ])->id;

        $this->command?->info("Seeding {$count} products for load testing...");

        $batch = [];
        for ($i = 1; $i <= $count; $i++) {
            $name = "Load Test Product {$i}";
            $batch[] = [
                'product_name' => $name,
                'slug' => Str::slug($name) . '-' . $i,
                'category_id' => $categoryId,
                'unit_type' => 'Piece',
                'type' => ProductTypeEnum::Customize->value,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $chunkSize) {
                DB::table('products')->insert($batch);
                $batch = [];
            }
        }

        if ($batch) {
            DB::table('products')->insert($batch);
        }

        $this->command?->info("✅ Seeded {$count} products");
    }
}
