<?php

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
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            // Drop index first if it exists
            if (Schema::hasColumn('orders', 'store')) {
                $indexes = Schema::getConnection()
                    ->getDoctrineSchemaManager()
                    ->listTableIndexes('orders');
                
                foreach ($indexes as $index) {
                    if (in_array('store', $index->getColumns())) {
                        $table->dropIndex('orders_store_index');
                        break;
                    }
                }
                
                $table->dropColumn('store');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'store')) {
                $table->string('store')->nullable()->after('site_manager_id');
                $table->index('store', 'orders_store_index');
            }
        });
    }
};
