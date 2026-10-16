@php
    $guest = $guest ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="first_name" class="form-label">First name <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name"
               name="first_name" value="{{ old('first_name', $guest?->first_name) }}" maxlength="80" required>
        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="last_name" class="form-label">Last name <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name"
               name="last_name" value="{{ old('last_name', $guest?->last_name) }}" maxlength="80" required>
        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
               value="{{ old('email', $guest?->email) }}" maxlength="190">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="date_of_birth" class="form-label">Date of birth</label>
        <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth"
               name="date_of_birth" value="{{ old('date_of_birth', $guest?->date_of_birth?->format('Y-m-d')) }}">
        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone"
               value="{{ old('phone', $guest?->phone) }}" maxlength="40">
        <div class="form-text">Stored encrypted at rest.</div>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="national_id" class="form-label">National ID / document number</label>
        <input type="text" class="form-control @error('national_id') is-invalid @enderror" id="national_id"
               name="national_id" value="{{ old('national_id', $guest?->national_id) }}" maxlength="60">
        <div class="form-text">Stored encrypted at rest.</div>
        @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="id_type" class="form-label">Document type</label>
        <input type="text" class="form-control @error('id_type') is-invalid @enderror" id="id_type" name="id_type"
               value="{{ old('id_type', $guest?->id_type) }}" maxlength="40" list="id-type-options"
               placeholder="Passport">
        <datalist id="id-type-options">
            <option value="Passport"></option>
            <option value="National ID"></option>
            <option value="Driver's licence"></option>
        </datalist>
        @error('id_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="nationality" class="form-label">Nationality</label>
        <input type="text" class="form-control @error('nationality') is-invalid @enderror" id="nationality"
               name="nationality" value="{{ old('nationality', $guest?->nationality) }}" maxlength="80">
        @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label">Address</label>
        <input type="text" class="form-control @error('address') is-invalid @enderror" id="address" name="address"
               value="{{ old('address', $guest?->address) }}" maxlength="255">
        <div class="form-text">Stored encrypted at rest.</div>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="city" class="form-label">City</label>
        <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city"
               value="{{ old('city', $guest?->city) }}" maxlength="100">
        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="postal_code" class="form-label">Postal code</label>
        <input type="text" class="form-control @error('postal_code') is-invalid @enderror" id="postal_code"
               name="postal_code" value="{{ old('postal_code', $guest?->postal_code) }}" maxlength="30">
        @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="country" class="form-label">Country</label>
        <input type="text" class="form-control @error('country') is-invalid @enderror" id="country" name="country"
               value="{{ old('country', $guest?->country) }}" maxlength="100">
        @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes</label>
        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2"
                  maxlength="1000">{{ old('notes', $guest?->notes) }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked((bool) old('is_active', $guest?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Guest profile is active</label>
        </div>
    </div>
</div>
