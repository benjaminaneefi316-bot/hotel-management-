<?php

declare(strict_types=1);

namespace App\Support\Encryption;

use RuntimeException;

/**
 * Authenticated field encryption using AES-256-GCM.
 *
 * WHY GCM: it is an Authenticated Encryption with Associated Data (AEAD) mode.
 * The 16-byte authentication tag means any tampering with the stored
 * ciphertext is detected and decryption fails safely, instead of silently
 * returning corrupted data.
 *
 * KEY: a base64-encoded 32-byte key resolved by EncryptionKey from
 * APP_ENCRYPTION_KEY, i.e. kept OUTSIDE the database (environment / AWS
 * Secrets Manager). A stolen database dump is therefore useless on its own.
 *
 * ENVELOPE (single string column):
 *
 *     "gcm1:" . base64( nonce[12] . tag[16] . ciphertext )
 *
 * A fresh cryptographically-random 96-bit nonce is generated for EVERY
 * encryption, which is what GCM requires.
 *
 * IMPLEMENTATION: uses libsodium's AES-256-GCM when the CPU supports it and
 * transparently falls back to OpenSSL's AES-256-GCM otherwise. Because the
 * envelope is identical, data encrypted by one can be decrypted by the other.
 *
 * AAD: an optional "additional authenticated data" string (the cast passes the
 * column name) binds a ciphertext to its field, so a value cannot be moved
 * from one column to another without detection.
 */
final class FieldEncryptor
{
    private const PREFIX = 'gcm1:';

    private const NONCE_BYTES = 12;   // 96-bit nonce, required by GCM

    private const TAG_BYTES = 16;     // 128-bit authentication tag

    public function __construct(private readonly EncryptionKey $key) {}

    public function encrypt(?string $plaintext, string $aad = ''): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }

        $nonce = random_bytes(self::NONCE_BYTES);

        if ($this->sodiumAvailable()) {
            // libsodium appends the 16-byte tag to the ciphertext.
            $sealed = sodium_crypto_aead_aes256gcm_encrypt($plaintext, $aad, $nonce, $this->key->bytes());
            $tag = substr($sealed, -self::TAG_BYTES);
            $ciphertext = substr($sealed, 0, -self::TAG_BYTES);
        } else {
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plaintext,
                'aes-256-gcm',
                $this->key->bytes(),
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                $aad,
                self::TAG_BYTES,
            );

            if ($ciphertext === false) {
                throw new RuntimeException('AES-256-GCM encryption failed.');
            }
        }

        return self::PREFIX.base64_encode($nonce.$tag.$ciphertext);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * Returns NULL (never a partial/garbage value) when the payload is
     * malformed, was tampered with, or the key is wrong.
     */
    public function decrypt(?string $payload, string $aad = ''): ?string
    {
        if ($payload === null || $payload === '') {
            return $payload;
        }

        if (! str_starts_with($payload, self::PREFIX)) {
            // Not our envelope: refuse rather than return raw data.
            return null;
        }

        $decoded = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($decoded === false || strlen($decoded) <= self::NONCE_BYTES + self::TAG_BYTES) {
            return null;
        }

        $nonce = substr($decoded, 0, self::NONCE_BYTES);
        $tag = substr($decoded, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($decoded, self::NONCE_BYTES + self::TAG_BYTES);

        if ($this->sodiumAvailable()) {
            $plaintext = sodium_crypto_aead_aes256gcm_decrypt($ciphertext.$tag, $aad, $nonce, $this->key->bytes());
        } else {
            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->key->bytes(),
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                $aad,
            );
        }

        return $plaintext === false ? null : $plaintext;
    }

    public function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** True when the environment is correctly configured. */
    public function isConfigured(): bool
    {
        return $this->key->isConfigured();
    }

    private function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_aead_aes256gcm_is_available')
            && sodium_crypto_aead_aes256gcm_is_available();
    }
}
