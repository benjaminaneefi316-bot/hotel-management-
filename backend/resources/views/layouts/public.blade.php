<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Azure Bay Hotel')</title>
    <meta name="description" content="@yield('meta_description', 'Azure Bay Hotel — a premium seaside retreat. Book your stay, discover our rooms, suites and facilities.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-ivory">

<nav class="navbar navbar-expand-lg navbar-dark bg-charcoal sticky-top py-3">
    <div class="container">
        <a class="navbar-brand" href="{{ route('public.home') }}">
            <span class="display-serif text-white fs-4">Azure&nbsp;Bay</span>
            <span class="text-gold small d-block" style="letter-spacing:.3em;font-size:.6rem;">HOTEL &amp; RESORT</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link @active('public.home')" href="{{ route('public.home') }}">Home</a></li>
                <li class="nav-item"><a class="nav-link @active('public.accommodation*')" href="{{ route('public.accommodation') }}">Accommodation</a></li>
                <li class="nav-item"><a class="nav-link @active('public.gallery')" href="{{ route('public.gallery') }}">Gallery</a></li>
                <li class="nav-item"><a class="nav-link @active('public.about')" href="{{ route('public.about') }}">About</a></li>
                <li class="nav-item"><a class="nav-link @active('public.contact')" href="{{ route('public.contact') }}">Contact</a></li>
                @auth
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-light rounded-pill ms-lg-2" href="{{ auth()->user()->isAdmin() || auth()->user()->isReceptionist() ? route('dashboard') : route('guest.dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link @active('login')" href="{{ route('login') }}"><i class="bi bi-person me-1"></i>Sign in</a></li>
                @endauth
                <li class="nav-item"><a class="btn btn-sm btn-gold rounded-pill ms-lg-2 px-3" href="{{ route('public.booking') }}">Book now</a></li>
            </ul>
        </div>
    </div>
</nav>

<main>
    @yield('content')
</main>

@include('partials.footer')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
