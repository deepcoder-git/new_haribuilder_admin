<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table): void {
            $table->id();

            // Original order for which return is created
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Site (if applicable)
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('sites')
                ->nullOnDelete();

            // Manager / user who created the return (usually a moderator)
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();

            // High-level return type (e.g. full, partial) – free string for now
            $table->string('type')->nullable()->comment('Type of return e.g. full, partial');

            // Business date of the return
            $table->date('date');

            // Simple lifecycle status for the return
            $table->string('status')
                ->default('pending')
                ->comment('pending, approved, rejected, completed, etc.');

            // Optional reason / note for the return
            $table->text('reason')->nullable();

            $table->timestamps();

            // Helpful indexes
            $table->index('order_id', 'order_returns_order_id_index');
            $table->index('site_id', 'order_returns_site_id_index');
            $table->index('manager_id', 'order_returns_manager_id_index');
            $table->index('status', 'order_returns_status_index');
        });

        Schema::create('order_return_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('order_return_id')
                ->constrained('order_returns')
                ->cascadeOnDelete();

            // Link back to original order (optional but helpful for reporting)
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            // Original ordered quantity (for reference)
            $table->unsignedInteger('ordered_quantity')->default(0);

            // Quantity being returned
            $table->unsignedInteger('return_quantity')->default(0);

            // Unit type (same as in stocks / wastage if needed)
            $table->string('unit_type')->nullable();

            $table->timestamps();

            $table->index('order_return_id', 'order_return_items_return_id_index');
            $table->index('order_id', 'order_return_items_order_id_index');
            $table->index('product_id', 'order_return_items_product_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};

