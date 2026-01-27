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

        // Drop index that includes sale_date, then drop the column itself if it exists.
        Schema::table('orders', function (Blueprint $table) {
            // First, try to drop any foreign key that might be using this index name.
            // Some environments may have created a foreign key constraint with the
            // same name as the index (`orders_site_id_sale_date_index`), which would
            // prevent the index from being dropped directly.
            try {
                $table->dropForeign('orders_site_id_sale_date_index');
            } catch (\Throwable $e) {
                // Foreign key might not exist; ignore
            }

            // Guarded drop of composite index if present
            try {
                $table->dropIndex('orders_site_id_sale_date_index');
            } catch (\Throwable $e) {
                // Index might not exist on some environments; ignore
            }

            if (Schema::hasColumn('orders', 'sale_date')) {
                $table->dropColumn('sale_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'sale_date')) {
                $table->date('sale_date')->nullable()->after('site_id');
            }

            // Recreate index in a safe way
            try {
                $table->index(['site_id', 'sale_date'], 'orders_site_id_sale_date_index');
            } catch (\Throwable $e) {
                // Ignore if index already exists
            }
        });
    }
};

