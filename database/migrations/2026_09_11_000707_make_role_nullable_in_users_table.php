<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Raw SQL for altering enum is often required on MySQL/MariaDB
            // because Laravel's change() method has limitations with ENUMs.
            DB::statement("ALTER TABLE users MODIFY role ENUM('client', 'worker', 'admin') NULL DEFAULT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revert back to default 'client', non-nullable
            DB::statement("UPDATE users SET role = 'client' WHERE role IS NULL");
            DB::statement("ALTER TABLE users MODIFY role ENUM('client', 'worker', 'admin') NOT NULL DEFAULT 'client'");
        });
    }
};

