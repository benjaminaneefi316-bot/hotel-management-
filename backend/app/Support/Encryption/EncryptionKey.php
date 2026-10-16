<?php

declare(strict_types=1);

namespace App\Support\Encryption;

use RuntimeException;

/**
 * Resolves the application's 32-byte AES-256-GCM key.
 *
 * The key comes from APP_ENCRYPTION_KEY (base64) and is kept OUTSIDE the
 * database. In production it is injected from AWS Secrets Manager / SSM.
 *
 * Both the field encryptor and the backup file encryptor use this single
 * source of truth.
 */
final class EncryptionKey
{
    private ?string $bytes = null;

    /** Raw 32-byte key. */
    public function bytes(): string
    {
        if ($this->bytes !== null) {
            return $this->bytes;
        }

        $encoded = (string) config('security.encryption_key');

        if ($encoded === '') {
            throw new RuntimeException(
                'APP_ENCRYPTION_KEY is not configured. Run "php artisan security:generate-secrets" and copy the value into your .env file.'
            );
        }

        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES) {
            throw new RuntimeException('APP_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }

        return $this->bytes = $key;
    }

    public function isConfigured(): bool
    {
        try {
            $this->bytes();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
