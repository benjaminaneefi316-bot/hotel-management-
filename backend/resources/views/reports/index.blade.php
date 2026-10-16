@extends('layouts.app')

@section('title', 'Reports')
@section('eyebrow', 'Analytics')
@section('heading', 'Reports')

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ number_format($summary['bookings']) }}</div>
                    <div class="kpi-label mt-1">Total bookings</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ number_format($summary['bookings_month']) }}</div>
                    <div class="kpi-label mt-1">Bookings this month</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ $currency }} @money($summary['revenue_month'])</div>
                    <div class="kpi-label mt-1">Revenue this month</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ number_format($summary['rooms']) }}</div>
                    <div class="kpi-label mt-1">Active rooms</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card card-hover h-100">
                <div class="card-body d-flex flex-column">
                    <div class="icon-circle mb-3"><i class="bi bi-bar-chart-line"></i></div>
                    <h2 class="h5">Occupancy</h2>
                    <p class="text-muted-soft small">Daily room occupancy for a chosen window, with the average rate.</p>
                    <a href="{{ route('reports.occupancy') }}" class="btn btn-charcoal mt-auto">View occupancy</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-hover h-100">
                <div class="card-body d-flex flex-column">
                    <div class="icon-circle mb-3"><i class="bi bi-cash-coin"></i></div>
                    <h2 class="h5">Revenue</h2>
                    <p class="text-muted-soft small">Collected payments by day and by method over a chosen window.</p>
                    <a href="{{ route('reports.revenue') }}" class="btn btn-charcoal mt-auto">View revenue</a>
                </div>
            </div>
        </div>
    </div>

    @can('export-reports')
        <div class="card mt-4">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Export CSV</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('reports.export', ['type' => 'bookings']) }}" class="btn btn-soft">
                        <i class="bi bi-download me-1"></i> Bookings (last 30 days)
                    </a>
                    <a href="{{ route('reports.export', ['type' => 'occupancy']) }}" class="btn btn-soft">
                        <i class="bi bi-download me-1"></i> Occupancy (last 30 days)
                    </a>
                    <a href="{{ route('reports.export', ['type' => 'revenue']) }}" class="btn btn-soft">
                        <i class="bi bi-download me-1"></i> Revenue (last 30 days)
                    </a>
                </div>
            </div>
        </div>
    @endcan
@endsection
