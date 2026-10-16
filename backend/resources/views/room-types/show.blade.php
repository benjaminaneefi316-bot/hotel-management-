@extends('layouts.app')

@section('title', $roomType->name)
@section('eyebrow', 'Property / Room types')
@section('heading', $roomType->name)

@section('actions')
    <a href="{{ route('room-types.edit', $roomType) }}" class="btn btn-gold">
        <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <a href="{{ route('room-types.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100 overflow-hidden">
                <x-image :src="$roomType->image_url" :alt="$roomType->name" class="w-100"
                         style="height:240px;object-fit:cover;" />
                <div class="card-body">
                    <h2 class="h4 mb-3">
                        {{ $roomType->name }}
                        @if ($roomType->is_active)
                            <span class="badge badge-soft badge-soft-success align-middle">Active</span>
                        @else
                            <span class="badge badge-soft badge-soft-neutral align-middle">Inactive</span>
                        @endif
                    </h2>

                    <dl class="row small mb-3">
                        <dt class="col-5 text-muted-soft">Code</dt>
                        <dd class="col-7"><code>{{ $roomType->code }}</code></dd>

                        <dt class="col-5 text-muted-soft">Slug</dt>
                        <dd class="col-7"><code>{{ $roomType->slug }}</code></dd>

                        <dt class="col-5 text-muted-soft">Bed type</dt>
                        <dd class="col-7">{{ $roomType->bed_type }}</dd>

                        <dt class="col-5 text-muted-soft">Capacity</dt>
                        <dd class="col-7">{{ $roomType->capacity }} guest(s)</dd>

                        <dt class="col-5 text-muted-soft">Base price</dt>
                        <dd class="col-7 text-gold fw-semibold">@money($roomType->base_price) / night</dd>

                        <dt class="col-5 text-muted-soft">Rooms</dt>
                        <dd class="col-7">{{ $roomType->rooms->count() }}</dd>
                    </dl>

                    @if ($roomType->description)
                        <p class="text-muted-soft small mb-3">{{ $roomType->description }}</p>
                    @endif

                    @if (! empty($roomType->amenities))
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($roomType->amenities as $amenity)
                                <span class="badge badge-soft badge-soft-gold">{{ $amenity }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card table-card">
                <div class="card-header bg-white fw-semibold">Rooms in this category</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Floor</th>
                                <th>View</th>
                                <th>Rate / night</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roomType->rooms as $room)
                                <tr>
                                    <td class="fw-semibold">Room {{ $room->room_number }}</td>
                                    <td class="text-muted-soft">{{ $room->floor ?? '—' }}</td>
                                    <td class="text-muted-soft">{{ $room->view ?? '—' }}</td>
                                    <td class="text-gold fw-semibold">@money($room->effectiveRate())</td>
                                    <td>@include('partials.status-badge', ['value' => $room->status])</td>
                                    <td class="text-end">
                                        <a href="{{ route('rooms.show', $room) }}" class="btn btn-sm btn-soft" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-building',
                                            'title' => 'No rooms yet',
                                            'message' => 'No physical rooms are assigned to this room type.',
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
                        <div class="fw-semibold">Delete this room type</div>
                        <div class="small text-muted-soft">Room types with attached rooms cannot be deleted.</div>
                    </div>
                    <form method="POST" action="{{ route('room-types.destroy', $roomType) }}"
                          onsubmit="return confirm('Delete room type {{ $roomType->name }}? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
