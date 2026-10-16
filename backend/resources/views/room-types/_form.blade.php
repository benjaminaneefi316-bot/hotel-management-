@php
    $roomType = $roomType ?? null;
    $amenityList = old('amenities', $roomType?->amenities ? implode(', ', $roomType->amenities) : '');
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code"
               value="{{ old('code', $roomType?->code) }}" maxlength="20" required>
        <div class="form-text">Short internal code, e.g. DLX.</div>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
               value="{{ old('name', $roomType?->name) }}" maxlength="80" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="bed_type" class="form-label">Bed type <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('bed_type') is-invalid @enderror" id="bed_type" name="bed_type"
               value="{{ old('bed_type', $roomType?->bed_type) }}" maxlength="40" required placeholder="1 King bed">
        @error('bed_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="capacity" class="form-label">Capacity <span class="text-danger">*</span></label>
        <input type="number" class="form-control @error('capacity') is-invalid @enderror" id="capacity" name="capacity"
               value="{{ old('capacity', $roomType?->capacity) }}" min="1" max="20" required>
        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="base_price" class="form-label">Base price / night <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" class="form-control @error('base_price') is-invalid @enderror"
                   id="base_price" name="base_price" value="{{ old('base_price', $roomType?->base_price) }}"
                   min="0" max="1000000" required>
            @error('base_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                  rows="3" maxlength="2000">{{ old('description', $roomType?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="amenities" class="form-label">Amenities</label>
        <input type="text" class="form-control @error('amenities') is-invalid @enderror" id="amenities" name="amenities"
               value="{{ $amenityList }}" maxlength="1000" placeholder="Wi-Fi, Air conditioning, Smart TV">
        <div class="form-text">Comma-separated list.</div>
        @error('amenities')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="image_url" class="form-label">Image (local path)</label>
        <input type="text" class="form-control @error('image_url') is-invalid @enderror" id="image_url" name="image_url"
               value="{{ old('image_url', $roomType?->image_url) }}" maxlength="500" list="local-room-type-images"
               placeholder="/assets/img/room-deluxe.jpg">
        <datalist id="local-room-type-images">
            <option value="/assets/img/room-classic.jpg"></option>
            <option value="/assets/img/room-deluxe.jpg"></option>
            <option value="/assets/img/room-executive.jpg"></option>
            <option value="/assets/img/room-family.jpg"></option>
            <option value="/assets/img/room-garden.jpg"></option>
            <option value="/assets/img/room-presidential.jpg"></option>
        </datalist>
        @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror

        @if ($roomType?->image_url)
            <div class="mt-2">
                <x-image :src="$roomType->image_url" :alt="$roomType->name" class="rounded"
                         style="height:120px;object-fit:cover;" />
            </div>
        @endif
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked((bool) old('is_active', $roomType?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Room type is active and bookable</label>
        </div>
    </div>
</div>
