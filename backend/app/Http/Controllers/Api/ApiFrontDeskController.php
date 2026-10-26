<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\StayStatus;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The arrivals / departures board for the mobile front desk.
 *
 * Arrivals are bookings due to check in today (pending ones must be confirmed
 * first, exactly as on the web front desk). Departures are in-house bookings
 * whose check-out date is today or already past.
 */
final class ApiFrontDeskController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $today = CarbonImmutable::today()->toDateString();

        $arrivals = Booking::query()
            ->with(['guest', 'rooms.roomType', 'payments', 'stays'])
            ->whereDate('check_in_date', $today)
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->orderBy('reference')
            ->get();

        $departures = Booking::query()
            ->with(['guest', 'rooms.roomType', 'payments', 'stays'])
            ->whereDate('check_out_date', '<=', $today)
            ->where('status', BookingStatus::CheckedIn->value)
            ->orderBy('check_out_date')
            ->get();

        $user = $request->user();

        return $this->ok([
            'date' => $today,
            'arrivals' => $arrivals
                ->map(fn (Booking $booking): array => $this->row($booking, $user))
                ->values()
                ->all(),
            'departures' => $departures
                ->map(fn (Booking $booking): array => $this->row($booking, $user))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Booking $booking, User $user): array
    {
        /** @var CheckIn|null $stay */
        $stay = $booking->stays->firstWhere('status', StayStatus::Active);

        return $this->bookingPayload($booking, $user) + [
            'stay_id' => $stay?->getKey(),
            'stay_status' => $stay?->status->value,
        ];
    }
}
