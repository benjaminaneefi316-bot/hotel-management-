@extends('layouts.app')

@section('title', 'Preview restore — '.$snapshot->name)
@section('eyebrow', 'System / Restore points')
@section('heading', 'Preview changes')

@section('actions')
    <a href="{{ route('restore-points.show', $snapshot) }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back to restore point
    </a>
@endsection

@section('content')
    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-arrow-left-right text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            Comparing the captured values in <strong>{{ $snapshot->name }}</strong> with the current configuration.
            Rows marked <span class="badge badge-soft badge-soft-gold">Changed</span> would be overwritten by a restore.
            Business data (guests, bookings, payments) is not part of this comparison.
        </div>
    </div>

    <div class="card table-card mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Type</th>
                        <th>Item</th>
                        <th>Current</th>
                        <th>Captured</th>
                        <th class="pe-3 text-end">State</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ $row['changed'] ? 'table-warning' : '' }}">
                            <td class="ps-3">
                                @if ($row['type'] === 'Role assignment')
                                    <span class="badge badge-soft badge-soft-info">Role</span>
                                @else
                                    <span class="badge badge-soft badge-soft-gold">Setting</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $row['label'] }}</div>
                                <div class="small text-muted-soft"><code>{{ $row['key'] }}</code></div>
                            </td>
                            <td class="small text-break">{{ $row['current'] === null || $row['current'] === '' ? '—' : $row['current'] }}</td>
                            <td class="small text-break">{{ $row['captured'] === null || $row['captured'] === '' ? '—' : $row['captured'] }}</td>
                            <td class="pe-3 text-end">
                                @if ($row['changed'])
                                    <span class="badge badge-soft badge-soft-gold">Changed</span>
                                @else
                                    <span class="badge badge-soft badge-soft-neutral">Unchanged</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-arrow-left-right',
                                    'title' => 'Nothing to compare',
                                    'message' => 'This restore point captured no items.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($rows !== [])
        <div class="card border-danger border-opacity-25">
            <div class="card-body">
                <h2 class="h6 text-danger"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore this configuration</h2>
                <p class="small text-muted-soft mb-3">
                    Type <code>{{ $snapshot->name }}</code> to confirm. Only whitelisted settings and role assignments
                    are written — never business rows.
                </p>
                <form method="POST" action="{{ route('restore-points.restore', $snapshot) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-7">
                        <label for="confirmation" class="form-label small">Confirmation</label>
                        <input type="text" id="confirmation" name="confirmation" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="col-md-5">
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Restore configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
