@extends('layouts.public')

@section('title', $roomType->name.' · Azure Bay Hotel')
@section('meta_description', \Illuminate\Support\Str::limit((string) $roomType->description, 150))

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.55),rgba(24,28,32,.55)),url('{{ asset(ltrim($roomType->image_url ?: 'assets/img/room-deluxe.jpg', '/')) }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Accommodation</div>
        <h1 class="display-serif display-5 text-white mb-2">{{ $roomType->name }}</h1>
        <p class="text-white-50 mb-0">
            <i class="bi bi-people me-1"></i>{{ $roomType->capacity }} guests
            <span class="mx-2">·</span>
            <i class="bi bi-lamp me-1"></i>{{ $roomType->bed_type }}
        </p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <div class="rounded-4 overflow-hidden shadow-sm mb-4">
                    <x-image :src="$roomType->image_url" :alt="$roomType->name.' at Azure Bay Hotel'" class="media-cover" style="height:380px;width:100%;" loading="eager" />
                </div>

                <h2 class="display-serif h3 mb-3">About this room</h2>
                <p class="text-muted-soft">{{ $roomType->description }}</p>

                <h3 class="h5 mt-4 mb-3">In-room amenities</h3>
                <div class="row g-2">
                    @forelse ($roomType->amenities ?? [] as $amenity)
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill text-gold"></i>
                                <span class="small">{{ $amenity }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-muted-soft small">Amenity list coming soon.</div>
                    @endforelse
                </div>

                <div class="alert alert-light border mt-4 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    We currently have <strong>{{ $roomType->rooms_count }}</strong>
                    {{ \Illuminate\Support\Str::plural('room', $roomType->rooms_count) }} in this category.
                    Availability updates live as bookings are made.
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card p-4 shadow-sm position-sticky" style="top:96px;">
                    <div class="d-flex justify-content-between align-items-end mb-3">
                        <div>
                            <div class="text-muted-soft small">From</div>
                            <div class="display-serif h2 mb-0 text-gold">@money((float) $roomType->base_price)</div>
                            <div class="text-muted-soft small">per night</div>
                        </div>
                        <i class="bi bi-moon-stars display-6 text-muted-soft"></i>
                    </div>

                    <hr>

                    <form method="POST" action="{{ route('public.booking.search') }}">
                        @csrf
                        <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="ci">Check-in</label>
                            <input type="date" id="ci" name="check_in_date" class="form-control"
                                   min="{{ now()->format('Y-m-d') }}" value="{{ now()->addDay()->format('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="co">Check-out</label>
                            <input type="date" id="co" name="check_out_date" class="form-control"
                                   min="{{ now()->addDays(2)->format('Y-m-d') }}" value="{{ now()->addDays(3)->format('Y-m-d') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="g">Guests</label>
                            <select id="g" name="guests" class="form-select" required>
                                @foreach (range(1, max(1, (int) $roomType->capacity)) as $n)
                                    <option value="{{ $n }}">{{ $n }} {{ \Illuminate\Support\Str::plural('guest', $n) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-gold w-100 py-2">
                            <i class="bi bi-calendar2-check me-1"></i> Check availability
                        </button>
                    </form>

                    <p class="text-muted-soft small text-center mt-3 mb-0">
                        No prepayment required to hold your dates.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

@if ($others->isNotEmpty())
<section class="section-sm bg-sand">
    <div class="container">
        <h2 class="display-serif h3 mb-4">You may also like</h2>
        <div class="row g-4">
            @foreach ($others as $other)
                <div class="col-md-6 col-lg-4">
                    @include('partials.room-card', [
                        'image' => $other->image_url,
                        'alt' => $other->name.' at Azure Bay Hotel',
                        'title' => $other->name,
                        'price' => (float) $other->base_price,
                        'meta' => '<i class="bi bi-people me-1"></i>'.$other->capacity.' guests · '.e($other->bed_type),
                        'description' => $other->description,
                        'features' => array_slice($other->amenities ?? [], 0, 3),
                        'url' => route('public.accommodation.show', $other),
                    ])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
