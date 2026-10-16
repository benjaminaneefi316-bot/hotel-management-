<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): View
    {
        $guest = $this->guestProfile($request->user());

        $bookings = $guest
            ? $guest->bookings()->with('rooms.roomType')->latest('check_in_date')->paginate(10)
            : Booking::query()->whereRaw('1 = 0')->paginate(10);

        return view('guest.bookings.index', [
            'guest' => $guest,
            'bookings' => $bookings,
        ]);
    }

    public function create(Request $request): View
    {
        return view('guest.bookings.create', [
            'guest' => $this->guestProfile($request->user()),
            'roomTypes' => RoomType::query()->active()->orderBy('base_price')->get(),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $this->authorize('book-own-room');

        $user = $request->user();
        $guest = $this->ensureGuestProfile($user);
        $data = $request->validated();

        $adults = (int) ($data['adults'] ?? 1);
        $children = (int) ($data['children'] ?? 0);

        // Server-side availability check: never trust the client's chosen room.
        $available = $this->bookings->availableRooms(
            $data['check_in_date'],
            $data['check_out_date'],
            (int) $data['room_type_id'],
        );

        $room = $available->first(fn ($r): bool => $r->roomType !== null && $r->roomType->capacity >= ($adults + $children))
            ?? $available->first();

        if ($room === null) {
            throw new BusinessRuleException('Sorry, no rooms of that type are available for the selected dates.');
        }

        $booking = $this->bookings->create([
            'guest_id' => $guest->getKey(),
            'check_in_date' => $data['check_in_date'],
            'check_out_date' => $data['check_out_date'],
            'room_ids' => [$room->getKey()],
            'adults' => $adults,
            'children' => $children,
            'special_requests' => $data['special_requests'] ?? null,
            'source' => 'web',
            'status' => BookingStatus::Pending->value,
        ], $user);

        return redirect()->route('guest.bookings.show', $booking)
            ->with('status', 'Your booking request '.$booking->reference.' has been received.');
    }

    public function show(Request $request, Booking $booking): View
    {
        $this->assertCanView($request, $booking);

        $booking->load(['guest', 'rooms.roomType', 'payments', 'stays']);

        return view('guest.bookings.show', ['booking' => $booking]);
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('view-own-bookings');
        $this->assertCanView($request, $booking);

        if (! $booking->isCancellable()) {
            throw new BusinessRuleException('This booking can no longer be cancelled online. Please contact the hotel.');
        }

        $this->bookings->cancel($booking, $request->user(), 'Cancelled by guest from the guest portal.');

        return back()->with('status', 'Your booking '.$booking->reference.' has been cancelled.');
    }

    /** Guests may only touch their own bookings; staff may view any. */
    private function assertCanView(Request $request, Booking $booking): void
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isReceptionist()) {
            return;
        }

        abort_unless(
            $booking->guest !== null && $booking->guest->user_id === $user->getKey(),
            403,
        );
    }

    private function guestProfile(User $user): ?Guest
    {
        return Guest::query()->where('user_id', $user->getKey())->first();
    }

    private function ensureGuestProfile(User $user): Guest
    {
        $guest = $this->guestProfile($user);

        if ($guest !== null) {
            return $guest;
        }

        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        return Guest::query()->create([
            'user_id' => $user->getKey(),
            'first_name' => $parts[0] ?? (string) $user->name,
            'last_name' => $parts[1] ?? '',
            'email' => $user->email,
            'is_active' => true,
        ]);
    }
}
