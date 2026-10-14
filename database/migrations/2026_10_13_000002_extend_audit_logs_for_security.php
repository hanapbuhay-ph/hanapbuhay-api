<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE admin_audit_logs
            DROP FOREIGN KEY admin_audit_logs_admin_id_foreign,
            MODIFY admin_id BIGINT UNSIGNED NULL
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE admin_audit_logs
            ADD COLUMN actor_name VARCHAR(255) NULL AFTER admin_id,
            ADD COLUMN actor_email VARCHAR(255) NULL AFTER actor_name,
            ADD COLUMN category VARCHAR(32) NOT NULL DEFAULT 'admin_activity' AFTER actor_email,
            ADD COLUMN outcome VARCHAR(16) NULL AFTER category,
            ADD COLUMN user_agent VARCHAR(1024) NULL AFTER ip_address,
            ADD INDEX admin_audit_logs_category_created_at_index (category, created_at)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE admin_audit_logs
            ADD CONSTRAINT admin_audit_logs_admin_id_foreign
                FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
            SQL);

        DB::statement(<<<'SQL'
            UPDATE admin_audit_logs
            INNER JOIN users ON users.id = admin_audit_logs.admin_id
            SET admin_audit_logs.actor_name = users.name,
                admin_audit_logs.actor_email = users.email
            SQL);
    }

    public function down(): void
    {
        if (DB::table('admin_audit_logs')->whereNull('admin_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back security audit logs while records have no linked actor.'
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE admin_audit_logs
            DROP FOREIGN KEY admin_audit_logs_admin_id_foreign,
            DROP INDEX admin_audit_logs_category_created_at_index,
            DROP COLUMN actor_name,
            DROP COLUMN actor_email,
            DROP COLUMN category,
            DROP COLUMN outcome,
            DROP COLUMN user_agent,
            MODIFY admin_id BIGINT UNSIGNED NOT NULL
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE admin_audit_logs
            ADD CONSTRAINT admin_audit_logs_admin_id_foreign
                FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
            SQL);
    }
};
