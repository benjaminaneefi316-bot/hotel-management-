@extends('layouts.app')

@section('title', 'Users')
@section('eyebrow', 'Administration')
@section('heading', 'User accounts')

@section('actions')
    <a href="{{ route('users.create') }}" class="btn btn-gold">
        <i class="bi bi-person-plus me-1"></i> New user
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label small text-muted-soft">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="{{ request('search') }}" placeholder="Name, username or email">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label small text-muted-soft">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>
                                {{ $role->display_name ?? ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small text-muted-soft">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (\App\Enums\UserStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-charcoal w-100" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="{{ route('users.index') }}" class="btn btn-soft" title="Reset filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar" style="width:34px;height:34px;font-size:.72rem;">{{ $user->initials() }}</span>
                                    <div class="lh-sm">
                                        <div class="fw-semibold">{{ $user->name }}</div>
                                        <div class="small text-muted-soft">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted-soft">{{ $user->username }}</td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge badge-soft badge-soft-gold">{{ $role->display_name ?? ucfirst($role->name) }}</span>
                                @empty
                                    <span class="text-muted-soft small">No role</span>
                                @endforelse
                            </td>
                            <td>@include('partials.status-badge', ['value' => $user->status])</td>
                            <td class="small text-muted-soft">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-soft" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-soft" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-person-x',
                                    'title' => 'No users found',
                                    'message' => 'Adjust your filters or create a new user account.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
