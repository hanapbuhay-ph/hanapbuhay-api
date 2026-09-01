<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')
                  ->constrained('worker_profiles')
                  ->cascadeOnDelete();
            $table->foreignId('service_category_id')
                  ->constrained('service_categories')
                  ->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->decimal('rate_amount', 10, 2);
            $table->enum('rate_type', [
                'hourly',
                'daily',
                'weekly',
                'monthly',
                'per_session',
                'per_project',
            ]);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posts');
    }
};
