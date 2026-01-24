<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_purchase_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_purchase_id')
                ->constrained('product_purchases')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->integer('quantity');
            $table->integer('unit_price')->default(0);
            $table->integer('total_price')->default(0);

            $table->timestamps();

            // Explicit indexes to mirror legacy schema (foreignId already creates indexes).
            $table->index('product_purchase_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_items');
    }
};


