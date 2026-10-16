@extends('layouts.public')

@section('title', 'Azure Bay Hotel — A premium seaside retreat')
@section('meta_description', 'Wake to the sound of the ocean at Azure Bay Hotel. Six room categories, a rooftop pool, spa and two restaurants on the shores of Azure Bay.')

@section('content')

{{-- ============================= HERO ============================= --}}
<section class="hero d-flex align-items-center" style="background-image:linear-gradient(90deg, rgba(20,26,31,.82) 0%, rgba(20,26,31,.45) 55%, rgba(20,26,31,.25) 100%), url('{{ asset('assets/img/hero-lobby.jpg') }}');">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-7 text-white py-5">
                <div class="eyebrow text-gold mb-2">Azure Bay · Est. 1998</div>
                <h1 class="display-serif display-3 mb-3">Where the ocean sets the pace</h1>
                <p class="lead text-white-50 mb-4">
                    A refined seaside retreat where warm hospitality meets effortless luxury.
                    Six room categories, a rooftop infinity pool, a full-service spa and two
                    signature restaurants — all a barefoot walk from the water.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('public.booking') }}" class="btn btn-gold btn-lg px-4">
                        <i class="bi bi-calendar2-check me-2"></i>Check availability
                    </a>
                    <a href="{{ route('public.accommodation') }}" class="btn btn-outline-light btn-lg px-4">
                        Explore rooms
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-4 mt-5 text-white-50 small">
                    <div><i class="bi bi-star-fill text-gold me-1"></i> 4.9 / 5 guest rating</div>
                    <div><i class="bi bi-geo-alt-fill text-gold me-1"></i> 1 Ocean Promenade</div>
                    <div><i class="bi bi-shield-check text-gold me-1"></i> Secure booking</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ======================= BOOKING SEARCH ======================= --}}
<div class="container">
    <div class="search-card shadow-lg">
        <form method="POST" action="{{ route('public.booking.search') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small fw-semibold" for="check_in_date">Check-in</label>
                <input type="date" id="check_in_date" name="check_in_date" class="form-control"
                       min="{{ now()->format('Y-m-d') }}"
                       value="{{ old('check_in_date', now()->addDay()->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold" for="check_out_date">Check-out</label>
                <input type="date" id="check_out_date" name="check_out_date" class="form-control"
                       min="{{ now()->addDays(2)->format('Y-m-d') }}"
                       value="{{ old('check_out_date', now()->addDays(3)->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold" for="guests">Guests</label>
                <select id="guests" name="guests" class="form-select" required>
                    @foreach (range(1, 6) as $n)
                        <option value="{{ $n }}" @selected(old('guests') == $n)>{{ $n }} {{ \Illuminate\Support\Str::plural('guest', $n) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold" for="room_type_id">Room type</label>
                <select id="room_type_id" name="room_type_id" class="form-select">
                    <option value="">Any</option>
                    @foreach ($roomTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('room_type_id') == $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-charcoal w-100 py-2">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ============================ WELCOME ============================ --}}
<section class="section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="eyebrow text-gold mb-2">Welcome to Azure Bay</div>
                <h2 class="display-serif display-6 mb-3">A stay shaped around you</h2>
                <p class="text-muted-soft">
                    Since 1998, Azure Bay has welcomed travellers seeking a quieter kind of luxury.
                    Our 26 rooms and suites are designed to feel like home — only with better views,
                    softer linens and someone else making breakfast.
                </p>
                <p class="text-muted-soft">
                    Whether you are here for a single night or a long summer, our team handles the
                    details: airport transfers, restaurant reservations, spa treatments and a
                    personalised welcome for every returning guest.
                </p>
                <div class="row g-3 mt-4">
                    <div class="col-6 col-sm-3">
                        <div class="kpi-card text-center">
                            <div class="kpi-value">26</div>
                            <div class="kpi-label">Rooms &amp; suites</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="kpi-card text-center">
                            <div class="kpi-value">4.9</div>
                            <div class="kpi-label">Guest rating</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="kpi-card text-center">
                            <div class="kpi-value">24/7</div>
                            <div class="kpi-label">Front desk</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="kpi-card text-center">
                            <div class="kpi-value">2</div>
                            <div class="kpi-label">Restaurants</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-6">
                        <x-image src="/assets/img/gallery-pool.jpg" alt="Rooftop infinity pool at Azure Bay" class="media-cover rounded-4 shadow-sm" style="height:280px;width:100%;" />
                    </div>
                    <div class="col-6">
                        <x-image src="/assets/img/gallery-spa.jpg" alt="Azure Bay spa treatment room" class="media-cover rounded-4 shadow-sm" style="height:280px;width:100%;" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ========================= FEATURED ROOMS ========================= --}}
<section class="section bg-sand">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow text-gold mb-2">Accommodation</div>
            <h2 class="display-serif display-6 mb-2">Rooms &amp; suites</h2>
            <p class="text-muted-soft mb-0">From garden twins to the presidential suite — every room looks out on something worth waking up for.</p>
        </div>

        <div class="row g-4">
            @forelse ($featured as $type)
                <div class="col-md-6 col-lg-4">
                    @include('partials.room-card', [
                        'image' => $type->image_url,
                        'alt' => $type->name.' at Azure Bay Hotel',
                        'title' => $type->name,
                        'price' => (float) $type->base_price,
                        'meta' => '<i class="bi bi-people me-1"></i>'.$type->capacity.' guests · '.e($type->bed_type),
                        'description' => $type->description,
                        'features' => $type->amenities ?? [],
                        'url' => route('public.accommodation.show', $type),
                    ])
                </div>
            @empty
                <div class="col-12">
                    @include('partials.empty-state', [
                        'icon' => 'bi-door-closed',
                        'title' => 'Rooms are being updated',
                        'message' => 'Our room list is temporarily unavailable. Please check back shortly.',
                    ])
                </div>
            @endforelse
        </div>

        <div class="text-center mt-5">
            <a href="{{ route('public.accommodation') }}" class="btn btn-outline-gold px-4">
                View all accommodation <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>

{{-- ========================== FACILITIES ========================== --}}
<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow text-gold mb-2">Facilities</div>
            <h2 class="display-serif display-6 mb-2">More than a place to sleep</h2>
        </div>
        <div class="row g-4">
            @foreach ([
                ['bi-water', 'Rooftop pool', 'A heated infinity pool with sun loungers and poolside service until sunset.'],
                ['bi-flower1', 'Spa &amp; sauna', 'Signature treatments, a steam room and a full wellness programme.'],
                ['bi-cup-hot', 'Two restaurants', 'Coastal fine dining and an all-day terrace brasserie.'],
                ['bi-wifi', 'Fibre Wi-Fi', 'Complimentary high-speed internet in every room and public area.'],
                ['bi-briefcase', 'Business centre', 'Meeting rooms, printing and a quiet lounge for working guests.'],
                ['bi-car-front', 'Airport transfers', 'Private, pre-booked transfers to and from the airport.'],
            ] as [$icon, $title, $copy])
                <div class="col-md-6 col-lg-4">
                    <div class="card card-hover h-100 p-4">
                        <div class="icon-circle mb-3"><i class="bi {{ $icon }}"></i></div>
                        <h3 class="h5">{!! $title !!}</h3>
                        <p class="text-muted-soft small mb-0">{{ $copy }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- =========================== GALLERY =========================== --}}
