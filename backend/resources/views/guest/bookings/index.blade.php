@extends('layouts.guest')

@section('title', 'My bookings')

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <div class="eyebrow">Guest portal</div>
            <h1 class="h3 mb-0">My bookings</h1>
        </div>
        <a href="{{ route('guest.bookings.create') }}" class="btn btn-gold">
            <i class="bi bi-plus-lg me-1"></i> New booking request
        </a>
    </div>

    <div class="card table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Reference</th>
                        <th>Arrival</th>
                        <th>Departure</th>
                        <th>Room(s)</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                        <th class="pe-3 text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ $booking->reference }}</td>
                            <td class="small">{{ $booking->check_in_date?->format('d M Y') }}</td>
                            <td class="small">{{ $booking->check_out_date?->format('d M Y') }}</td>
                            <td class="small text-muted-soft">
                                {{ $booking->rooms->pluck('room_number')->implode(', ') ?: 'To be assigned' }}
                            </td>
                            <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                            <td class="text-end">@money($booking->total_amount)</td>
                            <td class="pe-3 text-end">
                                <a href="{{ route('guest.bookings.show', $booking) }}" class="btn btn-sm btn-soft">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-calendar2-x',
                                    'title' => 'No bookings yet',
                                    'message' => 'When you request a stay it will appear here.',
                                    'action' => '<a href="'.route('guest.bookings.create').'" class="btn btn-gold">New booking request</a>',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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
@endsection
