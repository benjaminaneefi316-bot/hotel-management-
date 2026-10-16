@extends('layouts.app')

@section('title', $guest->fullName())
@section('eyebrow', 'Reservations / Guests')
@section('heading', $guest->fullName())

@section('actions')
    <a href="{{ route('guests.edit', $guest) }}" class="btn btn-gold">
        <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <a href="{{ route('guests.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="avatar avatar-lg mx-auto mb-3">
                        {{ strtoupper(substr($guest->first_name, 0, 1).substr($guest->last_name, 0, 1)) }}
                    </span>
                    <h2 class="h5 mb-1">{{ $guest->fullName() }}</h2>
                    <div class="text-muted-soft small mb-2">{{ $guest->email ?: 'No email on file' }}</div>
                    @if ($guest->is_active)
                        <span class="badge badge-soft badge-soft-success">Active</span>
                    @else
                        <span class="badge badge-soft badge-soft-neutral">Inactive</span>
                    @endif

                    <hr>

                    <dl class="row text-start small mb-0">
                        <dt class="col-5 text-muted-soft">Phone</dt>
                        <dd class="col-7">{{ $guest->phone ?: '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Document</dt>
                        <dd class="col-7">
                            {{ $guest->national_id ?: '—' }}
                            @if ($guest->id_type)
                                <div class="text-muted-soft">{{ $guest->id_type }}</div>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted-soft">Nationality</dt>
                        <dd class="col-7">{{ $guest->nationality ?: '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Date of birth</dt>
                        <dd class="col-7">{{ $guest->date_of_birth?->format('d M Y') ?? '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Address</dt>
                        <dd class="col-7">{{ $guest->address ?: '—' }}</dd>

                        <dt class="col-5 text-muted-soft">City</dt>
                        <dd class="col-7">{{ $guest->city ?: '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Country</dt>
                        <dd class="col-7">{{ $guest->country ?: '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Postal code</dt>
                        <dd class="col-7">{{ $guest->postal_code ?: '—' }}</dd>
                    </dl>

                    @if ($guest->notes)
                        <div class="alert alert-warning text-start small mt-3 mb-0">
                            <i class="bi bi-sticky me-1"></i>{{ $guest->notes }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card table-card">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Recent bookings</span>
                    <span class="badge badge-soft badge-soft-neutral">{{ $guest->bookings->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Rooms</th>
                                <th>Stay</th>
                                <th>Nights</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($guest->bookings as $booking)
                                <tr>
                                    <td class="fw-semibold">{{ $booking->reference }}</td>
                                    <td class="text-muted-soft">
                                        {{ $booking->rooms->pluck('room_number')->implode(', ') ?: '—' }}
                                    </td>
                                    <td class="small">
                                        {{ $booking->check_in_date?->format('d M Y') ?? '—' }}
                                        <i class="bi bi-arrow-right mx-1"></i>
                                        {{ $booking->check_out_date?->format('d M Y') ?? '—' }}
                                    </td>
                                    <td class="text-muted-soft">{{ $booking->nights() }}</td>
                                    <td class="fw-semibold">@money($booking->total_amount)</td>
                                    <td>@include('partials.status-badge', ['value' => $booking->status])</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-calendar2-x',
                                            'title' => 'No bookings',
                                            'message' => 'This guest has no booking history yet.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Delete this guest profile</div>
                        <div class="small text-muted-soft">Guests with booking history cannot be deleted; mark them inactive instead.</div>
                    </div>
                    <form method="POST" action="{{ route('guests.destroy', $guest) }}"
                          onsubmit="return confirm('Delete this guest profile? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
