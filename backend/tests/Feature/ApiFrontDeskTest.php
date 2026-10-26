<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * The mobile app's front-desk board: today's arrivals and departures, plus the
 * confirm action a pending arrival needs before it can be checked in.
 */
class ApiFrontDeskTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_staff_see_todays_arrivals_and_departures(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-device', ['*'])->plainTextToken;

        $arrival = Booking::factory()->stay(0, 2)->create(['status' => BookingStatus::Confirmed]);
        $departure = Booking::factory()->stay(-2, 2)->create(['status' => BookingStatus::CheckedIn]);

        CheckIn::factory()->create([
            'booking_id' => $departure->getKey(),
            'guest_id' => $departure->guest_id,
            'room_id' => Room::factory()->create()->getKey(),
            'checked_in_by' => $admin->getKey(),
        ]);

        // Not today: must stay off both boards.
        Booking::factory()->stay(5, 2)->create(['status' => BookingStatus::Confirmed]);

        $response = $this->withToken($token)->getJson('/api/front-desk')->assertOk();

        $response->assertJsonPath('arrivals.0.reference', $arrival->reference)
            ->assertJsonPath('arrivals.0.can_check_in', true)
            ->assertJsonPath('arrivals.0.can_confirm', false)
            ->assertJsonPath('departures.0.reference', $departure->reference);

        $stayId = $response->json('departures.0.stay_id');
        $this->assertNotNull($stayId);
    }

    public function test_a_pending_arrival_is_flagged_for_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-device', ['*'])->plainTextToken;

        $pending = Booking::factory()->stay(0, 1)->create(['status' => BookingStatus::Pending]);

        $this->withToken($token)
            ->getJson('/api/front-desk')
            ->assertOk()
            ->assertJsonPath('arrivals.0.reference', $pending->reference)
            ->assertJsonPath('arrivals.0.can_confirm', true)
            ->assertJsonPath('arrivals.0.can_check_in', false);
    }

    public function test_a_guest_cannot_read_the_front_desk(): void
    {
        $guest = User::factory()->guest()->create();
        $token = $guest->createToken('test-device', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/front-desk')->assertForbidden();
    }

    public function test_staff_can_confirm_a_pending_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-device', ['*'])->plainTextToken;

        $booking = Booking::factory()->create(['status' => BookingStatus::Pending]);

        $this->withToken($token)
            ->postJson("/api/bookings/{$booking->getKey()}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Confirmed->value);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()?->status);
    }

    public function test_a_guest_cannot_confirm_a_booking(): void
    {
        $guest = User::factory()->guest()->create();
        $token = $guest->createToken('test-device', ['*'])->plainTextToken;
        $booking = Booking::factory()->create(['status' => BookingStatus::Pending]);

        $this->withToken($token)
            ->postJson("/api/bookings/{$booking->getKey()}/confirm")
            ->assertForbidden();
    }

    public function test_an_already_confirmed_booking_cannot_be_confirmed_again(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-device', ['*'])->plainTextToken;
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

        $this->withToken($token)
            ->postJson("/api/bookings/{$booking->getKey()}/confirm")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'confirm_failed');
    }
}
