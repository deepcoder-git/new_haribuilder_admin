<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->index('order_id', 'order_products_order_id_foreign');
            $table->index('product_id', 'order_products_product_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};


