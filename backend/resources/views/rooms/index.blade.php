@extends('layouts.app')

@section('title', 'Rooms')
@section('eyebrow', 'Property')
@section('heading', 'Rooms')

@section('actions')
    <a href="{{ route('rooms.create') }}" class="btn btn-gold">
        <i class="bi bi-plus-lg me-1"></i> New room
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('rooms.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="search" class="form-label small text-muted-soft">Room number</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="{{ request('search') }}" placeholder="e.g. 301">
                </div>
                <div class="col-md-3">
                    <label for="room_type_id" class="form-label small text-muted-soft">Room type</label>
                    <select name="room_type_id" id="room_type_id" class="form-select">
                        <option value="">All types</option>
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) request('room_type_id') === (string) $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small text-muted-soft">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-charcoal w-100" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="{{ route('rooms.index') }}" class="btn btn-soft" title="Reset">
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
                        <th>Room</th>
                        <th>Type</th>
                        <th>Floor</th>
                        <th>View</th>
                        <th>Rate / night</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rooms as $room)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-image :src="$room->image_url" :alt="'Room '.$room->room_number"
                                             class="rounded" style="width:46px;height:46px;object-fit:cover;" />
                                    <div>
                                        <div class="fw-semibold">Room {{ $room->room_number }}</div>
                                        @unless ($room->is_active)
                                            <span class="badge badge-soft badge-soft-neutral">Inactive</span>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted-soft">{{ $room->roomType?->name ?? '—' }}</td>
                            <td class="text-muted-soft">{{ $room->floor ?? '—' }}</td>
                            <td class="text-muted-soft">{{ $room->view ?? '—' }}</td>
                            <td class="text-gold fw-semibold">@money($room->effectiveRate())</td>
                            <td>@include('partials.status-badge', ['value' => $room->status])</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('rooms.show', $room) }}" class="btn btn-sm btn-soft" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('rooms.edit', $room) }}" class="btn btn-sm btn-soft" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('rooms.destroy', $room) }}" class="d-inline"
                                      onsubmit="return confirm('Delete room {{ $room->room_number }}? This cannot be undone.')">
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
                                    'icon' => 'bi-building',
                                    'title' => 'No rooms found',
                                    'message' => 'Adjust your filters or add a new room.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $rooms->links() }}</div>
@endsection
