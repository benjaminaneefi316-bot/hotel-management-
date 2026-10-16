<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current role assignments (one user may hold several roles).
 *
 * - `assigned_by` records which administrator granted the role.
 * - Full role-change history (actor, previous role, new role, timestamp,
 *   outcome) lives in `audit_logs`, not here. This table only answers
 *   "what roles does this user have right now?"
 *
 * Role assignments can be included in a configuration snapshot and therefore
 * be reverted by a configuration-only restore (see docs/BACKUP_RESTORE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
