<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive of deactivated (soft-deleted) user accounts.
 *
 * Archiving is NOT permanent deletion. When an administrator archives a user
 * the application:
 *   1. sets users.status = 'archived' (blocks authentication immediately),
 *   2. copies the account into this table inside a DB transaction,
 *   3. records actor, timestamp and reason,
 *   4. leaves all business rows (bookings, stays, audit) untouched so their
 *      foreign-key history stays consistent.
 *
 * `password_hash` / `password_salt` are copied so the account can be restored
 * with working credentials. They are the SAME stored hash - never a plaintext
 * password - and are never displayed, exported or logged.
 * `original_user_id` is intentionally NOT a foreign key: it is a historical
 * reference that must survive any future changes to `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id')->index();
            $table->string('name', 120);
            $table->string('username', 60);
            $table->string('email', 190);

            $table->string('password_hash');
            $table->string('password_salt', 64);
            $table->string('status_at_archive', 20)->default('archived');
            $table->boolean('two_factor_enabled')->default(true);
            $table->json('roles')->nullable();
            $table->json('attributes')->nullable();     // full user row snapshot

            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('archived_at');

            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_users');
    }
};
