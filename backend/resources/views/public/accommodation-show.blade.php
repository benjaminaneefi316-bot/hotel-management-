@extends('layouts.public')

@section('title', $roomType->name.' · Azure Bay Hotel')

@section('content')
    <header class="page-hero" style="background-image:url('{{ $roomType->image_url ? (str_starts_with($roomType->image_url, 'http') ? $roomType->image_url : asset(ltrim($roomType->image_url, '/'))) : asset('assets/img/room-deluxe.jpg') }}');">
        <div class="container">
            <div class="eyebrow mb-2">Accommodation</div>
            <h1 class="mb-1">{{ $roomType->name }}</h1>
            <p class="mb-0 text-white-50">{{ $roomType->bed_type }} &middot; up to {{ $roomType->capacity }} guests</p>
        </div>
    </header>

    <section class="section bg-ivory">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-7">
                    <h2 class="h3 mb-3">About this room</h2>
                    <p class="text-muted-soft">
                        {{ $roomType->description ?: 'A beautifully appointed space designed for rest and relaxation at Azure Bay.' }}
                    </p>

                    <h3 class="h5 mt-4 mb-3">Amenities</h3>
                    <div class="row g-2">
                        @forelse (($roomType->amenities ?? []) as $amenity)
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center gap-2 text-muted-soft">
                                    <i class="bi bi-check2-circle text-gold"></i>{{ $amenity }}
                                </div>
                            </div>
                        @empty
                            <p class="text-muted-soft small mb-0">Amenities will be listed here.</p>
                        @endforelse
                    </div>

                    @if ($roomType->rooms->isNotEmpty())
                        <h3 class="h5 mt-4 mb-3">Rooms of this type</h3>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($roomType->rooms as $room)
                                <span class="stat-chip small">
                                    <i class="bi bi-door-closed text-gold me-1"></i>Room {{ $room->room_number }}
                                    @if ($room->view) <span class="text-muted-soft">· {{ $room->view }}</span> @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="col-lg-5">
                    <div class="card p-4 sticky-lg-top" style="top:90px;">
                        <div class="d-flex align-items-baseline justify-content-between">
                            <div class="kpi-value text-gold">@money($roomType->base_price)</div>
                            <div class="text-muted-soft small">per night</div>
                        </div>
                        <hr>
                        <ul class="list-unstyled small text-muted-soft d-grid gap-2">
                            <li><i class="bi bi-person text-gold me-2"></i>Sleeps up to {{ $roomType->capacity }}</li>
                            <li><i class="bi bi-moon-stars text-gold me-2"></i>{{ $roomType->bed_type }}</li>
                            <li><i class="bi bi-shield-check text-gold me-2"></i>Free cancellation where eligible</li>
                        </ul>
                        <a href="{{ route('public.booking', ['room_type_id' => $roomType->id]) }}" class="btn btn-gold w-100 py-2 mt-2">
                            <i class="bi bi-calendar2-check me-1"></i>Check availability
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="section" style="background:#fff;">
            <div class="container">
                <h2 class="h3 mb-4">You may also like</h2>
                <div class="row g-4">
                    @foreach ($related as $other)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.room-card', [
                                'image' => $other->image_url,
                                'alt' => $other->name.' at Azure Bay Hotel',
                                'title' => $other->name,
                                'price' => $other->base_price,
                                'meta' => e($other->bed_type).' &middot; up to '.e($other->capacity).' guests',
                                'description' => $other->description,
                                'url' => route('public.accommodation.show', $other),
                                'cta' => 'View details',
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
