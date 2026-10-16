@extends('layouts.app')

@section('title', 'Archived users')
@section('eyebrow', 'Administration')
@section('heading', 'Archived users')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('archived-users.index') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label for="search" class="form-label small text-muted-soft">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="{{ request('search') }}" placeholder="Name, username or email">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-charcoal" type="submit"><i class="bi bi-search me-1"></i> Search</button>
                    <a href="{{ route('archived-users.index') }}" class="btn btn-soft" title="Reset">
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
                        <th>Roles at archive</th>
                        <th>Archived</th>
                        <th>Reason</th>
                        <th>State</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($archives as $archive)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $archive->name }}</div>
                                <div class="small text-muted-soft">{{ $archive->email }}</div>
                                <div class="small text-muted-soft">&#64;{{ $archive->username }}</div>
                            </td>
                            <td>
                                @forelse ($archive->roleNames() as $name)
                                    <span class="badge badge-soft badge-soft-gold">{{ ucfirst($name) }}</span>
                                @empty
                                    <span class="text-muted-soft small">—</span>
                                @endforelse
                            </td>
                            <td class="small">
                                <div>{{ $archive->archived_at?->format('d M Y H:i') ?? '—' }}</div>
                                <div class="text-muted-soft">by {{ $archive->archivedBy?->name ?? '—' }}</div>
                            </td>
                            <td class="small text-muted-soft">{{ $archive->reason ?: '—' }}</td>
                            <td>
                                @if ($archive->isRestored())
                                    <span class="badge badge-soft badge-soft-success">Restored</span>
                                    <div class="small text-muted-soft mt-1">{{ $archive->restored_at?->format('d M Y') }}</div>
                                @else
                                    <span class="badge badge-soft badge-soft-danger">Archived</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if (! $archive->isRestored())
                                    <form method="POST" action="{{ route('archived-users.restore', $archive) }}"
                                          onsubmit="return confirm('Restore this account and re-activate it?')">
                                        @csrf
                                        <button class="btn btn-sm btn-gold" type="submit">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted-soft small">No action</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-archive',
                                    'title' => 'No archived users',
                                    'message' => 'Accounts you archive will appear here and can be restored later.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $archives->links() }}</div>
@endsection
