<?php

use App\Utility\Enums\CreatorTypeEnum;
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
        Schema::table('order_returns', function (Blueprint $table) {
            $enumValues = array_map(
                static fn (CreatorTypeEnum $e) => $e->value,
                CreatorTypeEnum::cases()
            );
            
            $table->enum('creator_type', $enumValues)
                ->default(CreatorTypeEnum::Other->value)
                ->after('manager_id')
                ->comment('Who created this return: store_manager, site_manager, or other');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropColumn('creator_type');
        });
    }
};
