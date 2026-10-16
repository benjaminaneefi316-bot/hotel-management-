@extends('layouts.app')

@section('title', 'Revenue report')
@section('eyebrow', 'Analytics / Reports')
@section('heading', 'Revenue')

@section('actions')
    <a href="{{ route('reports.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back to reports
    </a>
    @can('export-reports')
        <a href="{{ route('reports.export', ['type' => 'revenue', 'from' => $from, 'to' => $to]) }}" class="btn btn-charcoal">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    @endcan
@endsection

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.revenue') }}" class="row g-2 align-items-end">
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
                    <a href="{{ route('reports.revenue') }}" class="btn btn-soft">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ $currency }} @money($total)</div>
                    <div class="kpi-label mt-1">Collected in period</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-value">{{ number_format($byDay->sum('payments')) }}</div>
                    <div class="kpi-label mt-1">Payments recorded</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card table-card">
                <div class="card-header bg-white fw-semibold">By day</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Date</th><th class="text-end">Payments</th><th class="pe-3 text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($byDay as $row)
                                <tr>
                                    <td class="ps-3">{{ \Carbon\CarbonImmutable::parse($row->day)->format('D d M Y') }}</td>
                                    <td class="text-end">{{ number_format($row->payments) }}</td>
                                    <td class="pe-3 text-end fw-semibold">@money($row->total)</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-cash-coin',
                                            'title' => 'No payments',
                                            'message' => 'No payments were collected in this period.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($byDay->isNotEmpty())
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td class="ps-3">Total</td>
                                    <td class="text-end">{{ number_format($byDay->sum('payments')) }}</td>
                                    <td class="pe-3 text-end">@money($total)</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card table-card">
                <div class="card-header bg-white fw-semibold">By method</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Method</th><th class="text-end">Payments</th><th class="pe-3 text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($byMethod as $row)
                                <tr>
                                    <td class="ps-3">
                                        {{ \App\Enums\PaymentMethod::tryFrom((string) $row->method)?->label() ?? \Illuminate\Support\Str::headline((string) $row->method) }}
                                    </td>
                                    <td class="text-end">{{ number_format($row->payments) }}</td>
                                    <td class="pe-3 text-end fw-semibold">@money($row->total)</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted-soft py-4">No payments in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p class="small text-muted-soft mt-3 mb-0">Amounts shown in {{ $currency }}. Only payments with status “paid” are counted.</p>
@endsection
