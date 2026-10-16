@extends('layouts.app')

@section('title', 'Booking '.$booking->reference)
@section('eyebrow', 'Reservations')
@section('heading', 'Booking '.$booking->reference)

@section('actions')
    <div class="d-flex gap-2">
        @can('manage-bookings')
            @if ($booking->isCancellable() || $booking->status === \App\Enums\BookingStatus::CheckedIn)
                <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-soft"><i class="bi bi-pencil me-1"></i> Edit</a>
            @endif
        @endcan
        <a href="{{ route('bookings.index') }}" class="btn btn-soft"><i class="bi bi-arrow-left me-1"></i> Bookings</a>
    </div>
@endsection

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
        $paid = round((float) $booking->total_amount - (float) $balance, 2);
    @endphp

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <div class="eyebrow">Reference</div>
                            <h2 class="h4 mb-0">{{ $booking->reference }}</h2>
                        </div>
                        @include('partials.status-badge', ['value' => $booking->status])
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="stat-chip h-100">
                                <div class="kpi-label mb-1">Guest</div>
                                <div class="fw-semibold">{{ $booking->guest?->fullName() ?? '—' }}</div>
                                <div class="small text-muted-soft">{{ $booking->guest?->email }}</div>
                                @can('view-guest-sensitive-data')
                                    <div class="small text-muted-soft">{{ $booking->guest?->maskedPhone() }}</div>
                                @endcan
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="stat-chip h-100">
                                <div class="kpi-label mb-1">Stay dates</div>
                                <div class="fw-semibold">{{ $booking->check_in_date?->format('d M Y') }} → {{ $booking->check_out_date?->format('d M Y') }}</div>
                                <div class="small text-muted-soft">{{ $booking->nights() }} night(s) · {{ $booking->adults }} adult(s), {{ $booking->children }} child(ren)</div>
                            </div>
                        </div>
                        @if ($booking->special_requests)
                            <div class="col-12">
                                <div class="alert alert-light border mb-0">
                                    <div class="kpi-label mb-1">Special requests</div>
                                    {{ $booking->special_requests }}
                                </div>
                            </div>
                        @endif
                        @can('manage-bookings')
                            @if ($booking->internal_notes)
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">
                                        <div class="kpi-label mb-1">Internal notes</div>
                                        <span style="white-space: pre-line;">{{ $booking->internal_notes }}</span>
                                    </div>
                                </div>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>

            <div class="card mb-4 table-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-door-closed text-gold me-2"></i>Rooms &amp; stays</span>
                    <span class="small text-muted-soft">{{ $booking->rooms->count() }} room(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Room</th>
                                <th>Type</th>
                                <th class="text-end">Rate / night</th>
                                <th class="text-end pe-3">Front desk</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($booking->rooms as $room)
                                @php
                                    $stay = $booking->stays->firstWhere('room_id', $room->id);
                                @endphp
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $room->room_number }}</td>
                                    <td class="small text-muted-soft">{{ $room->roomType?->name ?? '—' }}</td>
                                    <td class="text-end">@money($room->pivot->rate ?? $room->effectiveRate())</td>
                                    <td class="text-end pe-3">
                                        @can('check-in-out')
                                            @if ($stay && $stay->status === \App\Enums\StayStatus::Active)
                                                <form method="POST" action="{{ route('stays.check-out', $stay) }}" class="d-inline"
                                                      data-confirm="Check this guest out of room {{ $room->room_number }}?">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-charcoal">
                                                        <i class="bi bi-box-arrow-right me-1"></i> Check out
                                                    </button>
                                                </form>
                                            @elseif ($booking->isCheckInnable() && ! $stay)
                                                <form method="POST" action="{{ route('bookings.check-in', $booking) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="room_id" value="{{ $room->id }}">
                                                    <button type="submit" class="btn btn-sm btn-gold">
                                                        <i class="bi bi-box-arrow-in-right me-1"></i> Check in
                                                    </button>
                                                </form>
                                            @elseif ($stay)
                                                @include('partials.status-badge', ['value' => $stay->status])
                                            @else
                                                <span class="text-muted-soft small">—</span>
                                            @endif
                                        @else
                                            @if ($stay)
                                                @include('partials.status-badge', ['value' => $stay->status])
                                            @else
                                                <span class="text-muted-soft small">—</span>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card table-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-credit-card text-gold me-2"></i>Payments</span>
                    @can('manage-payments')
                        <a href="{{ route('payments.create', ['booking_id' => $booking->id]) }}" class="btn btn-sm btn-soft">
                            <i class="bi bi-plus-lg me-1"></i> Record payment
                        </a>
                    @endcan
                </div>
                @if ($booking->payments->isEmpty())
                    <div class="card-body text-muted-soft small">No payments recorded for this reservation.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Date</th>
                                    <th>Method</th>
                                    <th>Recorded by</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($booking->payments as $payment)
                                    <tr>
                                        <td class="ps-3 small">{{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y H:i') }}</td>
                                        <td class="small">{{ $payment->method->label() }}</td>
                                        <td class="small text-muted-soft">{{ $payment->recordedBy?->name ?? '—' }}</td>
                                        <td>@include('partials.status-badge', ['value' => $payment->status])</td>
                                        <td class="text-end pe-3 fw-semibold">
                                            <a href="{{ route('payments.show', $payment) }}">@money($payment->amount)</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Folio</h2>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-soft">Room total</span>
                        <span class="fw-semibold">@money($booking->total_amount) {{ $currency }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-soft">Paid</span>
                        <span class="text-success fw-semibold">@money($paid) {{ $currency }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Balance</span>
                        <span class="kpi-value {{ $balance > 0 ? 'text-danger' : 'text-success' }}" style="font-size:1.5rem;">
                            @money($balance)
                        </span>
                    </div>
                </div>
            </div>

            @can('manage-bookings')
                <div class="card">
                    <div class="card-body d-grid gap-2">
                        <h2 class="h6 text-uppercase text-muted-soft mb-1" style="letter-spacing:.12em;">Actions</h2>

                        @if ($booking->status === \App\Enums\BookingStatus::Pending)
                            <form method="POST" action="{{ route('bookings.confirm', $booking) }}">
                                @csrf
                                <button type="submit" class="btn btn-gold w-100">
                                    <i class="bi bi-check2-circle me-1"></i> Confirm reservation
                                </button>
                            </form>
                        @endif

                        @can('check-in-out')
                            @if ($booking->status === \App\Enums\BookingStatus::Confirmed)
                                <form method="POST" action="{{ route('bookings.no-show', $booking) }}"
                                      data-confirm="Mark this reservation as a no-show?">
                                    @csrf
                                    <button type="submit" class="btn btn-soft w-100 text-danger">
                                        <i class="bi bi-person-dash me-1"></i> Mark as no-show
                                    </button>
                                </form>
                            @endif
                        @endcan

                        @if ($booking->isCancellable())
                            <button class="btn btn-soft w-100 text-danger" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#cancel-booking" aria-expanded="false" aria-controls="cancel-booking">
                                <i class="bi bi-x-circle me-1"></i> Cancel reservation
                            </button>
                            <div class="collapse" id="cancel-booking">
                                <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="border rounded-3 p-3">
                                    @csrf
                                    <label for="reason" class="form-label small">Cancellation reason</label>
                                    <input type="text" id="reason" name="reason" maxlength="255" class="form-control mb-2" placeholder="Optional">
                                    <button type="submit" class="btn btn-soft w-100 text-danger">Confirm cancellation</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endcan

            <div class="small text-muted-soft mt-3">
                Created {{ $booking->created_at?->format('d M Y H:i') }}
                @if ($booking->creator) by {{ $booking->creator->name }} @endif
            </div>
        </div>
    </div>
@endsection
