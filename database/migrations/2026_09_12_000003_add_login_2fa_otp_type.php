<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE otp_codes MODIFY type ENUM('email_verification', 'password_reset', 'login_2fa') NOT NULL");
    }

    public function down(): void
    {
        DB::table('otp_codes')->where('type', 'login_2fa')->update(['type' => 'password_reset']);
        DB::statement("ALTER TABLE otp_codes MODIFY type ENUM('email_verification', 'password_reset') NOT NULL");
    }
};
