@extends('layouts.guest')

@section('title', 'New booking request')

@section('content')
    @php
        $currency = app(\App\Services\SettingsService::class)->get('hotel_currency', 'USD');
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <div class="eyebrow">Guest portal</div>
            <h1 class="h3 mb-0">New booking request</h1>
        </div>
        <a href="{{ route('guest.bookings.index') }}" class="btn btn-soft">
            <i class="bi bi-arrow-left me-1"></i> My bookings
        </a>
    </div>

    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-info-circle text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            Submitting a request does not charge you. Our team confirms availability and will contact you; the request
            starts as <strong>pending</strong> until it is confirmed.
        </div>
    </div>

    <form method="POST" action="{{ route('guest.bookings.store') }}">
        @csrf

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Stay details</h2>

                        <div class="mb-3">
                            <label for="room_type_id" class="form-label">Room type <span class="text-danger">*</span></label>
                            <select id="room_type_id" name="room_type_id" class="form-select @error('room_type_id') is-invalid @enderror" required>
                                <option value="">Select a room type…</option>
                                @foreach ($roomTypes as $type)
                                    <option value="{{ $type->id }}" @selected((int) old('room_type_id') === $type->id)>
                                        {{ $type->name }} · @money($type->base_price) / night · sleeps {{ $type->capacity }}
                                    </option>
                                @endforeach
                            </select>
                            @error('room_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="check_in_date" class="form-label">Arrival <span class="text-danger">*</span></label>
                                <input type="date" id="check_in_date" name="check_in_date"
                                       class="form-control @error('check_in_date') is-invalid @enderror"
                                       value="{{ old('check_in_date', now()->addDay()->toDateString()) }}" required>
                                @error('check_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="check_out_date" class="form-label">Departure <span class="text-danger">*</span></label>
                                <input type="date" id="check_out_date" name="check_out_date"
                                       class="form-control @error('check_out_date') is-invalid @enderror"
                                       value="{{ old('check_out_date', now()->addDays(2)->toDateString()) }}" required>
                                @error('check_out_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="adults" class="form-label">Adults</label>
                                <input type="number" id="adults" name="adults" min="1" max="10"
                                       class="form-control" value="{{ old('adults', 1) }}">
                            </div>
                            <div class="col-md-6">
                                <label for="children" class="form-label">Children</label>
                                <input type="number" id="children" name="children" min="0" max="10"
                                       class="form-control" value="{{ old('children', 0) }}">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="special_requests" class="form-label">Special requests</label>
                            <textarea id="special_requests" name="special_requests" rows="3" maxlength="1000"
                                      class="form-control @error('special_requests') is-invalid @enderror"
                                      placeholder="Late arrival, cot needed, quiet room…">{{ old('special_requests') }}</textarea>
                            @error('special_requests')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 text-uppercase text-muted-soft mb-3" style="letter-spacing:.12em;">Guest</h2>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar">{{ strtoupper(substr($guest?->first_name ?? auth()->user()->name, 0, 1)) }}</span>
                            <div>
                                <div class="fw-semibold">{{ $guest?->fullName() ?? auth()->user()->name }}</div>
                                <div class="small text-muted-soft">{{ $guest?->email ?? auth()->user()->email }}</div>
                            </div>
                        </div>
                        <p class="small text-muted-soft mt-3 mb-0">
                            Need to update your details? Edit them in <a href="{{ route('profile.edit') }}">your profile</a>.
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn btn-gold w-100">
                    <i class="bi bi-send me-1"></i> Submit booking request
                </button>
            </div>
        </div>
    </form>
@endsection
