<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual hotel rooms.
 *
 * Valid `status` values (see App\Enums\RoomStatus):
 *   available | occupied | cleaning | maintenance | out_of_service
 *
 * `nightly_rate` is an optional override; when NULL the room type's
 * base price applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number', 20)->unique();   // "101", "407B"
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('floor')->default(1);
            $table->string('status', 30)->default('available')->index();
            $table->decimal('nightly_rate', 10, 2)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('view', 60)->nullable();        // "Sea view", "City view"
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['room_type_id', 'status']);
            $table->index(['floor', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
