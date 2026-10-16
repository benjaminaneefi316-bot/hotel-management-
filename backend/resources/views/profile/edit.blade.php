@extends('layouts.app')

@section('title', 'My profile')
@section('eyebrow', 'Account')
@section('heading', 'My profile')

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            {{-- Profile details --}}
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-1">Profile details</h2>
                    <p class="text-muted-soft small mb-4">
                        Update the name, username, email address and contact phone on your account.
                    </p>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Full name</label>
                            <input id="name"
                                   name="name"
                                   type="text"
                                   value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   autocomplete="name"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input id="username"
                                   name="username"
                                   type="text"
                                   value="{{ old('username', $user->username) }}"
                                   class="form-control @error('username') is-invalid @enderror"
                                   autocomplete="username"
                                   required>
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">Email address</label>
                            <input id="email"
                                   name="email"
                                   type="email"
                                   value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email') is-invalid @enderror"
                                   autocomplete="email"
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone number <span class="text-muted-soft">(optional)</span></label>
                            <input id="phone"
                                   name="phone"
                                   type="tel"
                                   value="{{ old('phone', $user->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   autocomplete="tel">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-charcoal">
                            <i class="bi bi-check2 me-1"></i> Save changes
                        </button>
                    </form>
                </div>
            </div>

            {{-- Change password --}}
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-1">Change password</h2>
                    <p class="text-muted-soft small mb-4">
                        Choose a strong password: at least 12 characters with upper and lower case letters,
                        a number and a symbol.
                    </p>

                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input id="current_password"
                                   name="current_password"
                                   type="password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   autocomplete="current-password"
                                   required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New password</label>
                            <input id="password"
                                   name="password"
                                   type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password"
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm new password</label>
                            <input id="password_confirmation"
                                   name="password_confirmation"
                                   type="password"
                                   class="form-control"
                                   autocomplete="new-password"
                                   required>
                        </div>

                        <button type="submit" class="btn btn-charcoal">
                            <i class="bi bi-shield-lock me-1"></i> Update password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            {{-- Security summary (read-only) --}}
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar-lg">{{ $user->initials() }}</span>
                        <div class="min-w-0">
                            <div class="h5 mb-0">{{ $user->name }}</div>
                            <div class="text-muted-soft small text-truncate">{{ $user->email }}</div>
                        </div>
                    </div>

                    <ul class="list-unstyled small d-grid gap-3 mb-0">
                        <li class="d-flex justify-content-between align-items-center gap-3">
                            <span class="text-muted-soft">Username</span>
                            <span class="fw-semibold text-end">{{ $user->username }}</span>
                        </li>

                        <li class="d-flex justify-content-between align-items-center gap-3">
                            <span class="text-muted-soft">Account status</span>
                            @include('partials.status-badge', ['value' => $user->status])
                        </li>

                        <li class="d-flex justify-content-between align-items-center gap-3">
                            <span class="text-muted-soft">Two-factor authentication</span>
                            @if ($user->two_factor_enabled)
                                <span class="badge badge-soft badge-soft-success">
                                    <i class="bi bi-shield-check me-1"></i>Enabled
                                </span>
                            @else
                                <span class="badge badge-soft badge-soft-danger">
                                    <i class="bi bi-shield-exclamation me-1"></i>Disabled
                                </span>
                            @endif
                        </li>

                        <li class="d-flex justify-content-between align-items-start gap-3">
                            <span class="text-muted-soft">Roles</span>
                            <span class="text-end">
                                @forelse ($user->roles as $role)
                                    <span class="badge badge-soft badge-soft-gold">{{ $role->display_name ?: ucfirst($role->name) }}</span>
                                @empty
                                    <span class="text-muted-soft">No role assigned</span>
                                @endforelse
                            </span>
                        </li>

                        <li class="d-flex justify-content-between align-items-center gap-3">
                            <span class="text-muted-soft">Last sign-in</span>
                            <span class="fw-semibold text-end">
                                {{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex gap-3">
                    <div class="icon-circle flex-shrink-0"><i class="bi bi-info-circle"></i></div>
                    <div class="small text-muted-soft">
                        Two-factor authentication is managed by the hotel administrator.
                        Your password is stored as a salted, peppered hash and is never kept in plain text.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
