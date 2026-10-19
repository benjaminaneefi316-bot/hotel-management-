@extends('layouts.auth')

@section('title', 'Authenticator app')

@section('content')
    <h1 class="h4 mb-1">Authenticator app</h1>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    {{-- ---------------------------------------------------------------- --}}
    {{-- Recovery codes are deliberately NOT displayed here. They are        --}}
    {{-- generated but not stored or accepted anywhere yet (see             --}}
    {{-- TotpService::generateRecoveryCodes), so showing them would send    --}}
    {{-- someone to save codes that could never get them back in.           --}}
    {{-- ---------------------------------------------------------------- --}}

    {{-- ---------------------------------------------------------------- --}}
    {{-- Already connected                                                 --}}
    {{-- ---------------------------------------------------------------- --}}
    @if (($stage ?? '') === 'active')
        <div class="alert alert-success small">
            <i class="bi bi-shield-check me-1"></i>
            <strong>Connected.</strong>
            Sign-in now asks for the 6-digit code from your authenticator app.
        </div>

        <p class="text-muted-soft small mb-4">
            Your app generates the code on your phone, so it works with no signal and
            no email. The code refreshes every 30 seconds.
        </p>

        <form method="POST" action="{{ route('two-factor.remove') }}"
              onsubmit="return confirm('Remove the authenticator app? Sign-in codes will be emailed instead.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger w-100 py-2">
                <i class="bi bi-phone me-1"></i> Remove authenticator app
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('profile.edit') }}" class="btn btn-link btn-sm link-secondary p-0">
                <i class="bi bi-arrow-left me-1"></i> Back to profile
            </a>
        </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Step 1: scan the QR code                                          --}}
    {{-- ---------------------------------------------------------------- --}}
    @else
        <p class="text-muted-soft small mb-4">
            Install <strong>Google Authenticator</strong> (or Authy, 1Password, Microsoft
            Authenticator), then scan this code with your phone's camera.
        </p>

        <div class="text-center mb-3">
            <div class="d-inline-block bg-white p-2 rounded border" style="line-height:0">
                {!! $qrSvg !!}
            </div>
        </div>

        @if ($secret)
            <div class="mb-4">
                <label class="form-label small fw-semibold">Can't scan? Type this key instead</label>
                <input type="text" class="form-control font-monospace text-center"
                       value="{{ $secret }}" readonly
                       onclick="this.select();">
                <div class="form-text small">
                    Add it manually in your app under "Enter a setup key".
                </div>
            </div>
        @endif

        <hr class="my-4">

        {{-- Step 2: prove you can read the code --}}
        <form method="POST" action="{{ route('two-factor.confirm') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-semibold" for="code">
                    3. Enter the 6-digit code your app is showing
                </label>
                <input type="text" id="code" name="code"
                       class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                       inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       autocomplete="one-time-code" placeholder="••••••" autofocus required>
                @error('code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-charcoal w-100 py-2">
                <i class="bi bi-check2-circle me-1"></i> Connect authenticator app
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="{{ route('profile.edit') }}" class="btn btn-link btn-sm link-secondary p-0">
                <i class="bi bi-arrow-left me-1"></i> Cancel, keep using emailed codes
            </a>
        </div>
    @endif
@endsection
