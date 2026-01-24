<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('site_id')
                ->nullable()
                ->constrained('sites')
                ->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->integer('quantity')->default(0);

            $table->enum('adjustment_type', ['in', 'out', 'adjustment'])->default('adjustment');

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);

            $table->timestamps();

            // Indexes from old schema
            $table->index('site_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['product_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};


