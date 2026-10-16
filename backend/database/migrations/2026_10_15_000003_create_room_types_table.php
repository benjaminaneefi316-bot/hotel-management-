<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories of rooms (Standard, Deluxe, Suite, ...).
 *
 * `image_url` points at a photograph used by the public room pages and the
 * dashboard room cards. URLs are validated by RoomType rules before saving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // STD, DLX, STE ...
            $table->string('name', 80);                    // "Deluxe King"
            $table->text('description')->nullable();
            $table->string('bed_type', 40);                // "1 King bed"
            $table->unsignedTinyInteger('capacity')->default(2);
            $table->decimal('base_price', 10, 2);          // per night
            $table->json('amenities')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
