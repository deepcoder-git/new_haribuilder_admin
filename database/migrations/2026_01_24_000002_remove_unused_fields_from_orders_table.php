<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        // Drop foreign keys first (if present), then drop the columns.
        Schema::table('orders', function (Blueprint $table) {
            // These were created via ->constrained('moderators')->nullOnDelete()
            if (Schema::hasColumn('orders', 'completed_by')) {
                $table->dropForeign(['completed_by']);
            }
            if (Schema::hasColumn('orders', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }
        });

        $columnsToDrop = [];
        foreach ([
            'store_manager_role',
            'product_id',
            'quantity',
            'amount',
            'delivery_status',
            'customer_image',
            'document_details',
            'is_completed',
            'completed_at',
            'completed_by',
            'approved_by',
            'approved_at',
            'deleted_at',
        ] as $col) {
            if (Schema::hasColumn('orders', $col)) {
                $columnsToDrop[] = $col;
            }
        }

        if (!empty($columnsToDrop)) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        // Best-effort rollback (types may differ from your legacy schema).
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'store_manager_role')) {
                $table->string('store_manager_role')->nullable()->after('store');
            }
            if (!Schema::hasColumn('orders', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('store_manager_role');
            }
            if (!Schema::hasColumn('orders', 'quantity')) {
                $table->integer('quantity')->nullable()->after('expected_delivery_date');
            }
            if (!Schema::hasColumn('orders', 'amount')) {
                $table->integer('amount')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('orders', 'customer_image')) {
                $table->string('customer_image')->nullable()->after('delivery_status');
            }
            if (!Schema::hasColumn('orders', 'document_details')) {
                $table->text('document_details')->nullable()->after('customer_image');
            }
            if (!Schema::hasColumn('orders', 'is_completed')) {
                $table->boolean('is_completed')->default(false)->after('drop_location');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_completed');
            }
            if (!Schema::hasColumn('orders', 'completed_by')) {
                $table->foreignId('completed_by')->nullable()->constrained('moderators')->nullOnDelete()->after('completed_at');
            }
            if (!Schema::hasColumn('orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('moderators')->nullOnDelete()->after('completed_by');
            }
            if (!Schema::hasColumn('orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('orders', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }
};


