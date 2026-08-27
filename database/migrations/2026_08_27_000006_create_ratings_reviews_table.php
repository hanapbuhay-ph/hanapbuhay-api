<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                  ->constrained('bookings')
                  ->cascadeOnDelete();
            $table->foreignId('rated_by')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('rated_user')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'rated_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings_reviews');
    }
};
