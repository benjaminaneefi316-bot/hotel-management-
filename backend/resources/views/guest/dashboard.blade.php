@extends('layouts.guest')

@section('title', 'My stay')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <div class="eyebrow">Guest portal</div>
            <h1 class="h3 mb-0">Welcome, {{ $guest?->first_name ?? auth()->user()->name }}</h1>
        </div>
        <a href="{{ route('guest.bookings.create') }}" class="btn btn-gold"><i class="bi bi-plus-lg me-1"></i>New booking request</a>
    </div>

    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['label' => 'Total bookings', 'value' => $bookings->count(), 'icon' => 'bi-calendar2-check'],
                ['label' => 'Upcoming stays', 'value' => $upcoming->count(), 'icon' => 'bi-suitcase-lg'],
                ['label' => 'Total paid', 'value' => number_format((float) $spent, 2), 'icon' => 'bi-cash-coin'],
            ];
        @endphp
        @foreach ($cards as $card)
            <div class="col-md-4">
                <div class="card kpi-card p-3 d-flex flex-row align-items-center gap-3">
                    <span class="icon-circle"><i class="bi {{ $card['icon'] }}"></i></span>
                    <div>
                        <div class="kpi-label">{{ $card['label'] }}</div>
                        <div class="kpi-value">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card table-card">
        <div class="d-flex justify-content-between align-items-center p-4 pb-2">
            <h2 class="h5 mb-0">Upcoming stays</h2>
            <a href="{{ route('guest.bookings.index') }}" class="small">All bookings</a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th>Reference</th><th>Arrival</th><th>Departure</th><th>Room</th><th>Status</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    @forelse ($upcoming as $booking)
                        <tr>
                            <td class="fw-semibold">{{ $booking->reference }}</td>
                            <td>{{ $booking->check_in_date?->format('d M Y') }}</td>
                            <td>{{ $booking->check_out_date?->format('d M Y') }}</td>
                            <td class="text-muted-soft small">{{ $booking->rooms->pluck('room_number')->implode(', ') ?: '—' }}</td>
                            <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                            <td class="text-end"><a href="{{ route('guest.bookings.show', $booking) }}" class="btn btn-sm btn-soft">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-soft py-4">You have no upcoming stays.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
