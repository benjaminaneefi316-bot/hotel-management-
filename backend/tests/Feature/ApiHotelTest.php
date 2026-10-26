<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * The public website content, served to the app so guests see the same hotel
 * story without an account.
 */
class ApiHotelTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    public function test_the_hotel_page_is_public_and_lists_room_types(): void
    {
        $this->seedSettings(['hotel_name' => 'Test Bay Hotel']);

        $type = RoomType::factory()->create([
            'name' => 'Deluxe Sea View',
            'code' => 'DLX',
            'slug' => 'deluxe-sea-view',
            'base_price' => 320,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/hotel')->assertOk();

        $response->assertJsonPath('hotel.name', 'Test Bay Hotel')
            ->assertJsonPath('hotel.tagline', 'Where the ocean sets the pace');

        $names = collect($response->json('room_types'))->pluck('name')->all();
        $this->assertContains($type->name, $names);
    }

    public function test_a_contact_enquiry_is_accepted_without_storing_the_body(): void
    {
        Log::spy();

        $response = $this->postJson('/api/hotel/contact', [
            'name' => 'Ada Guest',
            'email' => 'ada@example.test',
            'subject' => 'Late arrival',
            'message' => 'We will arrive around 11pm - is that alright?',
        ])->assertCreated();

        $response->assertJsonPath('status', 'received');
        $this->assertStringContainsString('Ada Guest', (string) $response->json('message'));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'contact enquiry')
                && $context['email'] === 'ada@example.test'
                && ! array_key_exists('message', $context))
            ->once();
    }

    public function test_a_contact_enquiry_needs_a_message(): void
    {
        $this->postJson('/api/hotel/contact', [
            'name' => 'Ada Guest',
            'email' => 'ada@example.test',
        ])->assertStatus(422)->assertJsonValidationErrors(['message']);
    }
}
