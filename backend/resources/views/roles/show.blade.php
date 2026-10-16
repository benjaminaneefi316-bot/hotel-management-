@extends('layouts.app')

@section('title', $role->display_name ?? ucfirst($role->name))
@section('eyebrow', 'Administration / Roles')
@section('heading', $role->display_name ?? ucfirst($role->name))

@section('actions')
    <a href="{{ route('roles.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back to roles
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5 mb-1">{{ $role->display_name ?? ucfirst($role->name) }}</h2>
                    <p class="text-muted-soft small mb-3">
                        {{ $role->description ?? 'No description provided.' }}
                    </p>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted-soft">Key</dt>
                        <dd class="col-7"><code>{{ $role->name }}</code></dd>
                        <dt class="col-5 text-muted-soft">System role</dt>
                        <dd class="col-7">{{ $role->is_system ? 'Yes' : 'No' }}</dd>
                        <dt class="col-5 text-muted-soft">Users</dt>
                        <dd class="col-7">{{ $role->users->count() }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">Granted abilities ({{ count($abilities) }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($abilities as $ability)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span>{{ str_replace('-', ' ', ucfirst($ability)) }}</span>
                            <code class="ms-auto small text-muted-soft">{{ $ability }}</code>
                        </li>
                    @empty
                        <li class="list-group-item">
                            <span class="text-muted-soft">This role has no abilities.</span>
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card table-card">
                <div class="card-header bg-white fw-semibold">Users with this role</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($role->users as $user)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar" style="width:32px;height:32px;font-size:.7rem;">{{ $user->initials() }}</span>
                                            <div class="lh-sm">
                                                <div class="fw-semibold">{{ $user->name }}</div>
                                                <div class="small text-muted-soft">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-muted-soft">{{ $user->username }}</td>
                                    <td>@include('partials.status-badge', ['value' => $user->status])</td>
                                    <td class="text-end">
                                        <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-soft" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        @include('partials.empty-state', [
                                            'icon' => 'bi-people',
                                            'title' => 'No users',
                                            'message' => 'No user currently holds this role.',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
