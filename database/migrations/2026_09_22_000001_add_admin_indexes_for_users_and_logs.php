<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active'], 'users_role_active_index');
            $table->index(['barangay_id', 'is_active'], 'users_barangay_active_index');
            $table->index('created_at', 'users_created_at_index');
            $table->index(['name', 'email'], 'users_name_email_index');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->index(['created_at', 'admin_id'], 'audit_logs_created_admin_index');
            $table->index(['target_type', 'target_id'], 'audit_logs_target_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_active_index');
            $table->dropIndex('users_barangay_active_index');
            $table->dropIndex('users_created_at_index');
            $table->dropIndex('users_name_email_index');
        });

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_created_admin_index');
            $table->dropIndex('audit_logs_target_index');
        });
    }
};
