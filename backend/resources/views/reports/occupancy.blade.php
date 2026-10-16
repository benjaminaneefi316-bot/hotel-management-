@extends('layouts.app')

@section('title', 'Occupancy report')
@section('eyebrow', 'Analytics / Reports')
@section('heading', 'Occupancy')

@section('actions')
    <a href="{{ route('reports.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back to reports
    </a>
    @can('export-reports')
        <a href="{{ route('reports.export', ['type' => 'occupancy', 'from' => $from, 'to' => $to]) }}" class="btn btn-charcoal">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    @endcan
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.occupancy') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="from" class="form-label small text-muted-soft">From</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-md-4">
                    <label for="to" class="form-label small text-muted-soft">To</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $to }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-charcoal w-100"><i class="bi bi-search me-1"></i> Update</button>
                    <a href="{{ route('reports.occupancy') }}" class="btn btn-soft">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ $average }}%</div>
                    <div class="kpi-label mt-1">Average occupancy</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ number_format($totalRooms) }}</div>
                    <div class="kpi-label mt-1">Active rooms</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header bg-white fw-semibold">
            {{ \Carbon\CarbonImmutable::parse($from)->format('d M Y') }} – {{ \Carbon\CarbonImmutable::parse($to)->format('d M Y') }}
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Occupied</th>
                        <th>Total rooms</th>
                        <th class="pe-3" style="width:40%;">Occupancy</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="ps-3">{{ \Carbon\CarbonImmutable::parse($row['date'])->format('D d M Y') }}</td>
                            <td>{{ $row['occupied'] }}</td>
                            <td class="text-muted-soft">{{ $row['total'] }}</td>
                            <td class="pe-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:8px;">
                                        <div class="progress-bar bg-charcoal" role="progressbar"
                                             style="width: {{ min(100, (float) $row['percent']) }}%;"
                                             aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span class="small fw-semibold" style="min-width:52px;">{{ $row['percent'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-bar-chart-line',
                                    'title' => 'No occupancy data',
                                    'message' => 'Adjust the date range and try again.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
