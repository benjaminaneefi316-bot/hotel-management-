@php
    $user = $user ?? null;
    $isCreate = $user === null;
    $selectedRoles = old('roles', $user?->roleNames() ?? []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
               value="{{ old('name', $user?->name) }}" maxlength="120" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username"
               value="{{ old('username', $user?->username) }}" maxlength="60" required>
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
               value="{{ old('email', $user?->email) }}" maxlength="190" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach (\App\Enums\UserStatus::cases() as $status)
                @if ($status === \App\Enums\UserStatus::Archived && $isCreate)
                    @continue
                @endif
                <option value="{{ $status->value }}" @selected(old('status', $user?->status?->value ?? 'active') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        @if ($isCreate)
            <div class="alert alert-info mb-0">
                <i class="bi bi-shield-lock me-2"></i>
                Two-factor switches on later, from the user's own profile, once they enrol an
                authenticator app. A brand-new account cannot receive a sign-in code yet, so
                enabling it here would lock them out.
            </div>
        @else
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="two_factor_enabled"
                       name="two_factor_enabled" value="1"
                       @checked((bool) old('two_factor_enabled', $user?->two_factor_enabled ?? false))>
                <label class="form-check-label" for="two_factor_enabled">Require two-factor authentication</label>
            </div>
            @error('two_factor_enabled')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @unless ($user?->two_factor_secret && $user?->two_factor_confirmed_at)
                <div class="form-text">
                    This user has not enrolled an authenticator app yet, so two-factor cannot be
                    switched on here. It turns on automatically when they enrol from their profile.
                </div>
            @endunless
        @endif
    </div>
</div>

@if ($isCreate)
    <hr class="my-4">
    <h2 class="h6 mb-3">Password</h2>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                   name="password" autocomplete="new-password" required>
            <div class="form-text">At least 12 characters with mixed case, numbers and symbols.</div>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                   autocomplete="new-password" required>
        </div>
    </div>

    <hr class="my-4">
    <h2 class="h6 mb-3">Roles <span class="text-danger">*</span></h2>
    <div class="@error('roles')is-invalid @enderror">
        <div class="row g-2">
            @foreach ($roles as $role)
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}"
                               id="role-{{ $role->id }}" @checked(in_array($role->name, $selectedRoles, true))>
                        <label class="form-check-label" for="role-{{ $role->id }}">
                            {{ $role->display_name ?? ucfirst($role->name) }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
@endif

@error('business_rule')
    <div class="alert alert-danger mt-3 mb-0"><i class="bi bi-exclamation-octagon me-2"></i>{{ $message }}</div>
@enderror
