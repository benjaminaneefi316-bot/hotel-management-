<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel guests.
 *
 * Sensitive columns are stored as AES-256-GCM ciphertext produced by the
 * custom cast App\Support\Casts\Aes256Gcm (see docs/SECURITY.md):
 *
 *   phone, national_id, address   -> "gcm1:<base64(nonce|ciphertext|tag)>"
 *
 * The database therefore never contains the plaintext values in ordinary
 * form, which is asserted by GuestEncryptionTest. The encryption key lives
 * in APP_ENCRYPTION_KEY (environment / AWS Secrets Manager), not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();

            // Optional link to a self-service guest account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 190)->nullable()->index();
            $table->date('date_of_birth')->nullable();

            // --- Encrypted (AES-256-GCM) ---------------------------------
            $table->string('phone', 255)->nullable();
            $table->string('national_id', 255)->nullable();
            $table->string('address', 255)->nullable();
            // ---------------------------------------------------------------

            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('id_type', 40)->nullable();     // passport / national id
            $table->string('nationality', 80)->nullable();
            $table->text('notes')->nullable();             // operational notes (non-secret)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index(['country', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
