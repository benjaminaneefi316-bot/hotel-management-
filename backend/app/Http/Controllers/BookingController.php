<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reservations.
 *
 * All the hard rules (double-booking prevention, date sanity, totals) live in
 * BookingService; this controller only validates input and translates
 * BusinessRuleException into a friendly flash message.
 */
final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly CheckInService $stays,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        $bookings = Booking::query()
            ->with(['guest', 'rooms', 'payments'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('guest', function ($guest) use ($search): void {
                            $guest->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($from !== '', fn ($q) => $q->whereDate('check_in_date', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('check_in_date', '<=', $to))
            ->latest('check_in_date')
            ->paginate(20)
            ->withQueryString();

        return view('bookings.index', [
            'bookings' => $bookings,
            'statuses' => BookingStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('bookings.create', [
            'guests' => Guest::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'rooms' => $this->bookableRooms(),
            'statuses' => BookingStatus::cases(),
            'selectedGuestId' => $request->integer('guest_id') ?: null,
            'selectedRoomId' => $request->integer('room_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'status' => ['nullable', Rule::in([BookingStatus::Pending->value, BookingStatus::Confirmed->value])],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $booking = $this->bookings->create($data, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bookings.show', $booking)
            ->with('status', "Booking {$booking->reference} created.");
    }

    public function show(Booking $booking): View
    {
        $booking->load(['guest', 'rooms.roomType', 'payments.recordedBy', 'stays.room', 'creator']);

        return view('bookings.show', [
            'booking' => $booking,
            'balance' => $this->bookings->outstandingBalance($booking),
        ]);
    }

    public function edit(Booking $booking): View
    {
        $booking->load(['guest', 'rooms.roomType']);

        return view('bookings.edit', [
            'booking' => $booking,
            'guests' => Guest::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'rooms' => $this->bookableRooms(),
            'statuses' => BookingStatus::cases(),
        ]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'room_ids' => ['nullable', 'array', 'min:1'],
            'room_ids.*' => ['integer', 'exists:rooms,id'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->bookings->update($booking, $data, $data['room_ids'] ?? null, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking updated.');
    }

    /** AJAX: which rooms are free for the requested window? */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
        ]);

        try {
            $rooms = $this->bookings->availableRooms(
                $data['check_in_date'],
                $data['check_out_date'],
                isset($data['room_type_id']) ? (int) $data['room_type_id'] : null,
            );
        } catch (BusinessRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'rooms' => $rooms->map(fn (Room $room): array => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'room_type' => $room->roomType?->name,
                'capacity' => $room->roomType?->capacity,
                'rate' => (float) $room->effectiveRate(),
            ])->values(),
        ]);
    }

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        try {
            $this->bookings->confirm($booking, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Booking {$booking->reference} confirmed.");
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->bookings->cancel($booking, $request->user(), $data['reason'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Booking {$booking->reference} cancelled.");
    }

    public function noShow(Request $request, Booking $booking): RedirectResponse
    {
        try {
            $this->stays->markNoShow($booking, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Booking {$booking->reference} marked as a no-show.");
    }

    /**
     * Rooms that may be offered on the booking form.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Room>
     */
    private function bookableRooms(): \Illuminate\Database\Eloquent\Collection
    {
        return Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->orderBy('room_number')
            ->get();
    }
}
