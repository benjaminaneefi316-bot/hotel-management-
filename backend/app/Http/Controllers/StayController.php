<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CheckIn;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StayController extends Controller
{
    public function __construct(private readonly CheckInService $checkIns) {}

    public function checkIn(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('check-in-out');

        $data = $request->validate([
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        $roomId = isset($data['room_id']) ? (int) $data['room_id'] : null;

        $this->checkIns->checkIn($booking, $request->user(), $roomId);

        return back()->with('status', 'Guest checked in for reservation '.$booking->reference.'.');
    }

    public function checkOut(Request $request, CheckIn $stay): RedirectResponse
    {
        $this->authorize('check-in-out');

        $this->checkIns->checkOut($stay, $request->user());

        return back()->with('status', 'Guest checked out of room '.($stay->room?->room_number ?? '').'.');
    }
}
