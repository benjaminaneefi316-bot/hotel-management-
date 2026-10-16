<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Encryption\FieldEncryptor;
use Tests\TestCase;

/**
 * The AES-256-GCM field encryptor: round trip, AAD binding and tamper refusal.
 */
class FieldEncryptorTest extends TestCase
{
    public function test_encrypt_decrypt_round_trip(): void
    {
        $encryptor = app(FieldEncryptor::class);

        $cipher = $encryptor->encrypt('super-secret-value', 'phone');

        $this->assertNotSame('super-secret-value', $cipher);
        $this->assertStringStartsWith('gcm1:', (string) $cipher);
        $this->assertSame('super-secret-value', $encryptor->decrypt($cipher, 'phone'));
    }

    public function test_ciphertext_is_bound_to_its_column(): void
    {
        $encryptor = app(FieldEncryptor::class);

        $cipher = $encryptor->encrypt('bound-value', 'phone');

        // Wrong AAD (column) must fail authentication.
        $this->assertNull($encryptor->decrypt($cipher, 'address'));
    }

    public function test_tampering_returns_null(): void
    {
        $encryptor = app(FieldEncryptor::class);

        $cipher = (string) $encryptor->encrypt('value', 'phone');

        $this->assertNull($encryptor->decrypt(substr($cipher, 0, -2).'ZZ', 'phone'));
        $this->assertNull($encryptor->decrypt('not-our-envelope', 'phone'));
    }

    public function test_null_and_empty_pass_through(): void
    {
        $encryptor = app(FieldEncryptor::class);

        $this->assertNull($encryptor->encrypt(null));
        $this->assertNull($encryptor->decrypt(null));
        $this->assertFalse($encryptor->isEncrypted('plain text'));
        $this->assertTrue($encryptor->isEncrypted('gcm1:anything'));
    }
}
