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
        Schema::table('order_return_items', function (Blueprint $table): void {
            $table->boolean('adjust_stock')
                ->default(false)
                ->after('unit_type')
                ->comment('If true, this return item should update stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_return_items', function (Blueprint $table): void {
            $table->dropColumn('adjust_stock');
        });
    }
};

