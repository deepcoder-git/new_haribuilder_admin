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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->string('supplier_type')->default('General Supplier');

            $table->string('email');
            $table->string('phone');

            $table->text('address')->nullable();
            $table->text('description')->nullable();

            // Renamed from gst_no -> tin_number in your old migrations
            $table->string('tin_number')->nullable();

            // documents column intentionally removed (was dropped in old migrations)

            $table->boolean('status')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};


