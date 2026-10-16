@extends('layouts.app')

@section('title', 'Payments')
@section('eyebrow', 'Money')
@section('heading', 'Payments')

@section('actions')
    <a href="{{ route('payments.create') }}" class="btn btn-gold"><i class="bi bi-plus-lg me-1"></i> Record payment</a>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('payments.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label small text-muted-soft mb-1" for="search">Search</label>
                    <input type="search" id="search" name="search" class="form-control" value="{{ request('search') }}"
                           placeholder="Booking reference or provider reference">
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-muted-soft mb-1" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected(request('status') === $statusOption->value)>
                                {{ $statusOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-muted-soft mb-1" for="method">Method</label>
                    <select id="method" name="method" class="form-select">
                        <option value="">All methods</option>
                        @foreach ($methods as $methodOption)
                            <option value="{{ $methodOption->value }}" @selected(request('method') === $methodOption->value)>
                                {{ $methodOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-charcoal w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    @if (request()->hasAny(['search', 'status', 'method']))
                        <a href="{{ route('payments.index') }}" class="btn btn-soft" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body p-0">
            @if ($payments->isEmpty())
                @include('partials.empty-state', [
                    'icon' => 'bi-credit-card',
                    'title' => 'No payments found',
                    'message' => 'Record a payment or adjust the filters.',
                    'action' => '<a href="'.route('payments.create').'" class="btn btn-gold">Record payment</a>',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Booking</th>
                                <th>Guest</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td class="ps-3 small">
                                        <a href="{{ route('payments.show', $payment) }}" class="fw-semibold text-dark">
                                            {{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y') }}
                                        </a>
                                        <div class="text-muted-soft">{{ $payment->created_at?->format('H:i') }}</div>
                                    </td>
                                    <td>
                                        @if ($payment->booking)
                                            <a href="{{ route('bookings.show', $payment->booking) }}" class="fw-semibold">{{ $payment->booking->reference }}</a>
                                        @else
                                            <span class="text-muted-soft">—</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $payment->booking?->guest?->fullName() ?? '—' }}</td>
                                    <td class="small">{{ $payment->method->label() }}</td>
                                    <td>@include('partials.status-badge', ['value' => $payment->status])</td>
                                    <td class="text-end pe-3 fw-semibold">{{ $payment->currency }} @money($payment->amount)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($payments->hasPages())
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted-soft">Showing {{ $payments->firstItem() }}–{{ $payments->lastItem() }} of {{ $payments->total() }}</span>
                {{ $payments->links() }}
            </div>
        @endif
    </div>
@endsection
