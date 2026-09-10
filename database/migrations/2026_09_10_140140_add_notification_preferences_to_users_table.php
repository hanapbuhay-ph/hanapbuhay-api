<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_booking_updates')->default(true)->after('is_active');
            $table->boolean('notify_messages')->default(true)->after('notify_booking_updates');
            $table->boolean('notify_promotions')->default(false)->after('notify_messages');
            $table->boolean('notify_push')->default(true)->after('notify_promotions');
            $table->boolean('notify_email')->default(true)->after('notify_push');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notify_booking_updates',
                'notify_messages',
                'notify_promotions',
                'notify_push',
                'notify_email',
            ]);
        });
    }
};
