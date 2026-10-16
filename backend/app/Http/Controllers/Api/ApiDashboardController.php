<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * A compact staff dashboard payload for the mobile app.
 */
final class ApiDashboardController extends ApiController
{
    /** Statuses that occupy a room's calendar. */
    private const BLOCKING = ['pending', 'confirmed', 'checked_in', 'checked_out'];

    public function summary(): JsonResponse
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();

        $totalRooms = max(1, Room::query()->where('is_active', true)->count());
        $occupiedToday = $this->occupiedRoomsOn($today->toDateString());

        return $this->ok([
            'date' => $today->toDateString(),
            'kpi' => [
                'arrivals_today' => Booking::query()
                    ->whereDate('check_in_date', $today->toDateString())
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                    ->count(),
                'departures_today' => Booking::query()
                    ->whereDate('check_out_date', $today->toDateString())
                    ->where('status', BookingStatus::CheckedIn->value)
                    ->count(),
                'in_house' => Booking::query()->where('status', BookingStatus::CheckedIn->value)->count(),
                'occupancy_percent' => round($occupiedToday / $totalRooms * 100, 1),
                'revenue_month' => (float) Payment::query()
                    ->where('status', PaymentStatus::Paid->value)
                    ->whereDate('paid_at', '>=', $monthStart->toDateString())
                    ->sum('amount'),
                'pending_bookings' => Booking::query()->where('status', BookingStatus::Pending->value)->count(),
            ],
            'rooms' => Room::query()
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(fn ($row): array => [
                    'status' => ($row->status instanceof RoomStatus ? $row->status : RoomStatus::from((string) $row->status))->value,
                    'label' => ($row->status instanceof RoomStatus ? $row->status : RoomStatus::from((string) $row->status))->label(),
                    'count' => (int) $row->count,
                ])
                ->all(),
        ]);
    }

    private function occupiedRoomsOn(string $date): int
    {
        return (int) DB::table('booking_rooms')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->whereIn('bookings.status', self::BLOCKING)
            ->whereDate('bookings.check_in_date', '<=', $date)
            ->whereDate('bookings.check_out_date', '>', $date)
            ->distinct()
            ->count('booking_rooms.room_id');
    }
}
