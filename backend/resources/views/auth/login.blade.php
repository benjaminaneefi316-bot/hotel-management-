@extends('layouts.auth')

@section('title', 'Sign in')

@section('content')
    <h1 class="h4 mb-1">Welcome back</h1>
    <p class="text-muted-soft small mb-4">
        Sign in to manage reservations, guests and the property.
    </p>

    <form method="POST" action="{{ route('login.attempt') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-semibold" for="email">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" autocomplete="username"
                       placeholder="you@azurebay.test" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label small fw-semibold" for="password">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       autocomplete="current-password" placeholder="••••••••" required>
                <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                        aria-label="Show or hide password">
                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-charcoal w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Continue
        </button>
    </form>

    <div class="alert alert-light border small mt-4 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        For your security, a one-time code is emailed after your password is verified.
        Accounts are locked temporarily after repeated failed attempts.
    </div>

    <p class="text-center text-muted-soft small mt-4 mb-0">
        <a href="{{ route('public.home') }}" class="link-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to the hotel website
        </a>
    </p>

    @push('scripts')
    <script>
        (function () {
            var button = document.getElementById('togglePassword');
            var field = document.getElementById('password');
            var icon = document.getElementById('togglePasswordIcon');
            if (!button || !field || !icon) return;
            button.addEventListener('click', function () {
                var showing = field.type === 'text';
                field.type = showing ? 'password' : 'text';
                icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        })();
    </script>
    @endpush
@endsection
