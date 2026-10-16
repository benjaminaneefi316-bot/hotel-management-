<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only security / operations audit trail.
 *
 * Recorded events include login outcomes, 2FA outcomes, role changes,
 * access denials, user archiving, backup results and configuration restores.
 *
 * NEVER stored here: passwords, OTP codes, pepper values, encryption keys,
 * or full payment card details. `old_values` / `new_values` are filtered
 * through the redaction list in App\Services\AuditLogger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_username', 80)->nullable();  // survives archiving

            $table->string('action', 80)->index();             // e.g. "user.role.change"
            $table->string('event_type', 40)->index();         // auth|security|data|system|admin
            $table->text('description')->nullable();

            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('outcome', 20)->default('success')->index(); // success|failure|denied

            $table->timestamps();

            $table->index('created_at');
            $table->index(['event_type', 'outcome']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
