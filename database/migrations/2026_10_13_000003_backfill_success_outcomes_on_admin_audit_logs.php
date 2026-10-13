<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_audit_logs')
            ->where('category', 'admin_activity')
            ->whereNull('outcome')
            ->update(['outcome' => 'success']);
    }

    public function down(): void
    {
        DB::table('admin_audit_logs')
            ->where('category', 'admin_activity')
            ->where('outcome', 'success')
            ->update(['outcome' => null]);
    }
};
