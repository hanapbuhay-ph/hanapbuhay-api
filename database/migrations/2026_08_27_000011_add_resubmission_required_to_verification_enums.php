<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alter worker_profiles.verification_status enum
        DB::statement("
            ALTER TABLE `worker_profiles`
            MODIFY COLUMN `verification_status`
            ENUM('unverified','pending','approved','rejected','resubmission_required')
            NOT NULL DEFAULT 'unverified'
        ");

        // Alter verification_documents.status enum
        DB::statement("
            ALTER TABLE `verification_documents`
            MODIFY COLUMN `status`
            ENUM('pending','approved','rejected','resubmission_required')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `worker_profiles`
            MODIFY COLUMN `verification_status`
            ENUM('unverified','pending','approved','rejected')
            NOT NULL DEFAULT 'unverified'
        ");

        DB::statement("
            ALTER TABLE `verification_documents`
            MODIFY COLUMN `status`
            ENUM('pending','approved','rejected')
            NOT NULL DEFAULT 'pending'
        ");
    }
};
