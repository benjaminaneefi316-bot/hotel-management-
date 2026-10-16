@extends('layouts.public')

@section('title', 'Book your stay · Azure Bay Hotel')
@section('meta_description', 'Check live availability and rates at Azure Bay Hotel.')

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.6),rgba(24,28,32,.6)),url('{{ asset('assets/img/room-executive.jpg') }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Reservations</div>
        <h1 class="display-serif display-5 text-white mb-2">Book your stay</h1>
        <p class="text-white-50 mb-0">Tell us your dates and we will show you what is available.</p>
    </div>
</div>

<section class="section">
    <div class="container" style="max-width:900px;">
        <div class="card p-4 p-md-5 shadow-sm">
            <h2 class="display-serif h4 mb-4">Check availability</h2>
            <form method="POST" action="{{ route('public.booking.search') }}" class="row g-3" novalidate>
                @csrf
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="check_in_date">Check-in <span class="text-danger">*</span></label>
                    <input type="date" id="check_in_date" name="check_in_date"
                           class="form-control @error('check_in_date') is-invalid @enderror"
                           min="{{ now()->format('Y-m-d') }}"
                           value="{{ old('check_in_date', now()->addDay()->format('Y-m-d')) }}" required>
                    @error('check_in_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="check_out_date">Check-out <span class="text-danger">*</span></label>
                    <input type="date" id="check_out_date" name="check_out_date"
                           class="form-control @error('check_out_date') is-invalid @enderror"
                           min="{{ now()->addDays(2)->format('Y-m-d') }}"
                           value="{{ old('check_out_date', now()->addDays(3)->format('Y-m-d')) }}" required>
                    @error('check_out_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="guests">Guests <span class="text-danger">*</span></label>
                    <select id="guests" name="guests" class="form-select @error('guests') is-invalid @enderror" required>
                        @foreach (range(1, 6) as $n)
                            <option value="{{ $n }}" @selected(old('guests') == $n)>{{ $n }} {{ \Illuminate\Support\Str::plural('guest', $n) }}</option>
                        @endforeach
                    </select>
                    @error('guests') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="room_type_id">Room type</label>
                    <select id="room_type_id" name="room_type_id" class="form-select">
                        <option value="">Any room type</option>
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}" @selected(old('room_type_id') == $type->id)>
                                {{ $type->name }} — from @money((float) $type->base_price)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 mt-2">
                    <button type="submit" class="btn btn-gold btn-lg w-100">
                        <i class="bi bi-search me-2"></i>Search availability
                    </button>
                </div>
            </form>
        </div>

        <div class="row g-3 mt-4">
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="icon-circle"><i class="bi bi-calendar-check"></i></div>
                    <div class="small"><strong class="d-block">Flexible dates</strong><span class="text-muted-soft">Change your dates at any time before arrival.</span></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="icon-circle"><i class="bi bi-credit-card"></i></div>
                    <div class="small"><strong class="d-block">No prepayment</strong><span class="text-muted-soft">Pay at the hotel on arrival.</span></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="icon-circle"><i class="bi bi-shield-lock"></i></div>
                    <div class="small"><strong class="d-block">Secure</strong><span class="text-muted-soft">Your details are encrypted end to end.</span></div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
