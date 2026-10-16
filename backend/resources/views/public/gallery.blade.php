@extends('layouts.public')

@section('title', 'Gallery · Azure Bay Hotel')
@section('meta_description', 'A photo tour of Azure Bay Hotel — rooms, suites, the pool, the spa and our restaurants.')

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.6),rgba(24,28,32,.6)),url('{{ asset('assets/img/gallery-exterior.jpg') }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Gallery</div>
        <h1 class="display-serif display-5 text-white mb-2">Life at Azure Bay</h1>
        <p class="text-white-50 mb-0">Rooms, suites, the rooftop pool, the spa and everything in between.</p>
    </div>
</div>

@php
    $photos = [
        ['/assets/img/hero-lobby.jpg', 'The lobby', 'Rooms &amp; public areas'],
        ['/assets/img/room-classic.jpg', 'Classic Queen room', 'Rooms'],
        ['/assets/img/room-deluxe.jpg', 'Deluxe King room', 'Rooms'],
        ['/assets/img/room-executive.jpg', 'Executive Suite living area', 'Suites'],
        ['/assets/img/room-family.jpg', 'Family room with two queen beds', 'Rooms'],
        ['/assets/img/room-garden.jpg', 'Garden Twin with courtyard terrace', 'Rooms'],
        ['/assets/img/room-presidential.jpg', 'The presidential suite', 'Suites'],
        ['/assets/img/gallery-pool.jpg', 'Rooftop infinity pool', 'Wellness'],
        ['/assets/img/gallery-spa.jpg', 'Spa treatment room', 'Wellness'],
        ['/assets/img/gallery-dining.jpg', 'The terrace brasserie', 'Dining'],
        ['/assets/img/gallery-suite.jpg', 'Suite living area', 'Suites'],
        ['/assets/img/gallery-exterior.jpg', 'The hotel facade at dusk', 'The hotel'],
    ];
@endphp

<section class="section">
    <div class="container">
        <div class="row g-3 g-md-4">
            @foreach ($photos as $i => [$src, $alt, $group])
                <div class="col-6 col-lg-4">
                    <a href="{{ asset(ltrim($src, '/')) }}"
                       class="gallery-item d-block rounded-4 overflow-hidden shadow-sm position-relative"
                       target="_blank" rel="noopener">
                        <x-image :src="$src" :alt="$alt.' — Azure Bay Hotel'" class="media-cover"
                                 style="height:{{ $i % 5 === 0 ? 320 : 230 }}px;width:100%;"
                                 :loading="$i < 3 ? 'eager' : 'lazy'" />
                        <span class="position-absolute bottom-0 start-0 m-2 badge badge-soft badge-soft-gold">{{ $group }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
