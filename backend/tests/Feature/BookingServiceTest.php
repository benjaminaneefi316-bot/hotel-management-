<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Reservation logic, including the critical DOUBLE-BOOKING PREVENTION rule.
 */
class BookingServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    private BookingService $bookings;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->bookings = app(BookingService::class);
        $this->actor = User::factory()->receptionist()->create();
    }

    public function test_a_booking_is_created_attached_and_priced(): void
    {
        $room = $this->makeRoom(150.00);
        $guest = Guest::factory()->create();

        $booking = $this->bookings->create([
            'guest_id' => $guest->getKey(),
            'room_ids' => [$room->getKey()],
            'check_in_date' => '2026-06-01',
            'check_out_date' => '2026-06-04',
        ], $this->actor);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(3, $booking->nights());
        $this->assertEquals(450.00, (float) $booking->total_amount);

        $this->assertDatabaseHas('booking_rooms', [
            'booking_id' => $booking->getKey(),
            'room_id' => $room->getKey(),
        ]);
    }

    public function test_double_booking_an_overlapping_period_is_refused(): void
    {
        $room = $this->makeRoom(150.00);
        $guest = Guest::factory()->create();

        $this->bookings->create([
            'guest_id' => $guest->getKey(),
            'room_ids' => [$room->getKey()],
            'check_in_date' => '2026-06-01',
            'check_out_date' => '2026-06-04',
        ], $this->actor);

        $this->expectException(BusinessRuleException::class);

        $this->bookings->create([
            'guest_id' => Guest::factory()->create()->getKey(),
            'room_ids' => [$room->getKey()],
            'check_in_date' => '2026-06-03',
            'check_out_date' => '2026-06-06',
        ], $this->actor);
    }

    public function test_adjacent_stays_do_not_count_as_an_overlap(): void
    {
        $room = $this->makeRoom(150.00);
        $guest = Guest::factory()->create();

        $this->bookings->create([
            'guest_id' => $guest->getKey(),
            'room_ids' => [$room->getKey()],
            'check_in_date' => '2026-06-01',
            'check_out_date' => '2026-06-04',
        ], $this->actor);

        $second = $this->bookings->create([
            'guest_id' => Guest::factory()->create()->getKey(),
            'room_ids' => [$room->getKey()],
            'check_in_date' => '2026-06-04',
            'check_out_date' => '2026-06-06',
        ], $this->actor);

        $this->assertSame(BookingStatus::Confirmed, $second->status);
    }

    public function test_has_overlap_matches_the_half_open_interval_rule(): void
    {
        $room = $this->makeRoom(150.00);

        $this->bookings->create([
            'guest_id' => Guest::factory()->create()->getKey(),
            'room_ids' => [$room->getKey()],
            'check_in_date' => '2026-06-01',
            'check_out_date' => '2026-06-04',
        ], $this->actor);

        $this->assertTrue($this->bookings->hasOverlap($room->getKey(), '2026-06-02', '2026-06-03'));
        $this->assertFalse($this->bookings->hasOverlap($room->getKey(), '2026-05-30', '2026-06-01'));
        $this->assertFalse($this->bookings->hasOverlap($room->getKey(), '2026-06-04', '2026-06-06'));
    }

    public function test_available_rooms_excludes_reserved_rooms(): void
    {
        $type = RoomType::factory()->create();
        $free = Room::factory()->forType($type)->create();
        $busy = Room::factory()->forType($type)->create();

        $this->bookings->create([
            'guest_id' => Guest::factory()->create()->getKey(),
            'room_ids' => [$busy->getKey()],
            'check_in_date' => '2026-06-01',
            'check_out_date' => '2026-06-04',
        ], $this->actor);

        $available = $this->bookings->availableRooms('2026-06-02', '2026-06-03');

        $this->assertTrue($available->contains('id', $free->getKey()));
        $this->assertFalse($available->contains('id', $busy->getKey()));
    }

    private function makeRoom(float $rate): Room
    {
        $type = RoomType::factory()->create(['base_price' => $rate]);

        return Room::factory()->forType($type)->create(['nightly_rate' => $rate]);
    }
}
