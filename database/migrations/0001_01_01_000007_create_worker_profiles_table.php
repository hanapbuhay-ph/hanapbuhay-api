<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->enum('verification_status', [
                'unverified',
                'pending',
                'approved',
                'rejected',
            ])->default('unverified');
            $table->enum('trust_tier', [
                'verified',
                'trusted',
                'flagged',
                'revoked',
            ])->nullable();
            $table->enum('availability_status', [
                'available',
                'busy',
                'offline',
            ])->default('offline');
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->integer('completed_jobs')->default(0);
            $table->string('verification_remarks')->nullable();
            $table->foreignId('verified_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_profiles');
    }
};
