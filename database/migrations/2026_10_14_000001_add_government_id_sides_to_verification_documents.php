<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `verification_documents`
            MODIFY COLUMN `document_type`
            ENUM(
                'government_id',
                'government_id_front',
                'government_id_back',
                'barangay_certificate',
                'selfie_with_id',
                'skill_certificate'
            )
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::table('verification_documents')
            ->whereIn('document_type', ['government_id_front', 'government_id_back'])
            ->update(['document_type' => 'government_id']);

        DB::statement("
            ALTER TABLE `verification_documents`
            MODIFY COLUMN `document_type`
            ENUM(
                'government_id',
                'barangay_certificate',
                'selfie_with_id',
                'skill_certificate'
            )
            NOT NULL
        ");
    }
};
