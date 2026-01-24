<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('material_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->decimal('quantity', 10, 2);
            $table->string('unit_type')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'material_id'], 'product_materials_product_id_material_id_unique');
            $table->index('material_id', 'product_materials_material_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_materials');
    }
};


