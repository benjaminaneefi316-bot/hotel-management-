@extends('layouts.public')

@section('title', 'Accommodation · Azure Bay Hotel')
@section('meta_description', 'Browse the rooms and suites at Azure Bay Hotel — from garden twins to the presidential suite.')

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.62),rgba(24,28,32,.62)),url('{{ asset('assets/img/room-deluxe.jpg') }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Accommodation</div>
        <h1 class="display-serif display-5 text-white mb-2">Rooms &amp; suites</h1>
        <p class="text-white-50 mb-0">Six categories, each designed around a different kind of stay.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-4">
            @forelse ($roomTypes as $type)
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
                        'title' => 'No rooms published yet',
                        'message' => 'Room categories will appear here once they are added by the hotel team.',
                    ])
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
