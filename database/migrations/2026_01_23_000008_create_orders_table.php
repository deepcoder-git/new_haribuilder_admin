<?php

use App\Utility\Enums\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_manager_id')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();

            $table->string('store')->nullable();

            $table->foreignId('transport_manager_id')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();

            $table->foreignId('site_id')
                ->nullable()
                ->constrained('sites')
                ->nullOnDelete();

            $table->date('sale_date');
            $table->date('expected_delivery_date')->nullable();

            // NOTE: Removed fields per request:
            // - store_manager_role
            // - product_id
            // - delivery_status
            // - customer_image
            // - document_details

            $table->integer('quantity')->nullable();
            $table->integer('amount')->nullable();

            $table->enum('status', array_map(static fn (OrderStatusEnum $e) => $e->value, OrderStatusEnum::cases()))
                ->default(OrderStatusEnum::Pending->value);

            $table->json('product_status')->nullable();
            $table->json('product_driver_details')->nullable();

            $table->string('priority')->nullable();
            $table->text('note')->nullable();
            $table->text('rejected_note')->nullable();
            $table->json('product_rejection_notes')->nullable();

            $table->string('drop_location')->nullable();

            $table->boolean('is_completed')->default(false);
            $table->boolean('is_lpo')->default(false);
            $table->boolean('is_custom_product')->default(false);

            // Stored as JSON mapping: {product_id: supplier_id, ...}
            $table->json('supplier_id')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes (adjusted because product_id/delivery_status/store_manager_role were removed)
            $table->index(['site_id', 'sale_date'], 'orders_site_id_sale_date_index');
            $table->index('transport_manager_id', 'orders_transport_manager_id_index');
            $table->index('drop_location', 'orders_drop_location_index');
            $table->index('store', 'orders_store_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};


