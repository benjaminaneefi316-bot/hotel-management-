<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Room;
use Illuminate\View\View;

/**
 * Front-desk overview: today's arrivals and departures, in-house guests and
 * rooms that need housekeeping attention.
 */
final class FrontDeskController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        $arrivals = Booking::query()
            ->with(['guest', 'rooms'])
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->orderBy('reference')
            ->get();

        $departures = CheckIn::query()
            ->with(['guest', 'room', 'booking'])
            ->where('status', StayStatus::Active->value)
            ->whereHas('booking', fn ($q) => $q->whereDate('check_out_date', '<=', $today))
            ->orderBy('checked_in_at')
            ->get();

        $inHouse = CheckIn::query()
            ->with(['guest', 'room.roomType', 'booking'])
            ->where('status', StayStatus::Active->value)
            ->orderBy('checked_in_at')
            ->get();

        $needsCleaning = Room::query()
            ->with('roomType')
            ->whereIn('status', [RoomStatus::Cleaning->value, RoomStatus::Maintenance->value])
            ->orderBy('room_number')
            ->get();

        return view('front-desk.index', compact('arrivals', 'departures', 'inHouse', 'needsCleaning'));
    }
}
