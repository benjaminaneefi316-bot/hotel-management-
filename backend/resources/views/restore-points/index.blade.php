@extends('layouts.app')

@section('title', 'Restore points')
@section('eyebrow', 'System')
@section('heading', 'Restore points')

@section('actions')
    <a href="{{ route('restore-points.create') }}" class="btn btn-gold"><i class="bi bi-camera me-1"></i> Capture restore point</a>
@endsection

@section('content')
    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-shield-check text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            <strong>Configuration only.</strong> A restore point captures whitelisted application settings (and optionally
            role assignments). It never contains guests, bookings, stays, payments or audit history, and restoring one
            can never roll back business data.
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body p-0">
            @if ($snapshots->isEmpty())
                @include('partials.empty-state', [
                    'icon' => 'bi-clock-history',
                    'title' => 'No restore points yet',
                    'message' => 'Capture the current configuration so you can roll it back later.',
                    'action' => '<a href="'.route('restore-points.create').'" class="btn btn-gold">Capture restore point</a>',
                ])
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Name</th>
                                <th>Items</th>
                                <th>Roles</th>
                                <th>Captured</th>
                                <th>Restored</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshots as $snapshot)
                                <tr>
                                    <td class="ps-3">
                                        <a href="{{ route('restore-points.show', $snapshot) }}" class="fw-semibold text-dark">{{ $snapshot->name }}</a>
                                        @if ($snapshot->description)
                                            <div class="small text-muted-soft">{{ \Illuminate\Support\Str::limit($snapshot->description, 70) }}</div>
                                        @endif
                                        <div class="small text-muted-soft">by {{ $snapshot->creator?->name ?? 'system' }}</div>
                                    </td>
                                    <td>{{ $snapshot->item_count }}</td>
                                    <td>
                                        @if ($snapshot->includes_role_assignments)
                                            <span class="badge badge-soft badge-soft-info">Included</span>
                                        @else
                                            <span class="text-muted-soft small">—</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $snapshot->captured_at?->format('d M Y H:i') }}</td>
                                    <td class="small">
                                        @if ($snapshot->restore_count > 0)
                                            <span class="badge badge-soft badge-soft-gold">{{ $snapshot->restore_count }}×</span>
                                            <div class="text-muted-soft">{{ $snapshot->last_restored_at?->format('d M Y') }}</div>
                                        @else
                                            <span class="text-muted-soft">Never</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="{{ route('restore-points.preview', $snapshot) }}" class="btn btn-sm btn-soft">
                                                <i class="bi bi-arrow-left-right me-1"></i> Preview
                                            </a>
                                            <a href="{{ route('restore-points.show', $snapshot) }}" class="btn btn-sm btn-soft">View</a>
                                            <form method="POST" action="{{ route('restore-points.destroy', $snapshot) }}"
                                                  data-confirm="Delete restore point &quot;{{ $snapshot->name }}&quot;?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-soft text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($snapshots->hasPages())
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted-soft">Showing {{ $snapshots->firstItem() }}–{{ $snapshots->lastItem() }} of {{ $snapshots->total() }}</span>
                {{ $snapshots->links() }}
            </div>
        @endif
    </div>
@endsection
