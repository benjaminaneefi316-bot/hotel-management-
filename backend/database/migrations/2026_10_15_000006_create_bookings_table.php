<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservations.
 *
 * Valid `status` values (see App\Enums\BookingStatus):
 *   pending | confirmed | checked_in | checked_out | cancelled | no_show
 *
 * Date semantics: `check_in_date` is the first night, `check_out_date` is the
 * departure day (the guest does NOT stay the night of check_out_date). Two
 * bookings therefore conflict only when
 *
 *     a.check_in_date < b.check_out_date AND b.check_in_date < a.check_out_date
 *
 * Overlap is prevented with a locking SELECT ... FOR UPDATE inside a
 * transaction (see app/Services/BookingService.php) so the rule works on
 * MySQL as well as SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();     // HM-2026-0001
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // created by

            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);

            $table->string('status', 30)->default('pending')->index();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('source', 30)->default('web');   // web | walk_in | phone | mobile
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();     // staff only, never shown to guests

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['check_in_date', 'check_out_date']);
            $table->index(['guest_id', 'status']);
            $table->index(['status', 'check_in_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
