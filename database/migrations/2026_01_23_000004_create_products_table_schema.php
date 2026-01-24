<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Your DB already has `products` (as per phpMyAdmin screenshot).
        // This migration is written to be safe for both:
        // - fresh installs (create table)
        // - existing installs (only add missing columns)
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();

                $table->string('product_name');

                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained('categories')
                    ->nullOnDelete();

                $table->string('type')->nullable();
                $table->string('store')->nullable();

                $table->foreignId('store_manager_id')
                    ->nullable()
                    ->constrained('moderators')
                    ->nullOnDelete();

                $table->string('image')->nullable();
                $table->string('unit_type')->nullable();
                $table->integer('low_stock_threshold')->nullable();
                $table->integer('available_qty')->nullable();

                $table->boolean('status')->default(true);
                $table->tinyInteger('is_product')
                    ->default(1)
                    ->comment('0 = Material Only, 1 = Material As Product, 2 = Material + Product');

                $table->timestamps();
            });

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            
            if (!Schema::hasColumn('products', 'product_name')) {
                $table->string('product_name')->after('id');
            }
            if (!Schema::hasColumn('products', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('products', 'type')) {
                $table->string('type')->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('products', 'store')) {
                $table->string('store')->nullable()->after('type');
            }
            if (!Schema::hasColumn('products', 'store_manager_id')) {
                $table->unsignedBigInteger('store_manager_id')->nullable()->after('store');
            }
            if (!Schema::hasColumn('products', 'image')) {
                $table->string('image')->nullable()->after('store_manager_id');
            }
            if (!Schema::hasColumn('products', 'unit_type')) {
                $table->string('unit_type')->nullable()->after('image');
            }
            if (!Schema::hasColumn('products', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->nullable()->after('unit_type');
            }
            if (!Schema::hasColumn('products', 'available_qty')) {
                $table->integer('available_qty')->nullable()->after('low_stock_threshold');
            }
            if (!Schema::hasColumn('products', 'status')) {
                $table->boolean('status')->default(true)->after('available_qty');
            }
            if (!Schema::hasColumn('products', 'is_product')) {
                $table->tinyInteger('is_product')
                    ->default(1)
                    ->comment('0 = Material Only, 1 = Material As Product, 2 = Material + Product')
                    ->after('status');
            }
            if (!Schema::hasColumn('products', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('products', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Not dropping `products` on rollback, because in many environments it pre-exists with real data.
        // If you want a "fresh install only" migration that drops on rollback, tell me and I’ll create a separate one.
    }
};