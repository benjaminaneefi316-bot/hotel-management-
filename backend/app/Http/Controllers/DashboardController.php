<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Staff dashboard: KPIs, a 7-day occupancy/revenue trend and room occupancy.
 *
 * The numbers are computed with aggregate queries (never by loading every row
 * into memory) so the page stays fast regardless of how much demo data exists.
 */
final class DashboardController extends Controller
{
    /** Statuses that occupy a room's calendar. */
    private const BLOCKING = ['pending', 'confirmed', 'checked_in', 'checked_out'];

    public function index(Request $request): View
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();

        $totalRooms = max(1, Room::query()->where('is_active', true)->count());
        $occupiedToday = $this->occupiedRoomsOn($today);

        $kpi = [
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
        ];

        $roomStatusBreakdown = Room::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status instanceof RoomStatus ? $row->status : RoomStatus::from((string) $row->status),
                'count' => (int) $row->count,
            ])
            ->all();

        $roomTypeOccupancy = $this->roomTypeOccupancy($today);

        $recentBookings = Booking::query()
            ->with(['guest', 'rooms'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        [$chartLabels, $occupancySeries, $revenueSeries] = $this->trend($today, $totalRooms);

        return view('dashboard', [
            'kpi' => $kpi,
            'roomStatusBreakdown' => $roomStatusBreakdown,
            'roomTypeOccupancy' => $roomTypeOccupancy,
            'recentBookings' => $recentBookings,
            'chartLabels' => $chartLabels,
            'occupancySeries' => $occupancySeries,
            'revenueSeries' => $revenueSeries,
        ]);
    }

    /** Rooms with an active booking covering the given day. */
    private function occupiedRoomsOn(CarbonImmutable $day): int
    {
        $date = $day->toDateString();

        return (int) DB::table('booking_rooms')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->whereIn('bookings.status', self::BLOCKING)
            ->whereDate('bookings.check_in_date', '<=', $date)
            ->whereDate('bookings.check_out_date', '>', $date)
            ->distinct()
            ->count('booking_rooms.room_id');
    }

    /**
     * @return array<int, array{image: ?string, name: string, base_price: float, bed_type: string, capacity: int, occupancy_percent: float, occupied: int, total: int}>
     */
    private function roomTypeOccupancy(CarbonImmutable $day): array
    {
        $date = $day->toDateString();

        $occupiedByType = DB::table('booking_rooms')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->join('rooms', 'rooms.id', '=', 'booking_rooms.room_id')
            ->whereIn('bookings.status', self::BLOCKING)
            ->whereDate('bookings.check_in_date', '<=', $date)
            ->whereDate('bookings.check_out_date', '>', $date)
            ->selectRaw('rooms.room_type_id as room_type_id, COUNT(DISTINCT rooms.id) as occupied')
            ->groupBy('rooms.room_type_id')
            ->pluck('occupied', 'room_type_id');

        return RoomType::query()
            ->withCount(['rooms' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('base_price')
            ->get()
            ->map(function (RoomType $type) use ($occupiedByType): array {
                $total = (int) $type->rooms_count;
                $occupied = (int) ($occupiedByType[$type->id] ?? 0);

                return [
                    'image' => $type->image_url,
                    'name' => $type->name,
                    'base_price' => (float) $type->base_price,
                    'bed_type' => (string) $type->bed_type,
                    'capacity' => (int) $type->capacity,
                    'occupancy_percent' => $total > 0 ? round($occupied / $total * 100, 1) : 0.0,
                    'occupied' => $occupied,
                    'total' => $total,
                ];
            })
            ->all();
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, float>, 2: array<int, float>}
     */
    private function trend(CarbonImmutable $today, int $totalRooms): array
    {
        $labels = [];
        $occupancy = [];
        $revenue = [];

        $paidByDay = Payment::query()
            ->where('status', PaymentStatus::Paid->value)
            ->whereDate('paid_at', '>=', $today->subDays(6)->toDateString())
            ->get()
            ->groupBy(fn (Payment $p): string => $p->paid_at?->toDateString() ?? '')
            ->map(fn ($group): float => (float) $group->sum('amount'));

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->subDays($i);
            $date = $day->toDateString();

            $labels[] = $day->format('D d M');
            $occupancy[] = round($this->occupiedRoomsOn($day) / $totalRooms * 100, 1);
            $revenue[] = round((float) ($paidByDay[$date] ?? 0), 2);
        }

        return [$labels, $occupancy, $revenue];
    }
}
