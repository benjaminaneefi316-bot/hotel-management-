@php
    $room = $room ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="room_number" class="form-label">Room number <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('room_number') is-invalid @enderror" id="room_number"
               name="room_number" value="{{ old('room_number', $room?->room_number) }}" maxlength="20" required>
        @error('room_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="room_type_id" class="form-label">Room type <span class="text-danger">*</span></label>
        <select name="room_type_id" id="room_type_id" class="form-select @error('room_type_id') is-invalid @enderror" required>
            <option value="">Select a room type…</option>
            @foreach ($roomTypes as $type)
                <option value="{{ $type->id }}" @selected((int) old('room_type_id', $room?->room_type_id) === $type->id)>
                    {{ $type->name }} ({{ $type->code }})
                </option>
            @endforeach
        </select>
        @error('room_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="floor" class="form-label">Floor</label>
        <input type="number" class="form-control @error('floor') is-invalid @enderror" id="floor" name="floor"
               value="{{ old('floor', $room?->floor) }}" min="0" max="200">
        @error('floor')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="nightly_rate" class="form-label">Nightly rate override</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" class="form-control @error('nightly_rate') is-invalid @enderror"
                   id="nightly_rate" name="nightly_rate" value="{{ old('nightly_rate', $room?->nightly_rate) }}"
                   min="0" max="1000000">
            @error('nightly_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Leave blank to use the room type's base price.</div>
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', $room?->status?->value ?? 'available') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="view" class="form-label">View</label>
        <input type="text" class="form-control @error('view') is-invalid @enderror" id="view" name="view"
               value="{{ old('view', $room?->view) }}" maxlength="60" placeholder="e.g. Sea view">
        @error('view')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="image_url" class="form-label">Image (local path)</label>
        <input type="text" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url"
               value="{{ old('image_url', $room?->image_url) }}" maxlength="500" list="local-room-images"
               placeholder="/assets/img/room-deluxe.jpg">
        <datalist id="local-room-images">
            <option value="/assets/img/room-classic.jpg"></option>
            <option value="/assets/img/room-deluxe.jpg"></option>
            <option value="/assets/img/room-executive.jpg"></option>
            <option value="/assets/img/room-family.jpg"></option>
            <option value="/assets/img/room-garden.jpg"></option>
            <option value="/assets/img/room-presidential.jpg"></option>
        </datalist>
        @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Use one of the bundled local images under /assets/img.</div>

        @if ($room?->image_url)
            <div class="mt-2">
                <x-image :src="$room->image_url" :alt="'Room '.$room->room_number" class="rounded" style="height:120px;object-fit:cover;" />
            </div>
        @endif
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes</label>
        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes"
                  rows="2" maxlength="1000">{{ old('notes', $room?->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked((bool) old('is_active', $room?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Room is active and bookable</label>
        </div>
    </div>
</div>
