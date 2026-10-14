<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_portfolio_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')
                ->constrained('worker_profiles')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['worker_profile_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_portfolio_photos');
    }
};
