@extends('layouts.auth')

@section('title', 'Two-factor verification')

@section('content')
    <h1 class="h4 mb-1">Verify it's you</h1>
    <p class="text-muted-soft small mb-4">
        Enter the 6-digit code we emailed you. It expires in
        {{ (int) config('security.otp.ttl_minutes', 5) }} minutes.
    </p>

    @if (! empty($devCode))
        <div class="alert alert-warning small">
            <i class="bi bi-cone-striped me-1"></i>
            <strong>Development mode.</strong> The email mailer is disabled, so your code is shown here:
            <span class="fw-bold fs-5 ms-1">{{ $devCode }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label small fw-semibold" for="code">Verification code</label>
            <input type="text" id="code" name="code"
                   class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                   inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                   placeholder="••••••" autofocus required>
        </div>

        <button type="submit" class="btn btn-charcoal w-100 py-2">
            <i class="bi bi-check2-circle me-1"></i> Verify and sign in
        </button>
    </form>

    <hr class="my-4">

    <form method="POST" action="{{ route('two-factor.resend') }}" class="text-center">
        @csrf
        <span class="text-muted-soft small">Didn't get the code?</span>
        <button type="submit" class="btn btn-link btn-sm text-gold p-0 align-baseline">
            Resend code
        </button>
    </form>

    <div class="text-center mt-2">
        <a href="{{ route('login') }}" class="btn btn-link btn-sm link-secondary p-0">
            <i class="bi bi-arrow-left me-1"></i>Cancel and start over
        </a>
    </div>
@endsection
