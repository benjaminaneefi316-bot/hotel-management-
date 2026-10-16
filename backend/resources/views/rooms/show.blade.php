@extends('layouts.app')

@section('title', 'Room ' . $room->room_number)
@section('eyebrow', 'Property / Rooms')
@section('heading', 'Room ' . $room->room_number)

@section('actions')
    @can('manage-rooms')
        <a href="{{ route('rooms.edit', $room) }}" class="btn btn-gold">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
    @endcan
    <a href="{{ route('rooms.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100 overflow-hidden">
                <x-image :src="$room->image_url" :alt="'Room '.$room->room_number.' — '.($room->roomType?->name ?? 'room')"
                         class="w-100" style="height:260px;object-fit:cover;" />
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h4 mb-0">Room {{ $room->room_number }}</h2>
                        @include('partials.status-badge', ['value' => $room->status])
                    </div>

                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted-soft">Room type</dt>
                        <dd class="col-7">{{ $room->roomType?->name ?? '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Floor</dt>
                        <dd class="col-7">{{ $room->floor ?? '—' }}</dd>

                        <dt class="col-5 text-muted-soft">View</dt>
                        <dd class="col-7">{{ $room->view ?? '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Base rate</dt>
                        <dd class="col-7">{{ $room->roomType ? '$'.number_format((float) $room->roomType->base_price, 2) : '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Effective rate</dt>
                        <dd class="col-7 text-gold fw-semibold">@money($room->effectiveRate()) / night</dd>

                        <dt class="col-5 text-muted-soft">Capacity</dt>
                        <dd class="col-7">{{ $room->roomType?->capacity ?? '—' }} guest(s)</dd>

                        <dt class="col-5 text-muted-soft">Active</dt>
                        <dd class="col-7">{{ $room->is_active ? 'Yes' : 'No' }}</dd>
                    </dl>

                    @if ($room->notes)
                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-sticky me-1"></i>{{ $room->notes }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if ($room->activeStay)
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Current stay</div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar">{{ $room->activeStay->guest?->fullName() ? substr($room->activeStay->guest->fullName(), 0, 2) : 'G' }}</span>
                            <div class="lh-sm">
                                <div class="fw-semibold">{{ $room->activeStay->guest?->fullName() ?? 'Guest' }}</div>
                                <div class="small text-muted-soft">
                                    Checked in {{ $room->activeStay->checked_in_at?->format('d M Y H:i') ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @can('update-room-status')
                <div class="card mb-3">
                    <div class="card-header bg-white fw-semibold">Change status</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('rooms.status', $room) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-8">
                                <label for="status" class="form-label small text-muted-soft">Room status</label>
                                <select name="status" id="status" class="form-select">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($room->status?->value === $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <button class="btn btn-charcoal w-100" type="submit">
                                    <i class="bi bi-arrow-repeat me-1"></i> Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card table-card">
                <div class="card-header bg-white fw-semibold">Upcoming &amp; recent bookings</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Guest</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($upcoming as $booking)
                                <tr>
                                    <td class="fw-semibold">{{ $booking->reference }}</td>
                                    <td>{{ $booking->guest?->fullName() ?? '—' }}</td>
                                    <td class="small">{{ $booking->check_in_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="small">{{ $booking->check_out_date?->format('d M Y') ?? '—' }}</td>
                                    <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-calendar2-x',
                                            'title' => 'No bookings',
                                            'message' => 'This room has no upcoming or recent reservations.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @can('manage-rooms')
                <div class="card mt-3">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Delete this room</div>
                            <div class="small text-muted-soft">Rooms with booking history cannot be deleted; deactivate them instead.</div>
                        </div>
                        <form method="POST" action="{{ route('rooms.destroy', $room) }}"
                              onsubmit="return confirm('Delete room {{ $room->room_number }}? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Delete</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
