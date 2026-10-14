<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 48)->index();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'id']);
        });

        Schema::create('admin_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_notification_id')
                ->constrained('admin_notifications')
                ->cascadeOnDelete();
            $table->foreignId('admin_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['admin_notification_id', 'admin_id']);
            $table->index(['admin_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_reads');
        Schema::dropIfExists('admin_notifications');
    }
};
