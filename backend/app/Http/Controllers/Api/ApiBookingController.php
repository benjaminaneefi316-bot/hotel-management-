<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bookings over the API.
 *
 * Staff (holders of `manage-bookings`) see every reservation and may create one
 * for any guest. A guest sees only their own bookings and may request a new stay
 * by room type; the reservation starts as `pending`.
 */
final class ApiBookingController extends ApiController
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Booking::query()->with(['guest', 'rooms.roomType', 'payments']);

        if (! $this->isStaff($user)) {
            $guestId = $user->guestProfiles()->value('id');

            if ($guestId === null) {
                return $this->ok(['data' => [], 'meta' => ['current_page' => 1, 'per_page' => 15, 'last_page' => 1, 'total' => 0]]);
            }

            $query->where('guest_id', $guestId);
        }

        $bookings = $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('check_in_date')
            ->paginate(min(50, max(1, (int) $request->input('per_page', 15))));

        return $this->ok($this->paginated(
            $bookings,
            fn (Booking $booking): array => $this->bookingPayload($booking, $user),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->isStaff($user)) {
            $data = $request->validate([
                'guest_id' => ['required', 'integer', 'exists:guests,id'],
                'check_in_date' => ['required', 'date'],
                'check_out_date' => ['required', 'date', 'after:check_in_date'],
                'room_ids' => ['required', 'array', 'min:1'],
                'room_ids.*' => ['integer', 'exists:rooms,id'],
                'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
                'children' => ['nullable', 'integer', 'min:0', 'max:20'],
                'special_requests' => ['nullable', 'string', 'max:2000'],
                'status' => ['nullable', 'in:'.BookingStatus::Pending->value.','.BookingStatus::Confirmed->value],
            ]);
        } else {
            $this->authorize('book-own-room');

            $data = $request->validate([
                'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
                'check_in_date' => ['required', 'date', 'after_or_equal:today'],
                'check_out_date' => ['required', 'date', 'after:check_in_date'],
                'adults' => ['nullable', 'integer', 'min:1', 'max:10'],
                'children' => ['nullable', 'integer', 'min:0', 'max:10'],
                'special_requests' => ['nullable', 'string', 'max:1000'],
            ]);

            $guest = $user->guestProfiles()->first();

            if (! $guest instanceof Guest) {
                return $this->fail('Your account is not linked to a guest profile. Please contact the hotel.', 422, 'no_guest_profile');
            }

            try {
                $room = $this->bookings
                    ->availableRooms($data['check_in_date'], $data['check_out_date'], (int) $data['room_type_id'])
                    ->first();
            } catch (BusinessRuleException $e) {
                return $this->fail($e->getMessage(), 422);
            }

            if ($room === null) {
                return $this->fail('No rooms of that type are free for the selected dates.', 409, 'no_availability');
            }

            $data['guest_id'] = $guest->getKey();
            $data['room_ids'] = [$room->getKey()];
            $data['status'] = BookingStatus::Pending->value;
        }

        try {
            $booking = $this->bookings->create($data, $user);
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 409, 'booking_conflict');
        }

        return $this->ok(['data' => $this->bookingPayload($booking, $user)], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        if (! $this->mayView($request->user(), $booking)) {
            return $this->fail('You may only view your own bookings.', 403, 'forbidden');
        }

        $booking->load(['guest', 'rooms.roomType', 'payments', 'stays.room', 'creator']);

        return $this->ok(['data' => $this->bookingPayload($booking, $request->user())]);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        if (! $this->mayView($request->user(), $booking)) {
            return $this->fail('You may only cancel your own bookings.', 403, 'forbidden');
        }

        if (! $booking->isCancellable()) {
            return $this->fail('This booking can no longer be cancelled. Please contact the hotel.', 409, 'not_cancellable');
        }

        try {
            $this->bookings->cancel($booking, $request->user(), $request->input('reason'));
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 409, 'cancel_failed');
        }

        return $this->ok(['status' => 'cancelled', 'reference' => $booking->reference]);
    }

    public function confirm(Request $request, Booking $booking): JsonResponse
    {
        try {
            $booking = $this->bookings->confirm($booking, $request->user());
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 409, 'confirm_failed');
        }

        $booking->load(['guest', 'rooms.roomType', 'payments']);

        return $this->ok(['data' => $this->bookingPayload($booking, $request->user())]);
    }

    private function isStaff(User $user): bool
    {
        return $user->isAdmin() || $user->isReceptionist();
    }

    private function mayView(User $user, Booking $booking): bool
    {
        if ($this->isStaff($user)) {
            return true;
        }

        return $booking->guest !== null && $booking->guest->user_id === $user->getKey();
    }
}
