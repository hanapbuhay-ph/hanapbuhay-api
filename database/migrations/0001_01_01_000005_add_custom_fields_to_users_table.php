<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // password is non-nullable in the scaffold; make it nullable
            // here so Google OAuth users can register without one.
            $table->string('password')->nullable()->change();

            $table->string('mobile_number')->nullable()->after('email');
            $table->enum('role', ['client', 'worker', 'admin'])
                  ->default('client')
                  ->after('mobile_number');
            $table->string('profile_photo_path')->nullable()->after('role');
            $table->string('google_id')->nullable()->unique()->after('profile_photo_path');
            $table->boolean('is_google_account')->default(false)->after('google_id');
            $table->boolean('is_active')->default(true)->after('is_google_account');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mobile_number',
                'role',
                'profile_photo_path',
                'google_id',
                'is_google_account',
                'is_active',
            ]);
            $table->dropSoftDeletes();
            $table->string('password')->nullable(false)->change();
        });
    }
};
