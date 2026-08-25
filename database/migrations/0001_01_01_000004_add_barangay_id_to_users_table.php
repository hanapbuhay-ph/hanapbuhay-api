<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Added after barangays table exists (migration 000003).
            // Nullable because Google OAuth users complete their profile
            // in a second step and won't have a barangay on first insert.
            $table->foreignId('barangay_id')
                  ->nullable()
                  ->after('remember_token')
                  ->constrained('barangays')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['barangay_id']);
            $table->dropColumn('barangay_id');
        });
    }
};
