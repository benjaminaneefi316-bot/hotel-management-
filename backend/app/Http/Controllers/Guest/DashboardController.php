<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $guest = Guest::query()->where('user_id', $user->getKey())->first();

        $bookings = $guest
            ? $guest->bookings()->with('rooms.roomType')->latest('check_in_date')->get()
            : collect();

        $upcoming = $bookings
            ->filter(fn (Booking $booking): bool => $booking->check_in_date !== null
                && $booking->check_out_date !== null
                && $booking->check_out_date->isFuture())
            ->sortBy('check_in_date')
            ->values();

        return view('guest.dashboard', [
            'guest' => $guest,
            'bookings' => $bookings,
            'upcoming' => $upcoming,
            'spent' => $bookings->sum(fn (Booking $booking): float => $booking->paidAmount()),
        ]);
    }
}
