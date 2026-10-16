@extends('layouts.app')

@section('title', 'Payment #'.$payment->id)
@section('eyebrow', 'Money')
@section('heading', 'Payment #'.$payment->id)

@section('actions')
    <a href="{{ route('payments.index') }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i> Back to payments</a>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="eyebrow">Amount</div>
                            <div class="kpi-value">{{ $payment->currency }} @money($payment->amount)</div>
                        </div>
                        @include('partials.status-badge', ['value' => $payment->status])
                    </div>

                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted-soft fw-normal">Method</dt>
                        <dd class="col-sm-8">{{ $payment->method->label() }}</dd>

                        <dt class="col-sm-4 text-muted-soft fw-normal">Provider</dt>
                        <dd class="col-sm-8">{{ $payment->provider ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted-soft fw-normal">Provider reference</dt>
                        <dd class="col-sm-8"><code>{{ $payment->provider_reference ?: '—' }}</code></dd>

                        <dt class="col-sm-4 text-muted-soft fw-normal">Recorded by</dt>
                        <dd class="col-sm-8">{{ $payment->recordedBy?->name ?? '—' }}</dd>

                        <dt class="col-sm-4 text-muted-soft fw-normal">Recorded at</dt>
                        <dd class="col-sm-8">{{ $payment->created_at?->format('d M Y H:i') }}</dd>

                        <dt class="col-sm-4 text-muted-soft fw-normal">Paid at</dt>
                        <dd class="col-sm-8">{{ $payment->paid_at?->format('d M Y H:i') ?? '—' }}</dd>

                        @if ($payment->refunded_at)
                            <dt class="col-sm-4 text-muted-soft fw-normal">Refunded at</dt>
                            <dd class="col-sm-8">{{ $payment->refunded_at->format('d M Y H:i') }}</dd>
                        @endif

                        @if ($payment->failure_reason)
                            <dt class="col-sm-4 text-muted-soft fw-normal">Failure reason</dt>
                            <dd class="col-sm-8 text-danger">{{ $payment->failure_reason }}</dd>
                        @endif

                        @if ($payment->notes)
                            <dt class="col-sm-4 text-muted-soft fw-normal">Notes</dt>
                            <dd class="col-sm-8" style="white-space: pre-line;">{{ $payment->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Booking</h2>
                    @if ($payment->booking)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <a href="{{ route('bookings.show', $payment->booking) }}" class="fw-semibold">{{ $payment->booking->reference }}</a>
                            @include('partials.status-badge', ['value' => $payment->booking->status])
                        </div>
                        <div class="small text-muted-soft mb-1">{{ $payment->booking->guest?->fullName() ?? '—' }}</div>
                        <div class="small text-muted-soft mb-3">
                            {{ $payment->booking->check_in_date?->format('d M Y') }} → {{ $payment->booking->check_out_date?->format('d M Y') }}
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted-soft">Booking total</span>
                            <span>@money($payment->booking->total_amount)</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted-soft">Outstanding balance</span>
                            <span class="fw-semibold {{ $payment->booking->balance() > 0 ? 'text-danger' : 'text-success' }}">
                                @money($payment->booking->balance())
                            </span>
                        </div>
                    @else
                        <p class="text-muted-soft small mb-0">This payment is not linked to a booking.</p>
                    @endif
                </div>
            </div>

            @can('manage-payments')
                @if ($payment->isPaid())
                    <div class="card">
                        <div class="card-body">
                            <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Refund</h2>
                            <form method="POST" action="{{ route('payments.refund', $payment) }}"
                                  data-confirm="Refund this payment? This cannot be undone.">
                                @csrf
                                <label for="reason" class="form-label small">Refund reason</label>
                                <input type="text" id="reason" name="reason" maxlength="255" class="form-control mb-3" placeholder="Optional">
                                <button type="submit" class="btn btn-soft w-100 text-danger">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Refund @money($payment->amount)
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif ($payment->status === \App\Enums\PaymentStatus::Refunded)
                    <div class="alert alert-secondary small mb-0">This payment has already been refunded.</div>
                @endif
            @endcan
        </div>
    </div>
@endsection
