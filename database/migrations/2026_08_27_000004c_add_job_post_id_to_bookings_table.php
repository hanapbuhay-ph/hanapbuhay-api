<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('job_post_id')
                  ->nullable()
                  ->after('service_category_id')
                  ->constrained('job_posts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['job_post_id']);
            $table->dropColumn('job_post_id');
        });
    }
};
