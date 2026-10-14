<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_reasons', function (Blueprint $table): void {
            $table->string('audience', 20)->default('client')->after('description');
            $table->index('audience');
        });

        DB::table('report_reasons')->insert([
            ['code' => 'client_no_show', 'label' => 'Client No-Show', 'description' => 'The client did not attend the agreed schedule.', 'audience' => 'worker', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'client_non_payment', 'label' => 'Non-Payment', 'description' => 'The client failed to pay as agreed.', 'audience' => 'worker', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'client_misconduct', 'label' => 'Client Misconduct', 'description' => 'The client behaved inappropriately or unprofessionally.', 'audience' => 'worker', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'unsafe_client_behavior', 'label' => 'Unsafe Client Behavior', 'description' => 'The client created an unsafe working environment.', 'audience' => 'worker', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'false_client_information', 'label' => 'False Client Information', 'description' => 'The client provided false or misleading information.', 'audience' => 'worker', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('report_reasons')->whereIn('code', ['client_no_show', 'client_misconduct', 'unsafe_client_behavior', 'false_client_information'])->delete();
        Schema::table('report_reasons', function (Blueprint $table): void {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });
    }
};