<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                  ->nullable()
                  ->constrained('bookings')
                  ->nullOnDelete();
            $table->foreignId('reported_by')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('reported_user')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->enum('reason', [
                'no_show',
                'unsatisfactory_work',
                'misconduct',
                'non_payment',
                'unsafe_environment',
                'abusive_behavior',
                'false_information',
                'other',
            ]);
            $table->text('description');
            $table->json('evidence_paths')->nullable();
            $table->enum('status', ['under_review', 'resolved', 'dismissed'])->default('under_review');
            $table->text('admin_remarks')->nullable();
            $table->enum('resolution_action', [
                'warning_issued',
                'account_suspended',
                'verification_revoked',
                'no_action',
            ])->nullable();
            $table->foreignId('resolved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
