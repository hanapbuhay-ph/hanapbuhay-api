<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')
                  ->constrained('worker_profiles')
                  ->cascadeOnDelete();
            $table->foreignId('service_category_id')
                  ->constrained('service_categories')
                  ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['worker_profile_id', 'service_category_id'], 'wsc_worker_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_service_categories');
    }
};
