@extends('layouts.app')

@section('title', $user->name)
@section('eyebrow', 'Administration / Users')
@section('heading', $user->name)

@section('actions')
    <a href="{{ route('users.edit', $user) }}" class="btn btn-soft">
        <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <a href="{{ route('users.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    @error('business_rule')
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i>{{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <span class="avatar avatar-lg mx-auto mb-3">{{ $user->initials() }}</span>
                    <h2 class="h5 mb-0">{{ $user->name }}</h2>
                    <div class="text-muted-soft small mb-2">{{ '@'.$user->username }}</div>
                    <div>@include('partials.status-badge', ['value' => $user->status])</div>

                    <hr>

                    <dl class="row text-start small mb-0">
                        <dt class="col-5 text-muted-soft">Email</dt>
                        <dd class="col-7">{{ $user->email }}</dd>

                        <dt class="col-5 text-muted-soft">Two-factor</dt>
                        <dd class="col-7">{{ $user->two_factor_enabled ? 'Enabled' : 'Disabled' }}</dd>

                        <dt class="col-5 text-muted-soft">Created</dt>
                        <dd class="col-7">{{ $user->created_at?->format('d M Y') ?? '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Last login</dt>
                        <dd class="col-7">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <div class="card kpi-card h-100">
                        <div class="card-body">
                            <div class="kpi-label">Bookings created</div>
                            <div class="kpi-value">{{ $bookingsCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card kpi-card h-100">
                        <div class="card-body">
                            <div class="kpi-label">Guest profiles</div>
                            <div class="kpi-value">{{ $guestProfiles }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card kpi-card h-100">
                        <div class="card-body">
                            <div class="kpi-label">Roles</div>
                            <div class="kpi-value">{{ $user->roles->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Roles</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.roles.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-2 mb-3">
                            @php $selectedRoles = old('roles', $user->roleNames()); @endphp
                            @foreach ($roles as $role)
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]"
                                               value="{{ $role->name }}" id="role-{{ $role->id }}"
                                               @checked(in_array($role->name, $selectedRoles, true))>
                                        <label class="form-check-label" for="role-{{ $role->id }}">
                                            {{ $role->display_name ?? ucfirst($role->name) }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('roles')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <button class="btn btn-gold btn-sm" type="submit"
                                onclick="return confirm('Update the roles for this user?')">
                            <i class="bi bi-shield-check me-1"></i> Update roles
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Reset password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('users.reset-password', $user) }}">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md-5">
                                <label for="password" class="form-label small">New password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                       id="password" name="password" autocomplete="new-password" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label for="password_confirmation" class="form-label small">Confirm password</label>
                                <input type="password" class="form-control" id="password_confirmation"
                                       name="password_confirmation" autocomplete="new-password" required>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-soft w-100" type="submit"
                                        onclick="return confirm('Reset this user\'s password?')">Reset</button>
                            </div>
                        </div>
                        <div class="form-text">At least 12 characters with mixed case, numbers and symbols.</div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">Account actions</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @if ($user->status !== \App\Enums\UserStatus::Active)
                        <form method="POST" action="{{ route('users.activate', $user) }}">
                            @csrf
                            <button class="btn btn-gold" type="submit">
                                <i class="bi bi-check-circle me-1"></i> Activate account
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('users.deactivate', $user) }}"
                              onsubmit="return confirm('Deactivate this account? The user will be signed out.')">
                            @csrf
                            <button class="btn btn-soft" type="submit">
                                <i class="bi bi-pause-circle me-1"></i> Deactivate account
                            </button>
                        </form>
                    @endif

                    @if ($user->status !== \App\Enums\UserStatus::Archived)
                        <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse"
                                data-bs-target="#archive-form" aria-expanded="false" aria-controls="archive-form">
                            <i class="bi bi-archive me-1"></i> Archive account
                        </button>
                    @endif
                </div>

                @if ($user->status !== \App\Enums\UserStatus::Archived)
                    <div class="collapse border-top" id="archive-form">
                        <div class="card-body">
                            <form method="POST" action="{{ route('users.archive', $user) }}"
                                  onsubmit="return confirm('Archive this account? The user will no longer be able to sign in.')">
                                @csrf
                                <label for="reason" class="form-label small">Reason (optional)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="reason" name="reason" maxlength="255"
                                           value="{{ old('reason') }}" placeholder="Why is this account being archived?">
                                    <button class="btn btn-danger" type="submit">Archive</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
