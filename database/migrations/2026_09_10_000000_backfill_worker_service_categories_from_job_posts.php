<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill worker_service_categories pivot from existing active job posts.
 *
 * Before this migration, JobPostService::create() did not sync the pivot table,
 * so workers who created job posts before the fix have no pivot rows and do not
 * appear in getWorkersByCategory() results.
 *
 * This is a data-only migration — no schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Gather distinct (worker_profile_id, service_category_id) pairs
        // from active job posts that have no pivot row yet.
        $missing = DB::table('job_posts as jp')
            ->select('jp.worker_profile_id', 'jp.service_category_id')
            ->whereNull('jp.deleted_at')
            ->where('jp.is_active', true)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('worker_service_categories as wsc')
                  ->whereColumn('wsc.worker_profile_id', 'jp.worker_profile_id')
                  ->whereColumn('wsc.service_category_id', 'jp.service_category_id');
            })
            ->distinct()
            ->get();

        foreach ($missing as $row) {
            DB::table('worker_service_categories')->insertOrIgnore([
                'worker_profile_id'   => $row->worker_profile_id,
                'service_category_id' => $row->service_category_id,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Not reversible — removing pivot rows could affect real data.
    }
};
