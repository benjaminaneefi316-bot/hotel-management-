<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which concrete rooms a booking occupies (a booking may cover >1 room).
 *
 * `rate` is the negotiated nightly rate for this room on this booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->decimal('rate', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['booking_id', 'room_id']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
    }
};
