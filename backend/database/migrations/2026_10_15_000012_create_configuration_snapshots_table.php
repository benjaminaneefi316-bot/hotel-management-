<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration-only restore points.
 *
 * A snapshot captures SELECTED application settings and (optionally) role
 * assignments at a moment in time. It deliberately contains NO business data
 * and NO secrets:
 *
 *   excluded: passwords, password hashes, password salts, peppers, OTPs,
 *             access tokens, encryption keys, guests, bookings, payments,
 *             rooms, stays, audit history
 *
 * Restoring a snapshot writes ONLY the captured values back, inside a single
 * database transaction. See docs/BACKUP_RESTORE.md.
 *
 * `checksum` is sha256 over the canonical JSON of the snapshot items and is
 * verified before every restore to detect tampering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->string('schema_version', 20)->default('1.0');
            $table->boolean('includes_role_assignments')->default(false);
            $table->unsignedInteger('item_count')->default(0);
            $table->string('checksum', 160);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->timestamp('last_restored_at')->nullable();
            $table->unsignedInteger('restore_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_snapshots');
    }
};
