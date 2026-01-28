<?php

declare(strict_types=1);

use App\Utility\Enums\WastageTypeEnum;
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
        Schema::create('wastages', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->comment('App\\Utility\\Enums\\WastageTypeEnum');
            $table->unsignedBigInteger('manager_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->date('date');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('manager_id')->references('id')->on('moderators')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });

        Schema::create('wastage_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('wastage_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('wastage_qty')->default(0);
            $table->string('unit_type')->nullable();
            $table->timestamps();

            $table->foreign('wastage_id')->references('id')->on('wastages')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wastage_products');
        Schema::dropIfExists('wastages');
    }
};

