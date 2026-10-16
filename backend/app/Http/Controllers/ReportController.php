<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Statuses that occupy a room's calendar. */
    private const BLOCKING = ['pending', 'confirmed', 'checked_in', 'checked_out'];

    public function index(): View
    {
        $summary = [
            'bookings' => Booking::query()->count(),
            'bookings_month' => Booking::query()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'revenue_month' => (float) Payment::query()
                ->where('status', PaymentStatus::Paid->value)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'rooms' => Room::query()->where('is_active', true)->count(),
        ];

        return view('reports.index', ['summary' => $summary]);
    }

    public function occupancy(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $totalRooms = max(1, Room::query()->where('is_active', true)->count());

        $rows = [];
        $average = 0.0;

        for ($cursor = CarbonImmutable::parse($from); $cursor->lessThanOrEqualTo(CarbonImmutable::parse($to)); $cursor = $cursor->addDay()) {
            $day = $cursor->toDateString();

            $occupied = DB::table('booking_rooms')
                ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
                ->whereIn('bookings.status', self::BLOCKING)
                ->whereDate('bookings.check_in_date', '<=', $day)
                ->whereDate('bookings.check_out_date', '>', $day)
                ->distinct()
                ->count('booking_rooms.room_id');

            $percent = round($occupied / $totalRooms * 100, 1);
            $rows[] = ['date' => $day, 'occupied' => $occupied, 'total' => $totalRooms, 'percent' => $percent];
        }

        if ($rows !== []) {
            $average = round(array_sum(array_column($rows, 'percent')) / count($rows), 1);
        }

        return view('reports.occupancy', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'average' => $average,
            'totalRooms' => $totalRooms,
        ]);
    }

    public function revenue(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $base = DB::table('payments')
            ->where('status', PaymentStatus::Paid->value)
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to);

        $byDay = (clone $base)
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total, COUNT(*) as payments')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $byMethod = (clone $base)
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as payments')
            ->groupBy('method')
            ->orderByDesc('total')
            ->get();

        return view('reports.revenue', [
            'byDay' => $byDay,
            'byMethod' => $byMethod,
            'from' => $from,
            'to' => $to,
            'total' => (float) $byDay->sum('total'),
        ]);
    }

    public function export(string $type, Request $request): StreamedResponse
    {
        $this->authorize('export-reports');

        abort_unless(in_array($type, ['bookings', 'occupancy', 'revenue'], true), 404);

        [$from, $to] = $this->range($request);
        $filename = sprintf('%s-%s-to-%s.csv', $type, $from, $to);

        return response()->streamDownload(function () use ($type, $from, $to): void {
            $out = fopen('php://output', 'wb');

            if ($type === 'bookings') {
                fputcsv($out, ['Reference', 'Guest', 'Check in', 'Check out', 'Status', 'Total', 'Paid']);
                Booking::query()
                    ->with('guest')
                    ->whereDate('check_in_date', '>=', $from)
                    ->whereDate('check_in_date', '<=', $to)
                    ->orderBy('check_in_date')
                    ->chunk(200, function ($chunk) use ($out): void {
                        foreach ($chunk as $booking) {
                            fputcsv($out, [
                                $booking->reference,
                                $booking->guest?->fullName() ?? '',
                                $booking->check_in_date?->toDateString() ?? '',
                                $booking->check_out_date?->toDateString() ?? '',
                                $booking->status->label(),
                                number_format((float) $booking->total_amount, 2, '.', ''),
                                number_format($booking->paidAmount(), 2, '.', ''),
                            ]);
                        }
                    });
            } elseif ($type === 'occupancy') {
                $totalRooms = max(1, Room::query()->where('is_active', true)->count());
                fputcsv($out, ['Date', 'Occupied rooms', 'Total rooms', 'Occupancy %']);

                for ($cursor = CarbonImmutable::parse($from); $cursor->lessThanOrEqualTo(CarbonImmutable::parse($to)); $cursor = $cursor->addDay()) {
                    $day = $cursor->toDateString();
                    $occupied = DB::table('booking_rooms')
                        ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
                        ->whereIn('bookings.status', self::BLOCKING)
                        ->whereDate('bookings.check_in_date', '<=', $day)
                        ->whereDate('bookings.check_out_date', '>', $day)
                        ->distinct()
                        ->count('booking_rooms.room_id');

                    fputcsv($out, [$day, $occupied, $totalRooms, round($occupied / $totalRooms * 100, 1)]);
                }
            } else {
                fputcsv($out, ['Date', 'Payments', 'Amount']);

                $rows = DB::table('payments')
                    ->where('status', PaymentStatus::Paid->value)
                    ->whereDate('paid_at', '>=', $from)
                    ->whereDate('paid_at', '<=', $to)
                    ->selectRaw('DATE(paid_at) as day, SUM(amount) as total, COUNT(*) as payments')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->get();

                foreach ($rows as $row) {
                    fputcsv($out, [$row->day, $row->payments, number_format((float) $row->total, 2, '.', '')]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolve and sanity-check the requested reporting window.
     *
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $from = (string) ($request->input('from') ?: now()->subDays(29)->toDateString());
        $to = (string) ($request->input('to') ?: now()->toDateString());

        try {
            $from = CarbonImmutable::parse($from)->toDateString();
            $to = CarbonImmutable::parse($to)->toDateString();
        } catch (\Throwable) {
            $from = now()->subDays(29)->toDateString();
            $to = now()->toDateString();
        }

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        // Clamp very wide ranges so the per-day loop stays bounded.
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 366) {
            $from = CarbonImmutable::parse($to)->subDays(366)->toDateString();
        }

        return [$from, $to];
    }
}
