@extends('layouts.public')

@section('title', 'Accommodation · Azure Bay Hotel')

@section('content')
    <header class="page-hero" style="background-image:url('{{ asset('assets/img/room-deluxe.jpg') }}');">
        <div class="container text-center">
            <div class="eyebrow mb-2">Accommodation</div>
            <h1 class="mb-0">Rooms &amp; suites</h1>
        </div>
    </header>

    <section class="section bg-ivory">
        <div class="container">
            <div class="text-center mb-5">
                <p class="text-muted-soft mx-auto" style="max-width:680px;">
                    Choose the space that suits your stay — from classic comfort to our signature
                    presidential suite. All rates are per room, per night.
                </p>
            </div>

            <div class="row g-4">
                @forelse ($roomTypes as $roomType)
                    <div class="col-md-6 col-lg-4">
                        @include('partials.room-card', [
                            'image' => $roomType->image_url,
                            'alt' => $roomType->name.' at Azure Bay Hotel',
                            'title' => $roomType->name,
                            'price' => $roomType->base_price,
                            'meta' => e($roomType->bed_type).' &middot; up to '.e($roomType->capacity).' guests &middot; '.$roomType->rooms_count.' room(s)',
                            'description' => $roomType->description,
                            'features' => collect($roomType->amenities ?? [])->take(3)->all(),
                            'url' => route('public.accommodation.show', $roomType),
                            'cta' => 'View details',
                        ])
                    </div>
                @empty
                    <div class="col-12">
                        @include('partials.empty-state', ['icon' => 'bi-door-closed', 'title' => 'No room types yet', 'message' => 'Room types will appear here once they are added.'])
                    </div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