<section class="section-sm bg-sand">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <div class="eyebrow text-gold mb-2">Gallery</div>
                <h2 class="display-serif display-6 mb-0">Life at Azure Bay</h2>
            </div>
            <a href="{{ route('public.gallery') }}" class="btn btn-sm btn-outline-gold d-none d-sm-inline-flex">See all photos</a>
        </div>
        <div class="row g-3">
            @foreach ([
                ['/assets/img/gallery-exterior.jpg', 'The hotel facade at dusk'],
                ['/assets/img/gallery-pool.jpg', 'Rooftop infinity pool'],
                ['/assets/img/gallery-dining.jpg', 'The terrace brasserie'],
                ['/assets/img/gallery-suite.jpg', 'A suite living area'],
                ['/assets/img/gallery-spa.jpg', 'Spa treatment room'],
                ['/assets/img/hero-lobby.jpg', 'The lobby'],
            ] as [$src, $alt])
                <div class="col-6 col-lg-4">
                    <a href="{{ asset(ltrim($src, '/')) }}" class="gallery-item d-block rounded-4 overflow-hidden shadow-sm" target="_blank" rel="noopener">
                        <x-image :src="$src" :alt="$alt" class="media-cover" style="height:200px;width:100%;" />
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ========================= TESTIMONIALS ========================= --}}
<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow text-gold mb-2">Guest stories</div>
            <h2 class="display-serif display-6 mb-2">What our guests say</h2>
        </div>
        <div class="row g-4">
            @foreach ([
                ['“The most restful three nights I have had in years. The staff remembered our names from day one.”', 'Amelia R.', 'London, UK'],
                ['“We booked the family room and the kids never wanted to leave the pool. Faultless service.”', 'Diego M.', 'Madrid, Spain'],
                ['“The presidential suite is worth every cent. The view at sunrise is unforgettable.”', 'Priya S.', 'Mumbai, India'],
            ] as [$quote, $name, $place])
                <div class="col-lg-4">
                    <div class="card h-100 p-4">
                        <div class="text-gold mb-2">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="mb-4">{{ $quote }}</p>
                        <div class="d-flex align-items-center gap-2 mt-auto">
                            <span class="avatar">{{ \Illuminate\Support\Str::of($name)->substr(0, 1) }}</span>
                            <div class="small lh-sm">
                                <div class="fw-semibold">{{ $name }}</div>
                                <div class="text-muted-soft">{{ $place }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================ CTA BAND ============================ --}}
<section class="cta-band">
    <div class="container text-center text-white py-5">
        <div class="eyebrow text-gold mb-2">Reservations</div>
        <h2 class="display-serif display-6 mb-3">Ready for your Azure Bay escape?</h2>
        <p class="text-white-50 mb-4 mx-auto" style="max-width:640px;">
            Check live availability in seconds. No prepayment required to hold your dates —
            our reservations team confirms every booking personally.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('public.booking') }}" class="btn btn-gold btn-lg px-4">Check availability</a>
            <a href="{{ route('public.contact') }}" class="btn btn-outline-light btn-lg px-4">Talk to us</a>
        </div>
    </div>
</section>

@endsection
