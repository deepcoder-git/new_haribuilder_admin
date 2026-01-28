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
        // If the table already exists (e.g. from a manual create or older project),
        // skip creating it again to avoid "Base table already exists" errors.
        if (Schema::hasTable('sites')) {
            return;
        }

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique()->nullable();

            $table->string('location');
            $table->string('type')->nullable();
            $table->string('work_type')->nullable();

            $table->foreignId('site_manager_id')
                ->nullable()
                ->constrained('moderators')
                ->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date')->nullable();

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
        Schema::dropIfExists('sites');
    }
};


