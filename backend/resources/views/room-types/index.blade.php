@extends('layouts.app')

@section('title', 'Room types')
@section('eyebrow', 'Property')
@section('heading', 'Room types')

@section('actions')
    <a href="{{ route('room-types.create') }}" class="btn btn-gold">
        <i class="bi bi-plus-lg me-1"></i> New room type
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('room-types.index') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label small text-muted-soft">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="{{ request('search') }}" placeholder="Name or code">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-charcoal" type="submit"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="{{ route('room-types.index') }}" class="btn btn-soft" title="Reset">
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
                        <th>Type</th>
                        <th>Code</th>
                        <th>Bed</th>
                        <th>Capacity</th>
                        <th>Base price</th>
                        <th>Rooms</th>
                        <th>Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roomTypes as $roomType)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-image :src="$roomType->image_url" :alt="$roomType->name" class="rounded"
                                             style="width:46px;height:46px;object-fit:cover;" />
                                    <div class="fw-semibold">{{ $roomType->name }}</div>
                                </div>
                            </td>
                            <td><code>{{ $roomType->code }}</code></td>
                            <td class="text-muted-soft">{{ $roomType->bed_type }}</td>
                            <td class="text-muted-soft">{{ $roomType->capacity }}</td>
                            <td class="text-gold fw-semibold">@money($roomType->base_price)</td>
                            <td><span class="badge badge-soft badge-soft-neutral">{{ $roomType->rooms_count }}</span></td>
                            <td>
                                @if ($roomType->is_active)
                                    <span class="badge badge-soft badge-soft-success">Active</span>
                                @else
                                    <span class="badge badge-soft badge-soft-neutral">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('room-types.show', $roomType) }}" class="btn btn-sm btn-soft" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('room-types.edit', $roomType) }}" class="btn btn-sm btn-soft" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('room-types.destroy', $roomType) }}" class="d-inline"
                                      onsubmit="return confirm('Delete room type {{ $roomType->name }}? This cannot be undone.')">
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
                            <td colspan="8">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-grid-1x2',
                                    'title' => 'No room types',
                                    'message' => 'Create a room type to start adding rooms.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $roomTypes->links() }}</div>
@endsection
