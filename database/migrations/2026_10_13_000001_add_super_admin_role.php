<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUPER_ADMIN_EMAIL = 'hanapbuhay1234@gmail.com';

    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('client', 'worker', 'admin', 'super_admin') NULL DEFAULT NULL");

        DB::table('users')
            ->whereRaw('LOWER(email) = ?', [self::SUPER_ADMIN_EMAIL])
            ->update([
                'role' => 'super_admin',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'super_admin')
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);

        DB::statement("ALTER TABLE users MODIFY role ENUM('client', 'worker', 'admin') NULL DEFAULT NULL");
    }
};
