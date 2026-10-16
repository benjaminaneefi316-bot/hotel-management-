<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public availability search. This only *shows* what is free; it does not
 * create a reservation (a signed-in guest or the front desk does that).
 */
final class BookingSearchController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::query()->active()->orderBy('name')->get();

        return view('public.booking', [
            'roomTypes' => $roomTypes,
        ]);
    }

    public function search(Request $request, BookingService $bookings): View
    {
        $data = $request->validate([
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'guests' => ['required', 'integer', 'min:1', 'max:10'],
            'room_type_id' => ['nullable', 'integer', 'exists:room_types,id'],
        ]);

        $guests = (int) $data['guests'];

        $rooms = $bookings
            ->availableRooms($data['check_in_date'], $data['check_out_date'], $data['room_type_id'] ?? null)
            ->filter(fn (Room $room): bool => (int) ($room->roomType?->capacity ?? 0) >= $guests)
            ->values();

        $nights = (int) \Carbon\CarbonImmutable::parse($data['check_in_date'])
            ->diffInDays(\Carbon\CarbonImmutable::parse($data['check_out_date']));

        $roomTypes = RoomType::query()->active()->orderBy('name')->get();

        // Cheapest room per type, for a tidy "from" summary.
        $fromPrices = $rooms
            ->groupBy(fn (Room $room): int => (int) $room->room_type_id)
            ->map(fn ($group) => $group->min(fn (Room $r): float => $r->effectiveRate()));

        return view('public.booking-results', [
            'rooms' => $rooms,
            'nights' => max(1, $nights),
            'guests' => $guests,
            'criteria' => $data,
            'roomTypes' => $roomTypes,
            'fromPrices' => $fromPrices,
        ]);
    }
}
