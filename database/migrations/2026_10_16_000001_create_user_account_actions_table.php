<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_account_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('target_user_id')->index();
            $table->string('target_name');
            $table->string('target_email');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('action');
            $table->text('reason');
            $table->timestamp('scheduled_for')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_account_actions');
    }
};
