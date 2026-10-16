@extends('layouts.app')

@section('title', 'Front desk')
@section('eyebrow', 'Overview')
@section('heading', 'Front desk')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-label">Arrivals today</div>
                    <div class="kpi-value">{{ $arrivals->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-label">Departures today</div>
                    <div class="kpi-value">{{ $departures->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-label">In house</div>
                    <div class="kpi-value">{{ $inHouse->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card kpi-card h-100">
                <div class="card-body">
                    <div class="kpi-label">Needs attention</div>
                    <div class="kpi-value">{{ $needsCleaning->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Arrivals -------------------------------------------------------- --}}
        <div class="col-xl-6">
            <div class="card table-card h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-box-arrow-in-right me-1 text-gold"></i> Today's arrivals</span>
                    <span class="badge badge-soft badge-soft-info">{{ $arrivals->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Rooms</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($arrivals as $booking)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $booking->guest?->fullName() ?? 'Guest' }}</div>
                                        <div class="small text-muted-soft">{{ $booking->reference }}</div>
                                    </td>
                                    <td class="text-muted-soft">
                                        {{ $booking->rooms->pluck('room_number')->implode(', ') ?: '—' }}
                                    </td>
                                    <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                                    <td class="text-end">
                                        @can('check-in-out')
                                            @if ($booking->isCheckInnable())
                                                <form method="POST" action="{{ route('bookings.check-in', $booking) }}"
                                                      onsubmit="return confirm('Check in {{ $booking->guest?->fullName() ?? 'this guest' }}?')">
                                                    @csrf
                                                    <button class="btn btn-sm btn-gold" type="submit">
                                                        <i class="bi bi-box-arrow-in-right me-1"></i> Check in
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted-soft small">Not ready</span>
                                            @endif
                                        @else
                                            <span class="text-muted-soft small">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-calendar2-check',
                                            'title' => 'No arrivals today',
                                            'message' => 'There are no checked-in-eligible bookings arriving today.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Departures ------------------------------------------------------ --}}
        <div class="col-xl-6">
            <div class="card table-card h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-box-arrow-right me-1 text-gold"></i> Today's departures</span>
                    <span class="badge badge-soft badge-soft-warning">{{ $departures->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Checked in</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($departures as $stay)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $stay->guest?->fullName() ?? 'Guest' }}</div>
                                        <div class="small text-muted-soft">{{ $stay->booking?->reference ?? '—' }}</div>
                                    </td>
                                    <td class="text-muted-soft">Room {{ $stay->room?->room_number ?? '—' }}</td>
                                    <td class="small text-muted-soft">{{ $stay->checked_in_at?->format('d M, H:i') ?? '—' }}</td>
                                    <td class="text-end">
                                        @can('check-in-out')
                                            <form method="POST" action="{{ route('stays.check-out', $stay) }}"
                                                  onsubmit="return confirm('Check out room {{ $stay->room?->room_number }}?')">
                                                @csrf
                                                <button class="btn btn-sm btn-charcoal" type="submit">
                                                    <i class="bi bi-box-arrow-right me-1"></i> Check out
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted-soft small">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-box-arrow-right',
                                            'title' => 'No departures today',
                                            'message' => 'No in-house stay is scheduled to check out today.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- In-house -------------------------------------------------------- --}}
        <div class="col-xl-7">
            <div class="card table-card h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-house-door me-1 text-gold"></i> In-house guests</span>
                    <span class="badge badge-soft badge-soft-success">{{ $inHouse->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Booking</th>
                                <th>Check-out</th>
                                <th>Since</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inHouse as $stay)
                                <tr>
                                    <td class="fw-semibold">{{ $stay->guest?->fullName() ?? 'Guest' }}</td>
                                    <td class="text-muted-soft">
                                        Room {{ $stay->room?->room_number ?? '—' }}
                                        <div class="small text-muted-soft">{{ $stay->room?->roomType?->name }}</div>
                                    </td>
                                    <td class="small text-muted-soft">{{ $stay->booking?->reference ?? '—' }}</td>
                                    <td class="small">{{ $stay->booking?->check_out_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="small text-muted-soft">{{ $stay->checked_in_at?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-people',
                                            'title' => 'No guests in house',
                                            'message' => 'There are currently no active stays.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Housekeeping ---------------------------------------------------- --}}
        <div class="col-xl-5">
            <div class="card table-card h-100">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-brush me-1 text-gold"></i> Rooms needing attention</span>
                    <span class="badge badge-soft badge-soft-neutral">{{ $needsCleaning->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Status</th>
                                <th class="text-end">Set status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($needsCleaning as $room)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">Room {{ $room->room_number }}</div>
                                        <div class="small text-muted-soft">{{ $room->roomType?->name }}</div>
                                    </td>
                                    <td>@include('partials.status-badge', ['value' => $room->status])</td>
                                    <td class="text-end">
                                        @can('update-room-status')
                                            <form method="POST" action="{{ route('rooms.status', $room) }}"
                                                  class="d-flex gap-1 justify-content-end">
                                                @csrf
                                                <select name="status" class="form-select form-select-sm" style="width:auto;"
                                                        aria-label="New status for room {{ $room->room_number }}">
                                                    @foreach (\App\Enums\RoomStatus::cases() as $status)
                                                        <option value="{{ $status->value }}" @selected($room->status?->value === $status->value)>
                                                            {{ $status->label() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-sm btn-soft" type="submit" title="Update status">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted-soft small">—</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-stars',
                                            'title' => 'All clear',
                                            'message' => 'No rooms are awaiting cleaning or maintenance.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
