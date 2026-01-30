<?php

use App\Utility\Enums\WastageStatusEnum;
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
        Schema::table('wastages', function (Blueprint $table) {
            $enumValues = array_map(
                static fn (WastageStatusEnum $e) => $e->value,
                WastageStatusEnum::cases()
            );
            
            $table->enum('status', $enumValues)
                ->default(WastageStatusEnum::Approved->value)
                ->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wastages', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
