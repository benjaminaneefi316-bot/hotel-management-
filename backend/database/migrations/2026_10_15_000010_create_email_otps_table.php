<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email one-time passwords (OTP) used as the second authentication factor.
 *
 * SECURITY PROPERTIES
 *   - `code_hash` stores Hash::make($code) (Argon2id/bcrypt). The plaintext
 *     code is never written to the database or the log.
 *   - codes expire after `security.otp_expiry_minutes` (default 5 minutes).
 *   - each code is single use: verification sets `consumed_at`.
 *   - requesting a new code invalidates older unused codes for the same
 *     purpose (see OtpService::issue()).
 *   - `attempts` / `max_attempts` block brute force per code.
 *   - resend frequency is throttled using `last_sent_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 40)->default('login_2fa');

            $table->string('code_hash', 255);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);

            $table->timestamp('last_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otps');
    }
};
