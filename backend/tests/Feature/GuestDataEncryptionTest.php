<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sensitive guest fields (phone, national id, address) must be stored as
 * AES-256-GCM ciphertext, never plaintext, and must be bound to their column.
 */
class GuestDataEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_fields_are_stored_as_ciphertext(): void
    {
        $guest = Guest::factory()->create([
            'phone' => '+1 555 010 0199',
            'national_id' => 'ZZ9988776',
            'address' => '12 Secret Harbour Lane',
        ]);

        $row = DB::table('guests')->where('id', $guest->getKey())->first();

        $this->assertStringStartsWith('gcm1:', (string) $row->phone);
        $this->assertStringStartsWith('gcm1:', (string) $row->national_id);
        $this->assertStringStartsWith('gcm1:', (string) $row->address);

        $this->assertStringNotContainsString('555', (string) $row->phone);
        $this->assertStringNotContainsString('ZZ9988776', (string) $row->national_id);
        $this->assertStringNotContainsString('Secret Harbour', (string) $row->address);
    }

    public function test_the_model_transparently_decrypts_the_values(): void
    {
        $guest = Guest::factory()->create([
            'phone' => '+1 555 010 0199',
            'national_id' => 'ZZ9988776',
            'address' => '12 Secret Harbour Lane',
        ]);

        $fresh = $guest->fresh();

        $this->assertSame('+1 555 010 0199', $fresh->phone);
        $this->assertSame('ZZ9988776', $fresh->national_id);
        $this->assertSame('12 Secret Harbour Lane', $fresh->address);
    }

    public function test_identical_plaintext_produces_different_ciphertext(): void
    {
        $first = Guest::factory()->create(['phone' => '+1 555 010 0199']);
        $second = Guest::factory()->create(['phone' => '+1 555 010 0199']);

        $rawFirst = DB::table('guests')->where('id', $first->getKey())->value('phone');
        $rawSecond = DB::table('guests')->where('id', $second->getKey())->value('phone');

        // A fresh nonce per encryption means the ciphertext must differ.
        $this->assertNotSame($rawFirst, $rawSecond);
    }

    public function test_tampered_ciphertext_is_refused_rather_than_returned(): void
    {
        $guest = Guest::factory()->create(['phone' => '+1 555 010 0199']);
        $raw = (string) DB::table('guests')->where('id', $guest->getKey())->value('phone');

        $tampered = substr($raw, 0, -1).(substr($raw, -1) === 'A' ? 'B' : 'A');

        DB::table('guests')->where('id', $guest->getKey())->update(['phone' => $tampered]);

        $this->assertNull($guest->fresh()->phone);
    }
}
