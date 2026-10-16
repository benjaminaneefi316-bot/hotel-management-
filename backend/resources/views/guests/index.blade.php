@extends('layouts.app')

@section('title', 'Guests')
@section('eyebrow', 'Reservations')
@section('heading', 'Guests')

@section('actions')
    <a href="{{ route('guests.create') }}" class="btn btn-gold">
        <i class="bi bi-person-plus me-1"></i> New guest
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('guests.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label small text-muted-soft">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="{{ request('search') }}" placeholder="Name or email">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small text-muted-soft">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All guests</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-charcoal w-100" type="submit"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="{{ route('guests.index') }}" class="btn btn-soft" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Guest</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($guests as $guest)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar" style="width:34px;height:34px;font-size:.72rem;">
                                        {{ strtoupper(substr($guest->first_name, 0, 1).substr($guest->last_name, 0, 1)) }}
                                    </span>
                                    <div class="fw-semibold">{{ $guest->fullName() }}</div>
                                </div>
                            </td>
                            <td class="text-muted-soft">{{ $guest->email ?: '—' }}</td>
                            <td class="text-muted-soft">{{ $guest->maskedPhone() }}</td>
                            <td class="text-muted-soft">
                                {{ collect([$guest->city, $guest->country])->filter()->implode(', ') ?: '—' }}
                            </td>
                            <td><span class="badge badge-soft badge-soft-neutral">{{ $guest->bookings_count }}</span></td>
                            <td>
                                @if ($guest->is_active)
                                    <span class="badge badge-soft badge-soft-success">Active</span>
                                @else
                                    <span class="badge badge-soft badge-soft-neutral">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('guests.show', $guest) }}" class="btn btn-sm btn-soft" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('guests.edit', $guest) }}" class="btn btn-sm btn-soft" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('guests.destroy', $guest) }}" class="d-inline"
                                      onsubmit="return confirm('Delete {{ $guest->fullName() }}? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-soft text-danger" type="submit" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-people',
                                    'title' => 'No guests found',
                                    'message' => 'Adjust your search or create a new guest profile.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $guests->links() }}</div>
@endsection
