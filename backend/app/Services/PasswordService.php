<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Central password service - the ONLY place passwords are hashed or checked.
 *
 * The assignment requires explicit "salt and pepper" logic, so on top of
 * Laravel's own hashing we add:
 *
 *   1. a PER-USER SALT  - 32 random bytes, stored on the user row
 *                          (users.password_salt). Salts do not need to be
 *                          secret; they guarantee two users with the same
 *                          password get different hashes.
 *   2. an APPLICATION PEPPER - a secret kept in APP_PASSWORD_PEPPER (env)
 *                          and never written to the database.
 *
 * Before hashing we build a fixed-length, keyed pre-hash:
 *
 *      pre = sha256( pepper . "\0" . salt . "\0" . plaintext )
 *
 * and then hand `pre` to Laravel's Hash facade, which applies Argon2id (or
 * bcrypt) WITH ITS OWN PER-HASH RANDOM SALT and stores that salt inside the
 * encoded digest. So three independent layers protect the password:
 * application pepper, explicit per-user salt, and the library's internal salt.
 *
 * PASSWORD CHANGES / RESETS  -> hash() is called again, producing a NEW salt.
 * PEPPER ROTATION            -> set APP_PASSWORD_PEPPER to the new value and
 *                               move the old one into APP_PASSWORD_PEPPER_PREVIOUS.
 *                               check() still verifies old passwords and reports
 *                               `stale = true`, so the caller can rehash with the
 *                               current pepper on the next successful login.
 * COST / ALGORITHM CHANGE    -> Hash::needsRehash() reports when a stored hash
 *                               should be upgraded; callers rehash on login.
 *
 * Never write custom cryptography - this only composes vetted primitives
 * (random_bytes, sha256, and Laravel's password hasher).
 */
final class PasswordService
{
    private const MIN_PEPPER_LENGTH = 16;

    /** Generate a fresh, URL-safe per-user salt (32 random bytes). */
    public function newSalt(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Hash a plaintext password for a NEW or CHANGED password.
     *
     * @return array{hash: string, salt: string}
     */
    public function hash(string $plaintext): array
    {
        $salt = $this->newSalt();

        return [
            'hash' => Hash::make($this->preHash($this->currentPepper(), $salt, $plaintext)),
            'salt' => $salt,
        ];
    }

    /**
     * Verify a plaintext password against a stored hash + salt.
     *
     * @return array{ok: bool, stale: bool}  stale = verified with an old pepper
     */
    public function check(string $plaintext, string $hash, string $salt): array
    {
        // 1. Try the current pepper.
        if (Hash::check($this->preHash($this->currentPepper(), $salt, $plaintext), $hash)) {
            return ['ok' => true, 'stale' => false];
        }

        // 2. Fall back to any previous (rotated-out) peppers.
        foreach ($this->previousPeppers() as $pepper) {
            if (Hash::check($this->preHash($pepper, $salt, $plaintext), $hash)) {
                return ['ok' => true, 'stale' => true];
            }
        }

        return ['ok' => false, 'stale' => false];
    }

    /** Simple boolean helper. */
    public function verify(string $plaintext, string $hash, string $salt): bool
    {
        return $this->check($plaintext, $hash, $salt)['ok'];
    }

    /** Re-hash with the CURRENT pepper, keeping the same salt. */
    public function rehash(string $plaintext, string $salt): string
    {
        return Hash::make($this->preHash($this->currentPepper(), $salt, $plaintext));
    }

    /** True when the stored hash was produced with an outdated cost/algorithm. */
    public function needsRehash(string $hash): bool
    {
        return Hash::needsRehash($hash);
    }

    /**
     * The current application pepper.
     *
     * Fails LOUDLY when it is missing so we never silently store passwords
     * without the pepper (a common and dangerous misconfiguration).
     */
    public function currentPepper(): string
    {
        $pepper = (string) config('security.pepper');

        if (strlen($pepper) < self::MIN_PEPPER_LENGTH) {
            throw new RuntimeException(
                'APP_PASSWORD_PEPPER is missing or too short (min '.self::MIN_PEPPER_LENGTH.' characters). '
                .'Run "php artisan security:generate-secrets" and copy the value into your .env file.'
            );
        }

        return $pepper;
    }

    /** @return string[] */
    private function previousPeppers(): array
    {
        return (array) config('security.previous_peppers', []);
    }

    private function preHash(string $pepper, string $salt, string $plaintext): string
    {
        // Keyed, fixed-length pre-hash. Keeps input well under bcrypt's
        // 72-byte limit and binds the pepper and salt into the digest.
        return hash('sha256', $pepper."\0".$salt."\0".$plaintext);
    }
}
