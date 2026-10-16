<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · @yield('title', 'Something went wrong') · Azure Bay Hotel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
</head>
<body class="bg-sand">
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card shadow-sm text-center p-5" style="max-width:560px;">
        <div class="icon-circle mx-auto mb-3"><i class="bi @yield('icon', 'bi-exclamation-triangle')"></i></div>
        <div class="display-serif text-gold" style="font-size:3.5rem;line-height:1;">@yield('code')</div>
        <h1 class="h3 mt-2">@yield('title', 'Something went wrong')</h1>
        <p class="text-muted-soft">@yield('message', 'We could not complete that request.')</p>
        <div class="d-flex gap-2 justify-content-center mt-3">
            <a href="{{ url('/') }}" class="btn btn-charcoal"><i class="bi bi-house me-1"></i>Home</a>
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-gold">Dashboard</a>
            @endauth
        </div>
    </div>
</div>
</body>
</html>
