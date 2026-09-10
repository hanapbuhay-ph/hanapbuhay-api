<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hanapbuhay_notifications', function (Blueprint $table) {
            $table->string('type', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('hanapbuhay_notifications', function (Blueprint $table) {
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
            ])->nullable(false)->change();
        });
    }
};
