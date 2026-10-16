<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reservation logic.
 *
 * The important part is DOUBLE-BOOKING PREVENTION. Two bookings for the same
 * room overlap when
 *
 *     existing.check_in_date < requested.check_out_date
 *     AND existing.check_out_date > requested.check_in_date
 *
 * The check runs inside a transaction that first locks the candidate room rows
 * with SELECT ... FOR UPDATE, so two simultaneous requests cannot both pass the
 * check and then both insert. This works on both MySQL and SQLite.
 */
final class BookingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Statuses that still hold a room's calendar. */
    private const BLOCKING_STATUSES = ['pending', 'confirmed', 'checked_in', 'checked_out'];

    /**
     * Create a booking.
     *
     * @param  array{
     *     guest_id: int,
     *     check_in_date: string,
     *     check_out_date: string,
     *     room_ids: array<int, int>,
     *     adults?: int,
     *     children?: int,
     *     special_requests?: string|null,
     *     source?: string|null,
     *     status?: string|null
     * }  $data
     *
     * @throws BusinessRuleException
     */
    public function create(array $data, User $actor): Booking
    {
        [$checkIn, $checkOut, $nights] = $this->normaliseDates($data['check_in_date'], $data['check_out_date']);

        $roomIds = array_values(array_unique(array_map('intval', $data['room_ids'] ?? [])));

        if ($roomIds === []) {
            throw new BusinessRuleException('Select at least one room for the reservation.');
        }

        return DB::transaction(function () use ($data, $actor, $roomIds, $checkIn, $checkOut, $nights): Booking {
            /** @var Collection<int, Room> $rooms */
            $rooms = Room::query()
                ->whereIn('id', $roomIds)
                ->lockForUpdate()
                ->get();

            if ($rooms->count() !== count($roomIds)) {
                throw new BusinessRuleException('One or more selected rooms no longer exist.');
            }

            foreach ($rooms as $room) {
                if (! $room->is_active) {
                    throw new BusinessRuleException("Room {$room->room_number} is inactive and cannot be booked.");
                }

                if ($room->status === RoomStatus::OutOfService || $room->status === RoomStatus::Maintenance) {
                    throw new BusinessRuleException("Room {$room->room_number} is currently {$room->status->label()} and cannot be booked.");
                }

                if ($this->hasOverlap($room->id, $checkIn, $checkOut)) {
                    throw new BusinessRuleException("Room {$room->room_number} is already reserved for those dates.");
                }
            }

            $status = $data['status'] ?? BookingStatus::Confirmed->value;

            $booking = Booking::query()->create([
                'guest_id' => $data['guest_id'],
                'user_id' => $actor->getKey(),
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'status' => $status,
                'source' => $data['source'] ?? 'admin',
                'special_requests' => $data['special_requests'] ?? null,
                'confirmed_at' => $status === BookingStatus::Confirmed->value ? now() : null,
                'total_amount' => 0,
            ]);

            $total = 0.0;

            foreach ($rooms as $room) {
                $rate = $room->effectiveRate();
                $booking->rooms()->attach($room->id, ['rate' => $rate]);
                $total += $rate * $nights;
            }

            $booking->forceFill(['total_amount' => $total])->save();

            $this->audit->success(
                action: 'booking.create',
                eventType: 'data',
                description: sprintf('Created booking %s for %d night(s) in room(s) %s.', $booking->reference, $nights, $rooms->pluck('room_number')->implode(', ')),
                subject: $booking,
                newValues: [
                    'reference' => $booking->reference,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'rooms' => $rooms->pluck('room_number')->all(),
                    'total_amount' => $total,
                ],
            );

            return $booking->fresh(['rooms', 'guest']);
        });
    }

    /**
     * Reschedule a booking. Keeps the same rooms unless new ones are supplied.
     *
     * @param  array<int, int>|null  $roomIds
     *
     * @throws BusinessRuleException
     */
    public function update(Booking $booking, array $data, ?array $roomIds, User $actor): Booking
    {
        if (! $booking->isCancellable() && $booking->status !== BookingStatus::CheckedIn) {
            throw new BusinessRuleException('Only pending, confirmed or in-house bookings can be modified.');
        }

        [$checkIn, $checkOut, $nights] = $this->normaliseDates(
            $data['check_in_date'] ?? $booking->check_in_date->toDateString(),
            $data['check_out_date'] ?? $booking->check_out_date->toDateString(),
        );

        $before = [
            'check_in_date' => $booking->check_in_date->toDateString(),
            'check_out_date' => $booking->check_out_date->toDateString(),
            'rooms' => $booking->rooms->pluck('room_number')->all(),
        ];

        return DB::transaction(function () use ($booking, $data, $roomIds, $actor, $checkIn, $checkOut, $nights, $before): Booking {
            $targetRoomIds = $roomIds !== null && $roomIds !== []
                ? array_values(array_unique(array_map('intval', $roomIds)))
                : $booking->rooms->pluck('id')->all();

            /** @var Collection<int, Room> $rooms */
            $rooms = Room::query()->whereIn('id', $targetRoomIds)->lockForUpdate()->get();

            foreach ($rooms as $room) {
                if ($this->hasOverlap($room->id, $checkIn, $checkOut, ignoreBookingId: $booking->getKey())) {
                    throw new BusinessRuleException("Room {$room->room_number} is already reserved for the new dates.");
                }
            }

            $booking->fill([
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => $data['adults'] ?? $booking->adults,
                'children' => $data['children'] ?? $booking->children,
                'special_requests' => $data['special_requests'] ?? $booking->special_requests,
                'internal_notes' => $data['internal_notes'] ?? $booking->internal_notes,
            ]);

            if ($rooms->isNotEmpty()) {
                $total = 0.0;
                $sync = [];
                foreach ($rooms as $room) {
                    $sync[$room->id] = ['rate' => $room->effectiveRate()];
                    $total += $room->effectiveRate() * $nights;
                }
                $booking->rooms()->sync($sync);
                $booking->total_amount = $total;
            }

            $booking->save();

            $this->audit->success(
                action: 'booking.update',
                eventType: 'data',
                description: sprintf('Updated booking %s.', $booking->reference),
                subject: $booking,
                oldValues: $before,
                newValues: [
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'rooms' => $rooms->pluck('room_number')->all(),
                ],
            );

            return $booking->fresh(['rooms', 'guest']);
        });
    }

    /**
     * Cancel a booking (soft - the row is kept for reporting).
     *
     * @throws BusinessRuleException
     */
    public function cancel(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        if (! $booking->isCancellable()) {
            throw new BusinessRuleException('Only pending or confirmed bookings can be cancelled.');
        }

        $booking->forceFill([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'internal_notes' => trim(($booking->internal_notes ?? '')."\nCancelled: ".($reason ?: 'no reason given')),
        ])->save();

        $this->audit->success(
            action: 'booking.cancel',
            eventType: 'data',
            description: sprintf('Cancelled booking %s (%s).', $booking->reference, $reason ?: 'no reason given'),
            subject: $booking,
            oldValues: ['status' => BookingStatus::Confirmed->value],
            newValues: ['status' => BookingStatus::Cancelled->value],
        );

        return $booking->fresh();
    }

    /**
     * Confirm a pending booking.
     *
     * @throws BusinessRuleException
     */
    public function confirm(Booking $booking, User $actor): Booking
    {
        if ($booking->status !== BookingStatus::Pending) {
            throw new BusinessRuleException('Only pending bookings can be confirmed.');
        }

        $booking->forceFill([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ])->save();

        $this->audit->success(
            action: 'booking.confirm',
            eventType: 'data',
            description: sprintf('Confirmed booking %s.', $booking->reference),
            subject: $booking,
            oldValues: ['status' => BookingStatus::Pending->value],
            newValues: ['status' => BookingStatus::Confirmed->value],
        );

        return $booking->fresh();
    }

    /**
     * Does the room already have a booking that overlaps [$checkIn, $checkOut)?
     */
    public function hasOverlap(int $roomId, CarbonImmutable|string $checkIn, CarbonImmutable|string $checkOut, ?int $ignoreBookingId = null): bool
    {
        $in = $checkIn instanceof CarbonImmutable ? $checkIn->toDateString() : $checkIn;
        $out = $checkOut instanceof CarbonImmutable ? $checkOut->toDateString() : $checkOut;

        return BookingRoom::query()
            ->where('room_id', $roomId)
            ->when($ignoreBookingId !== null, fn ($q) => $q->where('booking_id', '!=', $ignoreBookingId))
            ->whereHas('booking', function ($query) use ($in, $out): void {
                $query->whereIn('status', self::BLOCKING_STATUSES)
                    ->whereDate('check_in_date', '<', $out)
                    ->whereDate('check_out_date', '>', $in);
            })
            ->exists();
    }

    /**
     * Rooms that are free for the whole range.
     *
     * @return Collection<int, Room>
     */
    public function availableRooms(string $checkIn, string $checkOut, ?int $roomTypeId = null): Collection
    {
        [$in, $out] = $this->normaliseDates($checkIn, $checkOut);

        return Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->whereIn('status', [RoomStatus::Available->value, RoomStatus::Cleaning->value])
            ->when($roomTypeId !== null, fn ($q) => $q->where('room_type_id', $roomTypeId))
            ->whereNotIn('id', function ($query) use ($in, $out): void {
                $query->select('booking_rooms.room_id')
                    ->from('booking_rooms')
                    ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
                    ->whereIn('bookings.status', self::BLOCKING_STATUSES)
                    // $in / $out are already 'Y-m-d' strings (see normaliseDates).
                    ->whereDate('bookings.check_in_date', '<', $out)
                    ->whereDate('bookings.check_out_date', '>', $in);
            })
            ->orderBy('room_number')
            ->get();
    }

    public function priceFor(array $roomIds, string $checkIn, string $checkOut): float
    {
        [, , $nights] = $this->normaliseDates($checkIn, $checkOut);

        return round((float) Room::query()
            ->whereIn('id', $roomIds)
            ->get()
            ->sum(fn (Room $room) => $room->effectiveRate() * $nights), 2);
    }

    public function outstandingBalance(Booking $booking): float
    {
        $paid = (float) $booking->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        return round((float) $booking->total_amount - $paid, 2);
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     *
     * @throws BusinessRuleException
     */
    private function normaliseDates(string $checkIn, string $checkOut): array
    {
        try {
            $in = CarbonImmutable::parse($checkIn)->startOfDay();
            $out = CarbonImmutable::parse($checkOut)->startOfDay();
        } catch (\Throwable) {
            throw new BusinessRuleException('Invalid check-in or check-out date.');
        }

        if ($out->lessThanOrEqualTo($in)) {
            throw new BusinessRuleException('The check-out date must be after the check-in date.');
        }

        if ($in->diffInDays($out) > 365) {
            throw new BusinessRuleException('A single reservation cannot exceed 365 nights.');
        }

        return [$in->toDateString(), $out->toDateString(), (int) $in->diffInDays($out)];
    }
}
