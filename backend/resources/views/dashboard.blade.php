@extends('layouts.app')

@section('title', 'Dashboard')
@section('eyebrow', 'Control centre')
@section('heading', 'Dashboard')

@section('actions')
    <a href="{{ route('front-desk.index') }}" class="btn btn-soft">
        <i class="bi bi-door-open me-1"></i> Front desk
    </a>
@endsection

@section('content')
    {{-- KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="kpi-label">Arrivals today</span>
                        <i class="bi bi-box-arrow-in-right text-gold"></i>
                    </div>
                    <div class="kpi-value mt-2">{{ number_format($kpi['arrivals_today']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="kpi-label">Departures today</span>
                        <i class="bi bi-box-arrow-right text-gold"></i>
                    </div>
                    <div class="kpi-value mt-2">{{ number_format($kpi['departures_today']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="kpi-label">In-house guests</span>
                        <i class="bi bi-people text-gold"></i>
                    </div>
                    <div class="kpi-value mt-2">{{ number_format($kpi['in_house']) }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="kpi-label">Occupancy</span>
                        <i class="bi bi-pie-chart text-gold"></i>
                    </div>
                    <div class="kpi-value mt-2">{{ number_format($kpi['occupancy_percent'], 1) }}%</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="kpi-label">Revenue (month)</span>
                        <i class="bi bi-cash-coin text-gold"></i>
                    </div>
                    <div class="kpi-value mt-2">@money($kpi['revenue_month'])</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="kpi-label">Pending bookings</span>
                        <i class="bi bi-hourglass-split text-gold"></i>
                    </div>
                    <div class="kpi-value mt-2">{{ number_format($kpi['pending_bookings']) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Occupancy & revenue trend --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <div class="eyebrow">Last 7 days</div>
                    <h2 class="h5 mb-0">Occupancy &amp; revenue</h2>
                </div>
                <span class="text-muted-soft small">Occupancy is measured against active rooms.</span>
            </div>
            <div style="position:relative;height:320px;">
                <canvas id="occupancyRevenueChart"
                        aria-label="Occupancy percentage and collected revenue over the last seven days"
                        role="img"></canvas>
            </div>
        </div>
    </div>

    {{-- Rooms --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <div class="eyebrow">Property</div>
            <h2 class="h5 mb-0">Rooms</h2>
        </div>
        <div class="d-none d-md-flex flex-wrap gap-2">
            @foreach ($roomStatusBreakdown as $row)
                <span class="stat-chip small d-inline-flex align-items-center gap-2">
                    @include('partials.status-badge', ['value' => $row['status']])
                    <span class="fw-semibold">{{ $row['count'] }}</span>
                </span>
            @endforeach
        </div>
    </div>

    <div class="row g-3 mb-4">
        @forelse ($roomTypeOccupancy as $type)
            <div class="col-md-6 col-xl-4">
                <div class="card card-hover h-100 overflow-hidden">
                    <x-image :src="$type['image']"
                             :alt="$type['name'].' at Azure Bay Hotel'"
                             class="room-card-img" />
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <h3 class="h6 mb-1">{{ $type['name'] }}</h3>
                            <span class="text-gold fw-semibold text-nowrap">@money($type['base_price'])</span>
                        </div>
                        <div class="text-muted-soft small mb-3">
                            {{ $type['bed_type'] }} &middot; sleeps {{ $type['capacity'] }}
                        </div>

                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted-soft">Occupancy</span>
                            <span class="fw-semibold">{{ number_format($type['occupancy_percent'], 1) }}%</span>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-gold"
                                 role="progressbar"
                                 style="width: {{ min(100, $type['occupancy_percent']) }}%;"
                                 aria-valuenow="{{ $type['occupancy_percent'] }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>
                        <div class="text-muted-soft small mt-2">
                            {{ $type['occupied'] }} of {{ $type['total'] }} rooms occupied
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                @include('partials.empty-state', [
                    'icon' => 'bi-building',
                    'title' => 'No rooms configured',
                    'message' => 'Room types and their occupancy will appear here once they are set up.',
                ])
            </div>
        @endforelse
    </div>

    {{-- Recent bookings --}}
    <div class="card table-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Recent bookings</h2>
            @can('manage-bookings')
                <a href="{{ route('bookings.index') }}" class="btn btn-sm btn-soft">
                    View all <i class="bi bi-arrow-right ms-1"></i>
                </a>
            @endcan
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                <tr>
                    <th>Reference</th>
                    <th>Guest</th>
                    <th>Stay</th>
                    <th>Rooms</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recentBookings as $booking)
                    <tr>
                        <td class="fw-semibold">{{ $booking->reference }}</td>
                        <td>{{ $booking->guest?->fullName() ?? '—' }}</td>
                        <td class="small text-muted-soft">
                            {{ $booking->check_in_date?->format('d M Y') }} &rarr; {{ $booking->check_out_date?->format('d M Y') }}
                            <div>{{ $booking->nights() }} night{{ $booking->nights() === 1 ? '' : 's' }}</div>
                        </td>
                        <td class="small">{{ $booking->rooms->pluck('room_number')->implode(', ') ?: '—' }}</td>
                        <td class="text-end">@money($booking->total_amount)</td>
                        <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            @include('partials.empty-state', [
                                'icon' => 'bi-calendar2-x',
                                'title' => 'No bookings yet',
                                'message' => 'New reservations will appear here as they are created.',
                            ])
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const canvas = document.getElementById('occupancyRevenueChart');

            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            new Chart(canvas, {
                data: {
                    labels: @json($chartLabels),
                    datasets: [
                        {
                            type: 'line',
                            label: 'Occupancy %',
                            data: @json($occupancySeries),
                            borderColor: '#c9a227',
                            backgroundColor: 'rgba(201, 162, 39, .16)',
                            yAxisID: 'y',
                            tension: 0.35,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 3,
                        },
                        {
                            type: 'bar',
                            label: 'Revenue (USD)',
                            data: @json($revenueSeries),
                            backgroundColor: 'rgba(31, 41, 51, .78)',
                            borderRadius: 6,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            position: 'left',
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: { callback: (value) => value + '%' },
                            grid: { color: 'rgba(0, 0, 0, .05)' },
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { callback: (value) => '$' + value },
                        },
                    },
                    plugins: {
                        legend: { position: 'bottom' },
                    },
                },
            });
        })();
    </script>
@endpush
