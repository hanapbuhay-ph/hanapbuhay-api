<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')
                  ->constrained('worker_profiles')
                  ->cascadeOnDelete();
            $table->enum('document_type', [
                'government_id',
                'barangay_certificate',
                'selfie_with_id',
                'skill_certificate',
            ]);
            $table->string('file_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_documents');
    }
};
