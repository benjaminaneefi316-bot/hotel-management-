<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 80 reservations with payments and stays.
 *
 * HOW DOUBLE-BOOKING IS AVOIDED
 * -----------------------------
 * Bookings are created through the real BookingService::create(), the same
 * code path the UI uses. For each room the seeder walks the calendar
 * FORWARD, placing non-overlapping stays (and advancing a per-room cursor past
 * each one), so overlaps are impossible by construction. If the service still
 * rejects a slot (because of rounding or a gap), the BusinessRuleException is
 * caught and the cursor simply moves on - so the seeder can never create a
 * double booking.
 *
 * History is generated with realistic dates: completed stays in the past,
 * one in-house stay per occupied room today, and confirmed/pending arrivals
 * in the future.
 */
class BookingSeeder extends Seeder
{
    public const TARGET = 80;

    /** @var string[] */
    private const SPECIAL_REQUESTS = [
        'High floor with a sea view if possible.',
        'Late arrival, around 22:00.',
        'Twin beds please.',
        'Quiet room away from the lift.',
        'Celebrating an anniversary.',
        'Travel cot needed for a toddler.',
    ];

    public function run(): void
    {
        $rooms = Room::query()
            ->where('is_active', true)
            ->whereNotIn('status', [RoomStatus::Maintenance->value, RoomStatus::OutOfService->value])
            ->orderBy('id')
            ->get();

        $guests = Guest::query()->orderBy('id')->get();

        $staff = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'receptionist']))
            ->orderBy('id')
            ->get();

        if ($rooms->isEmpty() || $guests->isEmpty() || $staff->isEmpty()) {
            $this->command->warn('BookingSeeder skipped: run RoleSeeder, UserSeeder, RoomSeeder and GuestSeeder first.');

            return;
        }

        /** @var BookingService $service */
        $service = app(BookingService::class);

        $today = CarbonImmutable::now()->startOfDay();
        $actor = $staff->first();

        $created = 0;
        $skipped = 0;
        $guestCursor = 0;

        DB::transaction(function () use ($rooms, $guests, $staff, $service, $today, $actor, &$created, &$skipped, &$guestCursor): void {
            foreach ($rooms as $room) {
                $cursor = $today->subDays($this->between(100, 130));

                while ($cursor->lessThan($today->addDays(45)) && $created < self::TARGET) {
                    $nights = $this->between(1, 6);
                    $checkIn = $cursor;
                    $checkOut = $cursor->addDays($nights);

                    $guest = $guests[$guestCursor % $guests->count()];
                    $guestCursor++;

                    $status = $this->statusFor($checkIn, $checkOut, $today);

                    try {
                        /** @var Booking $booking */
                        $booking = $service->create([
                            'guest_id' => $guest->getKey(),
                            'check_in_date' => $checkIn->toDateString(),
                            'check_out_date' => $checkOut->toDateString(),
                            'room_ids' => [$room->getKey()],
                            'adults' => $this->between(1, 2),
                            'children' => $this->between(0, 2),
                            'source' => $this->pick(['website', 'phone', 'walk_in', 'ota']),
                            'status' => BookingStatus::Confirmed->value,
                            'special_requests' => $this->chance(25) ? $this->pick(self::SPECIAL_REQUESTS) : null,
                        ], $staff->random());
                    } catch (BusinessRuleException) {
                        // Slot collided: move on rather than double-book.
                        $skipped++;
                        $cursor = $cursor->addDays($nights + 1);

                        continue;
                    }

                    $this->applyOutcome($booking, $room, $status, $nights, $today);
                    $created++;

                    $cursor = $checkOut->addDays($this->between(0, 6));
                }

                if ($created >= self::TARGET) {
                    break;
                }
            }
        });

        $this->refreshRoomStatuses();

        $this->command->info("Bookings seeded: {$created}".($skipped > 0 ? " ({$skipped} slot collision(s) skipped)" : ''));
        $this->command->info('Stays: '.CheckIn::query()->count().' | Payments: '.Payment::query()->count());
    }

    private function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $values
     * @return T
     */
    private function pick(array $values): mixed
    {
        return $values[array_rand($values)];
    }

    private function chance(int $percent): bool
    {
        return $this->between(1, 100) <= $percent;
    }

    /** Decide what happened to a stay based on where it sits relative to today. */
    private function statusFor(CarbonImmutable $checkIn, CarbonImmutable $checkOut, CarbonImmutable $today): BookingStatus
    {
        if ($checkOut->lessThanOrEqualTo($today)) {
            $roll = $this->between(1, 100);

            return match (true) {
                $roll <= 82 => BookingStatus::CheckedOut,
                $roll <= 93 => BookingStatus::Cancelled,
                default => BookingStatus::NoShow,
            };
        }

        if ($checkIn->lessThanOrEqualTo($today) && $checkOut->greaterThan($today)) {
            return BookingStatus::CheckedIn;
        }

        return $this->between(1, 100) <= 85
            ? BookingStatus::Confirmed
            : BookingStatus::Pending;
    }

    /**
     * Apply the status, create the matching stay record and the payment(s).
     */
    private function applyOutcome(Booking $booking, Room $room, BookingStatus $status, int $nights, CarbonImmutable $today): void
    {
        $total = (float) $booking->total_amount;

        switch ($status) {
            case BookingStatus::CheckedOut:
                $booking->forceFill([
                    'status' => BookingStatus::CheckedOut,
                    'confirmed_at' => $booking->check_in_date->subDays(7),
                ])->save();

                CheckIn::query()->create([
                    'booking_id' => $booking->getKey(),
                    'room_id' => $room->getKey(),
                    'guest_id' => $booking->guest_id,
                    'status' => StayStatus::Completed,
                    'checked_in_at' => $booking->check_in_date->setTime(15, $this->between(0, 59)),
                    'checked_in_by' => $booking->user_id,
                    'checked_out_at' => $booking->check_out_date->setTime(11, $this->between(0, 59)),
                    'checked_out_by' => $booking->user_id,
                    'actual_nights' => $nights,
                ]);

                $this->pay($booking, $total, PaymentStatus::Paid);

                break;

            case BookingStatus::CheckedIn:
                $booking->forceFill([
                    'status' => BookingStatus::CheckedIn,
                    'confirmed_at' => $booking->check_in_date->subDays(10),
                ])->save();

                CheckIn::query()->create([
                    'booking_id' => $booking->getKey(),
                    'room_id' => $room->getKey(),
                    'guest_id' => $booking->guest_id,
                    'status' => StayStatus::Active,
                    'checked_in_at' => $booking->check_in_date->setTime(15, 30),
                    'checked_in_by' => $booking->user_id,
                    'actual_nights' => null,
                ]);

                // Deposit collected at check-in.
                $this->pay($booking, round($total * 0.5, 2), PaymentStatus::Paid);

                break;

            case BookingStatus::Cancelled:
                $booking->forceFill([
                    'status' => BookingStatus::Cancelled,
                    'cancelled_at' => $booking->check_in_date->subDays($this->between(1, 14)),
                    'internal_notes' => 'Cancelled by guest.',
                ])->save();

                // Deposit that was refunded.
                $this->pay($booking, round($total * 0.3, 2), PaymentStatus::Refunded);

                break;

            case BookingStatus::NoShow:
                $booking->forceFill([
                    'status' => BookingStatus::NoShow,
                    'internal_notes' => 'Guest did not arrive; deposit forfeited.',
                ])->save();

                $this->pay($booking, round($total * 0.3, 2), PaymentStatus::Paid);

                break;

            default:
                // Future arrival: Confirmed (usually with a deposit) or Pending.
                if ($status === BookingStatus::Confirmed) {
                    $booking->forceFill(['confirmed_at' => now()->subDays($this->between(1, 20))])->save();

                    if ($this->chance(70)) {
                        $this->pay($booking, round($total * 0.3, 2), PaymentStatus::Paid);
                    }
                }

                break;
        }
    }

    private function pay(Booking $booking, float $amount, PaymentStatus $status): void
    {
        if ($amount <= 0) {
            return;
        }

        Payment::query()->create([
            'booking_id' => $booking->getKey(),
            'user_id' => $booking->user_id,
            'amount' => $amount,
            'currency' => 'USD',
            'method' => $this->pick(PaymentMethod::cases()),
            'status' => $status,
            'provider' => 'stripe',
            'provider_reference' => 'tok_'.Str::random(18),
            'paid_at' => $status === PaymentStatus::Paid
                ? $booking->check_in_date->subDays($this->between(0, 5))
                : null,
            'refunded_at' => $status === PaymentStatus::Refunded ? now()->subDays($this->between(1, 20)) : null,
            'notes' => $status === PaymentStatus::Refunded ? 'Refunded after cancellation.' : null,
        ]);
    }

    /**
     * Make the physical room state match the stays we just created:
     * a room with an active stay is OCCUPIED, and a couple of others are shown
     * as CLEANING to make the housekeeping board realistic.
     */
    private function refreshRoomStatuses(): void
    {
        $occupied = CheckIn::query()
            ->where('status', StayStatus::Active->value)
            ->pluck('room_id')
            ->unique()
            ->all();

        Room::query()->whereIn('id', $occupied)->update(['status' => RoomStatus::Occupied->value]);

        // A few free rooms awaiting housekeeping.
        Room::query()
            ->whereNotIn('id', $occupied)
            ->where('status', RoomStatus::Available->value)
            ->inRandomOrder()
            ->limit(4)
            ->update(['status' => RoomStatus::Cleaning->value]);
    }
}
