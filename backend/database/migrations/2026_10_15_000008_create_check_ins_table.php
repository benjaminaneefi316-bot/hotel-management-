<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actual stay records: one row per completed check-in.
 *
 * Valid `status` values (see App\Enums\StayStatus):
 *   active    - guest is currently in the room
 *   completed - guest has checked out
 *   cancelled - stay was cancelled before/at arrival
 *
 * Business rules enforced by App\Services\CheckinService:
 *   - a room may only have ONE row in `active` status at any time
 *   - a stay cannot be checked out twice
 *   - check-out requires a prior check-in
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();

            $table->string('status', 20)->default('active')->index();
            $table->timestamp('checked_in_at');
            $table->foreignId('checked_in_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('actual_nights')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
