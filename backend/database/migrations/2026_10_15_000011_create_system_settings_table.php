<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application configuration / settings.
 *
 * Only rows whose `key` is listed as eligible in config/restore-points.php
 * (and not containing any secret) may be included in a configuration snapshot
 * or restored by a configuration-only restore.
 *
 * Passwords, password hashes, salts, peppers, OTPs, tokens and encryption
 * keys are stored elsewhere and are explicitly EXCLUDED from snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->string('group', 60)->default('general')->index();
            $table->string('type', 20)->default('string');  // string|integer|boolean|decimal|json
            $table->string('description', 255)->nullable();
            $table->boolean('is_public')->default(false);   // readable by the mobile API
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
