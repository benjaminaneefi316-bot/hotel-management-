@extends('layouts.app')

@section('title', 'Bookings')
@section('eyebrow', 'Reservations')
@section('heading', 'Bookings')

@section('actions')
    <a href="{{ route('bookings.create') }}" class="btn btn-gold">
        <i class="bi bi-plus-lg me-1"></i> New booking
    </a>
@endsection

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
    @endphp

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('bookings.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label small text-muted-soft mb-1" for="search">Search</label>
                    <input type="search" id="search" name="search" class="form-control"
                           value="{{ request('search') }}" placeholder="Reference, guest name or email">
                </div>
                <div class="col-6 col-lg-2">
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
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-muted-soft mb-1" for="from">Arriving from</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ request('from') }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small text-muted-soft mb-1" for="to">Arriving to</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ request('to') }}">
                </div>
                <div class="col-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-charcoal w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    @if (request()->hasAny(['search', 'status', 'from', 'to']))
                        <a href="{{ route('bookings.index') }}" class="btn btn-soft" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body p-0">
            @if ($bookings->isEmpty())
                @include('partials.empty-state', [
                    'icon' => 'bi-calendar2-x',
                    'title' => 'No bookings found',
                    'message' => 'Try adjusting your filters or create a new reservation.',
                    'action' => '<a href="'.route('bookings.create').'" class="btn btn-gold">New booking</a>',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Reference</th>
                                <th>Guest</th>
                                <th>Dates</th>
                                <th>Rooms</th>
                                <th>Status</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                <tr>
                                    <td class="ps-3">
                                        <a href="{{ route('bookings.show', $booking) }}" class="fw-semibold text-dark">{{ $booking->reference }}</a>
                                        <div class="small text-muted-soft">{{ $booking->nights() }} night(s)</div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $booking->guest?->fullName() ?? '—' }}</div>
                                        <div class="small text-muted-soft">{{ $booking->guest?->email }}</div>
                                    </td>
                                    <td class="small">
                                        <div><i class="bi bi-box-arrow-in-right text-gold me-1"></i>{{ $booking->check_in_date?->format('d M Y') }}</div>
                                        <div><i class="bi bi-box-arrow-right text-gold me-1"></i>{{ $booking->check_out_date?->format('d M Y') }}</div>
                                    </td>
                                    <td class="small">
                                        @forelse ($booking->rooms as $room)
                                            <span class="badge badge-soft badge-soft-neutral me-1">{{ $room->room_number }}</span>
                                        @empty
                                            <span class="text-muted-soft">—</span>
                                        @endforelse
                                    </td>
                                    <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                                    <td class="text-end">@money($booking->total_amount)</td>
                                    @php $balance = $booking->balance(); @endphp
                                    <td class="text-end fw-semibold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                                        @money($balance)
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('bookings.show', $booking) }}" class="btn btn-sm btn-soft">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($bookings->hasPages())
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted-soft">
                    Showing {{ $bookings->firstItem() }}–{{ $bookings->lastItem() }} of {{ $bookings->total() }}
                </span>
                {{ $bookings->links() }}
            </div>
        @endif
    </div>

    <p class="small text-muted-soft mt-3 mb-0">Amounts shown in {{ $currency }}.</p>
@endsection
