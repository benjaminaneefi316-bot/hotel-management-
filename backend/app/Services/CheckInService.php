<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\StayStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Check-in / check-out (stay) logic.
 *
 * Rules enforced server-side:
 *   - only a CONFIRMED booking can be checked in
 *   - a room can have at most ONE active stay at a time
 *   - a stay can only be checked out while it is active (never twice)
 *   - booking status and room status are kept consistent
 */
final class CheckInService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Check a guest in.
     *
     * @throws BusinessRuleException
     */
    public function checkIn(Booking $booking, User $actor, ?int $roomId = null): CheckIn
    {
        if (! $booking->isCheckInnable()) {
            throw new BusinessRuleException(
                sprintf('Booking %s has status "%s" and cannot be checked in.', $booking->reference, $booking->status->label())
            );
        }

        $booking->loadMissing('rooms', 'guest');

        $room = $roomId !== null
            ? $booking->rooms->firstWhere('id', $roomId)
            : $booking->rooms->first();

        if (! $room) {
            throw new BusinessRuleException('This booking has no room assigned to check in.');
        }

        return DB::transaction(function () use ($booking, $room, $actor): CheckIn {
            // Lock the room so two receptionists cannot check in simultaneously.
            /** @var Room $locked */
            $locked = Room::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            $alreadyActive = CheckIn::query()
                ->where('room_id', $locked->getKey())
                ->where('status', StayStatus::Active->value)
                ->exists();

            if ($alreadyActive) {
                throw new BusinessRuleException("Room {$locked->room_number} already has a guest checked in.");
            }

            if ($locked->status === RoomStatus::OutOfService || $locked->status === RoomStatus::Maintenance) {
                throw new BusinessRuleException("Room {$locked->room_number} is {$locked->status->label()} and cannot be used.");
            }

            $stay = CheckIn::query()->create([
                'booking_id' => $booking->getKey(),
                'room_id' => $locked->getKey(),
                'guest_id' => $booking->guest_id,
                'status' => StayStatus::Active,
                'checked_in_at' => now(),
                'checked_in_by' => $actor->getKey(),
            ]);

            $booking->forceFill(['status' => BookingStatus::CheckedIn])->save();
            $locked->forceFill(['status' => RoomStatus::Occupied])->save();

            $this->audit->success(
                action: 'stay.check_in',
                eventType: 'data',
                description: sprintf('Checked in %s to room %s (booking %s).', $booking->guest?->fullName() ?? 'guest', $locked->room_number, $booking->reference),
                subject: $stay,
                newValues: ['room' => $locked->room_number, 'booking' => $booking->reference],
            );

            return $stay->fresh(['room', 'guest', 'booking']);
        });
    }

    /**
     * Check a guest out and free the room for housekeeping.
     *
     * @throws BusinessRuleException
     */
    public function checkOut(CheckIn $stay, User $actor): CheckIn
    {
        if (! $stay->isActive()) {
            throw new BusinessRuleException('This stay has already been checked out.');
        }

        return DB::transaction(function () use ($stay, $actor): CheckIn {
            /** @var Room $room */
            $room = Room::query()->whereKey($stay->room_id)->lockForUpdate()->firstOrFail();

            $stay->forceFill([
                'status' => StayStatus::Completed,
                'checked_out_at' => now(),
                'checked_out_by' => $actor->getKey(),
                'actual_nights' => max(1, (int) $stay->checked_in_at->diffInDays(now())),
            ])->save();

            // Room goes to housekeeping.
            $room->forceFill(['status' => RoomStatus::Cleaning])->save();

            // Only mark the booking checked out when no active stays remain.
            $booking = $stay->booking;
            if ($booking && ! $booking->activeStays()->exists()) {
                $booking->forceFill(['status' => BookingStatus::CheckedOut])->save();
            }

            $this->audit->success(
                action: 'stay.check_out',
                eventType: 'data',
                description: sprintf('Checked out room %s (booking %s).', $room->room_number, $booking?->reference ?? '-'),
                subject: $stay,
                newValues: ['room' => $room->room_number, 'nights' => $stay->actual_nights],
            );

            return $stay->fresh(['room', 'guest', 'booking']);
        });
    }

    /**
     * Mark a confirmed booking as a no-show (guest never arrived).
     *
     * @throws BusinessRuleException
     */
    public function markNoShow(Booking $booking, User $actor): Booking
    {
        if ($booking->status !== BookingStatus::Confirmed) {
            throw new BusinessRuleException('Only confirmed bookings can be marked as a no-show.');
        }

        $booking->forceFill(['status' => BookingStatus::NoShow])->save();

        $this->audit->success(
            action: 'booking.no_show',
            eventType: 'data',
            description: sprintf('Marked booking %s as a no-show.', $booking->reference),
            subject: $booking,
            oldValues: ['status' => BookingStatus::Confirmed->value],
            newValues: ['status' => BookingStatus::NoShow->value],
        );

        return $booking->fresh();
    }
}
