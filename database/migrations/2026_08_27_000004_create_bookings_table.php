<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('client_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('worker_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('service_category_id')
                  ->constrained('service_categories');
            $table->text('notes')->nullable();
            $table->dateTime('scheduled_at');
            $table->enum('status', [
                'pending',
                'accepted',
                'declined',
                'active',
                'completed',
                'cancelled',
            ])->default('pending');
            $table->enum('cancelled_by', ['client', 'worker', 'admin'])->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('is_client_tracking')->default(false);
            $table->boolean('is_worker_tracking')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('force_cancelled_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
