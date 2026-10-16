<?php

declare(strict_types=1);

namespace App\Support\Encryption;

use RuntimeException;

/**
 * Authenticated, whole-file encryption using AES-256-GCM.
 *
 * Used by the backup service to protect a database dump before it leaves the
 * server. Envelope (raw binary, no base64 so the archive stays compact):
 *
 *     nonce[12] . tag[16] . ciphertext
 *
 * A fresh random nonce is generated per call. Because GCM authenticates the
 * data, a corrupted or tampered backup fails to decrypt instead of producing
 * silent garbage.
 *
 * NOTE: the file is processed in memory. That is fine for a classroom-sized
 * database; for very large production dumps use a streaming AEAD construction
 * or the managed snapshot feature of your database provider.
 */
final class FileEncryptor
{
    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    public function __construct(private readonly EncryptionKey $key) {}

    /** Encrypt $source into $destination. Returns the number of bytes written. */
    public function encryptFile(string $source, string $destination): int
    {
        $plaintext = file_get_contents($source);

        if ($plaintext === false) {
            throw new RuntimeException("Unable to read backup source file: {$source}");
        }

        $nonce = random_bytes(self::NONCE_BYTES);

        if ($this->sodiumAvailable()) {
            $sealed = sodium_crypto_aead_aes256gcm_encrypt($plaintext, '', $nonce, $this->key->bytes());
            $payload = $nonce.$sealed; // sodium already appends the tag
        } else {
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plaintext,
                'aes-256-gcm',
                $this->key->bytes(),
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                self::TAG_BYTES,
            );

            if ($ciphertext === false) {
                throw new RuntimeException('AES-256-GCM file encryption failed.');
            }

            $payload = $nonce.$tag.$ciphertext;
        }

        $written = file_put_contents($destination, $payload, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException("Unable to write encrypted backup file: {$destination}");
        }

        return $written;
    }

    /** Decrypt $source into $destination. Returns the number of bytes written. */
    public function decryptFile(string $source, string $destination): int
    {
        $payload = file_get_contents($source);

        if ($payload === false) {
            throw new RuntimeException("Unable to read encrypted backup file: {$source}");
        }

        if (strlen($payload) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Encrypted backup file is truncated.');
        }

        $nonce = substr($payload, 0, self::NONCE_BYTES);
        $rest = substr($payload, self::NONCE_BYTES);

        if ($this->sodiumAvailable()) {
            $plaintext = sodium_crypto_aead_aes256gcm_decrypt($rest, '', $nonce, $this->key->bytes());
        } else {
            $tag = substr($rest, 0, self::TAG_BYTES);
            $ciphertext = substr($rest, self::TAG_BYTES);
            $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key->bytes(), OPENSSL_RAW_DATA, $nonce, $tag, '');
        }

        if ($plaintext === false) {
            throw new RuntimeException('Backup decryption failed: the archive is corrupt, tampered with, or encrypted with a different key.');
        }

        $written = file_put_contents($destination, $plaintext, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException("Unable to write decrypted backup file: {$destination}");
        }

        return $written;
    }

    private function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_aead_aes256gcm_is_available')
            && sodium_crypto_aead_aes256gcm_is_available();
    }
}
