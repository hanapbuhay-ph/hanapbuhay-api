<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code', 6);
            $table->enum('type', ['email_verification', 'password_reset']);
            $table->timestamp('expires_at');
            // used_at replaces a boolean is_used flag — one column gives
            // both the "was it used?" check and the exact timestamp it was used.
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            // Speeds up the lookup in EmailVerificationController:
            // WHERE email = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()
            $table->index(['email', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
