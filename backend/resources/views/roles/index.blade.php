@extends('layouts.app')

@section('title', 'Roles')
@section('eyebrow', 'Administration')
@section('heading', 'Roles &amp; permissions')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ($roles as $role)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h5 mb-0">{{ $role->display_name ?? ucfirst($role->name) }}</h2>
                            <span class="badge badge-soft badge-soft-gold">{{ $role->users_count }} user{{ $role->users_count === 1 ? '' : 's' }}</span>
                        </div>
                        <p class="text-muted-soft small flex-grow-1">
                            {{ $role->description ?? 'No description provided.' }}
                        </p>
                        <a href="{{ route('roles.show', $role) }}" class="btn btn-soft w-100">
                            <i class="bi bi-shield-lock me-1"></i> View permissions
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card table-card">
        <div class="card-header bg-white fw-semibold">Permission matrix</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ability</th>
                        @foreach ($roles as $role)
                            <th class="text-center">{{ $role->display_name ?? ucfirst($role->name) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($abilities as $ability => $allowedRoles)
                        <tr>
                            <td class="fw-semibold">{{ str_replace('-', ' ', ucfirst($ability)) }}</td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    @if (in_array($role->name, $allowedRoles, true))
                                        <i class="bi bi-check-circle-fill text-success" title="Allowed"></i>
                                    @else
                                        <i class="bi bi-dash text-muted-soft" title="Not allowed"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $roles->count() + 1 }}">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-shield-slash',
                                    'title' => 'No abilities configured',
                                    'message' => 'Add entries to config/abilities.php to define the permission matrix.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
