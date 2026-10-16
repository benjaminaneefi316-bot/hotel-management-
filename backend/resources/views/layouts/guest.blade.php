<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My stay') · Azure Bay Hotel</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-sand">

<nav class="navbar navbar-expand-lg navbar-dark bg-charcoal py-3">
    <div class="container">
        <a class="navbar-brand" href="{{ route('guest.dashboard') }}">
            <span class="display-serif text-white fs-4">Azure&nbsp;Bay</span>
            <span class="text-gold small d-block" style="letter-spacing:.3em;font-size:.6rem;">GUEST PORTAL</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#guestNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="guestNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link @active('guest.dashboard')" href="{{ route('guest.dashboard') }}">Overview</a></li>
                <li class="nav-item"><a class="nav-link @active('guest.bookings.*')" href="{{ route('guest.bookings.index') }}">My bookings</a></li>
                <li class="nav-item"><a class="nav-link @active('profile.*')" href="{{ route('profile.edit') }}">Profile</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('public.home') }}">Hotel site</a></li>
                <li class="nav-item">
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-light rounded-pill ms-lg-2" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="section-sm">
    <div class="container">
        @include('partials.alerts')
        @yield('content')
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
