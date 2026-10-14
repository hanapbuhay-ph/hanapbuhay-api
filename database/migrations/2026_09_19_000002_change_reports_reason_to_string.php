<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Report reasons are admin-managed records, so this column cannot be a
        // fixed enum without rejecting newly created reason codes.
        DB::statement("ALTER TABLE reports MODIFY reason VARCHAR(100) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reports MODIFY reason ENUM('no_show','unsatisfactory_work','misconduct','non_payment','unsafe_environment','abusive_behavior','false_information','other') NOT NULL");
    }
};