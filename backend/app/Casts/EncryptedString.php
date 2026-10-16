<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Encryption\FieldEncryptor;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Eloquent cast that transparently encrypts/decrypts a column with AES-256-GCM.
 *
 * Usage on a model:
 *
 *     protected function casts(): array
 *     {
 *         return ['phone' => EncryptedString::class];
 *     }
 *
 * The property is used as the "additional authenticated data", which binds a
 * ciphertext to its column so values cannot be swapped between columns.
 *
 * If decryption fails (wrong key or tampered data) the cast returns NULL and
 * writes a security warning to the log - it never returns corrupted plaintext
 * and never logs the value itself.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class EncryptedString implements CastsAttributes
{
    /**
     * Eloquent instantiates cast objects with `new` and no arguments, so the
     * encryptor cannot be constructor-injected. It is resolved lazily from the
     * container instead (and cached per cast instance, which Eloquent reuses).
     */
    private ?FieldEncryptor $encryptor = null;

    private function encryptor(): FieldEncryptor
    {
        return $this->encryptor ??= app(FieldEncryptor::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $plain = $this->encryptor()->decrypt((string) $value, $key);

        if ($plain === null) {
            Log::warning('Encrypted field could not be decrypted; returning null.', [
                'model' => $model::class,
                'field' => $key,
                'record_id' => $model->getKey(),
                // The ciphertext and any plaintext are deliberately NOT logged.
            ]);
        }

        return $plain;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => $this->encryptor()->encrypt((string) $value, $key)];
    }
}
