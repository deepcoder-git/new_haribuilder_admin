<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_purchases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('suppliers');

            $table->date('purchase_date')->index();

            $table->string('purchase_number')->unique();
            // Keep a separate non-unique index to mirror your legacy schema (even though unique already indexes it).
            $table->index('purchase_number');

            $table->integer('total_amount')->default(0);
            $table->text('notes')->nullable();

            $table->boolean('status')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchases');
    }
};


