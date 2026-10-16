@extends('layouts.public')

@section('title', 'About · Azure Bay Hotel')
@section('meta_description', 'Our story, our values and the people who make Azure Bay Hotel feel like home.')

@section('content')
<div class="page-hero" style="background-image:linear-gradient(rgba(24,28,32,.55),rgba(24,28,32,.55)),url('{{ asset('assets/img/hero-lobby.jpg') }}');">
    <div class="container">
        <div class="eyebrow text-gold mb-2">Our story</div>
        <h1 class="display-serif display-5 text-white mb-2">About Azure Bay</h1>
        <p class="text-white-50 mb-0">Independent, family-run and quietly obsessed with the details.</p>
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <h2 class="display-serif display-6 mb-3">A retreat built on hospitality</h2>
                <p class="text-muted-soft">
                    Azure Bay opened its doors in 1998 with a simple idea: that a great hotel
                    should feel like a home you did not have to maintain. Three decades later we
                    are still family-run, still independent, and still answer the phone ourselves.
                </p>
                <p class="text-muted-soft">
                    Our 26 rooms and suites sit a barefoot walk from the water. We grow herbs for
                    the kitchen in the courtyard, source fish from the morning market, and know
                    most of our returning guests by name and by their preferred pillow.
                </p>
                <p class="text-muted-soft mb-0">
                    Whether you are here for a single night of rest or a long summer of slow
                    mornings, our aim is the same: that you leave a little lighter than you arrived.
                </p>
            </div>
            <div class="col-lg-6">
                <x-image src="/assets/img/gallery-exterior.jpg" alt="Azure Bay Hotel facade at dusk" class="media-cover rounded-4 shadow" style="height:380px;width:100%;" />
            </div>
        </div>
    </div>
</section>

<section class="section-sm bg-sand">
    <div class="container">
        <div class="row g-4">
            @foreach ([
                ['bi-heart', 'Genuine warmth', 'Service that remembers your name, your coffee and your birthday.'],
                ['bi-tree', 'Considered and green', 'Local sourcing, low-waste kitchens and a courtyard we actually plant.'],
                ['bi-shield-check', 'Safe and secure', 'Guest data is encrypted, access is role-based and every action is audited.'],
                ['bi-award', 'Quietly excellent', 'No gimmicks — just consistently high standards, year after year.'],
            ] as [$icon, $title, $copy])
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 p-4">
                        <div class="icon-circle mb-3"><i class="bi {{ $icon }}"></i></div>
                        <h3 class="h6">{{ $title }}</h3>
                        <p class="text-muted-soft small mb-0">{{ $copy }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container text-center text-white py-5">
        <h2 class="display-serif display-6 mb-3">Come and see for yourself</h2>
        <a href="{{ route('public.booking') }}" class="btn btn-gold btn-lg px-4">Check availability</a>
    </div>
</section>
@endsection
