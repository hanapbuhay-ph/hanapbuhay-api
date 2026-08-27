<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hanapbuhay_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('type', [
                'booking_request',
                'booking_accepted',
                'booking_declined',
                'booking_completed',
                'booking_cancelled',
                'verification_approved',
                'verification_rejected',
                'verification_resubmit',
                'new_message',
                'new_rating',
                'report_resolved',
                'system_announcement',
                'trust_tier_updated',
            ]);
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hanapbuhay_notifications');
    }
};
