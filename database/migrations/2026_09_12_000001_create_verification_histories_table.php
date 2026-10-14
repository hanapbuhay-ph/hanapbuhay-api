<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')
                ->constrained('worker_profiles')
                ->cascadeOnDelete();
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['worker_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_histories');
    }
};
