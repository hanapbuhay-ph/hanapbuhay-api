<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'status')) {
                $table->string('status')->default('published')->after('body');
            }

            if (! Schema::hasColumn('announcements', 'target_audience')) {
                $table->string('target_audience')->default('all')->after('status');
            }
        });

        DB::table('announcements')->whereNull('status')->update([
            'status' => 'published',
        ]);

        DB::table('announcements')->whereNull('target_audience')->update([
            'target_audience' => 'all',
        ]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['status', 'target_audience']);
        });
    }
};
