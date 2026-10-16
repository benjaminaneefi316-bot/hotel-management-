@extends('layouts.guest')

@section('title', 'Booking '.$booking->reference)

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
        $paid = (float) $booking->paidAmount();
        $balance = (float) $booking->balance();
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <div class="eyebrow">Guest portal / My bookings</div>
            <h1 class="h3 mb-0">Booking {{ $booking->reference }}</h1>
        </div>
        <a href="{{ route('guest.bookings.index') }}" class="btn btn-soft">
            <i class="bi bi-arrow-left me-1"></i> All bookings
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Stay</span>
                    @include('partials.status-badge', ['value' => $booking->status])
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-muted-soft">Arrival</dt>
                        <dd class="col-sm-8">{{ $booking->check_in_date?->format('D d M Y') }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Departure</dt>
                        <dd class="col-sm-8">{{ $booking->check_out_date?->format('D d M Y') }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Nights</dt>
                        <dd class="col-sm-8">{{ $booking->nights() }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Guests</dt>
                        <dd class="col-sm-8">{{ $booking->adults }} adult(s)@if ($booking->children), {{ $booking->children }} child(ren)@endif</dd>

                        <dt class="col-sm-4 text-muted-soft">Room(s)</dt>
                        <dd class="col-sm-8">
                            @forelse ($booking->rooms as $room)
                                <div>
                                    <span class="fw-semibold">Room {{ $room->room_number }}</span>
                                    <span class="text-muted-soft">· {{ $room->roomType?->name ?? 'Room' }}</span>
                                </div>
                            @empty
                                <span class="text-muted-soft">To be assigned by the hotel</span>
                            @endforelse
                        </dd>

                        @if ($booking->special_requests)
                            <dt class="col-sm-4 text-muted-soft">Special requests</dt>
                            <dd class="col-sm-8">{{ $booking->special_requests }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">Payments</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Date</th><th>Method</th><th>Status</th><th class="pe-3 text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($booking->payments as $payment)
                                <tr>
                                    <td class="ps-3 small">{{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y') }}</td>
                                    <td class="small">{{ $payment->method?->label() ?? '—' }}</td>
                                    <td>@include('partials.status-badge', ['value' => $payment->status])</td>
                                    <td class="pe-3 text-end">@money($payment->amount)</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted-soft py-4">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Summary</h2>
                    <ul class="list-unstyled d-grid gap-2 mb-0 small">
                        <li class="d-flex justify-content-between"><span class="text-muted-soft">Total</span><span class="fw-semibold">@money($booking->total_amount)</span></li>
                        <li class="d-flex justify-content-between"><span class="text-muted-soft">Paid</span><span class="fw-semibold text-success">@money($paid)</span></li>
                        <li class="d-flex justify-content-between border-top pt-2">
                            <span class="text-muted-soft">Balance</span>
                            <span class="fw-semibold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">@money($balance)</span>
                        </li>
                    </ul>
                    <p class="small text-muted-soft mt-3 mb-0">Amounts shown in {{ $currency }}.</p>
                </div>
            </div>

            @if ($booking->isCancellable())
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-2">Cancel this booking</h2>
                        <p class="small text-muted-soft">
                            Pending and confirmed bookings can be cancelled online. Cancellation is immediate.
                        </p>
                        <form method="POST" action="{{ route('guest.bookings.cancel', $booking) }}"
                              data-confirm="Cancel booking {{ $booking->reference }}?">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-circle me-1"></i> Cancel booking
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
