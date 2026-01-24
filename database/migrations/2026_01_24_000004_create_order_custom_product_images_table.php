<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_custom_product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_custom_product_id')
                ->constrained('order_custom_products')
                ->cascadeOnDelete();

            $table->string('image_path');
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('order_custom_product_id', 'order_custom_product_images_order_custom_product_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_custom_product_images');
    }
};


