@extends('layouts.app')

@section('title', 'Restore point · '.$snapshot->name)
@section('eyebrow', 'System / Restore points')
@section('heading', $snapshot->name)

@section('actions')
    <a href="{{ route('restore-points.preview', $snapshot) }}" class="btn btn-soft">
        <i class="bi bi-arrow-left-right me-1"></i> Preview changes
    </a>
    <a href="{{ route('restore-points.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    @error('confirmation')
        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2"></i>{{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Snapshot</div>
                <div class="card-body">
                    <h2 class="h5 mb-1">{{ $snapshot->name }}</h2>
                    @if ($snapshot->description)
                        <p class="text-muted-soft small">{{ $snapshot->description }}</p>
                    @endif

                    <dl class="row small mb-0">
                        <dt class="col-6 text-muted-soft">Items</dt>
                        <dd class="col-6">{{ $snapshot->item_count }}</dd>

                        <dt class="col-6 text-muted-soft">Role sets</dt>
                        <dd class="col-6">{{ $snapshot->includes_role_assignments ? 'Included' : 'Not included' }}</dd>

                        <dt class="col-6 text-muted-soft">Schema</dt>
                        <dd class="col-6">{{ $snapshot->schema_version }}</dd>

                        <dt class="col-6 text-muted-soft">Captured by</dt>
                        <dd class="col-6">{{ $snapshot->creator?->name ?? 'system' }}</dd>

                        <dt class="col-6 text-muted-soft">Captured at</dt>
                        <dd class="col-6">{{ $snapshot->captured_at?->format('d M Y H:i') }}</dd>

                        <dt class="col-6 text-muted-soft">Last restored</dt>
                        <dd class="col-6">
                            {{ $snapshot->restore_count > 0 ? $snapshot->last_restored_at?->format('d M Y H:i').' ('.$snapshot->restore_count.'×)' : 'Never' }}
                        </dd>

                        <dt class="col-6 text-muted-soft">Checksum</dt>
                        <dd class="col-6 text-break"><code class="small">{{ \Illuminate\Support\Str::limit($snapshot->checksum, 16) }}</code></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Captured items</span>
                    <a href="{{ route('restore-points.preview', $snapshot) }}" class="small">Compare with current</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th class="ps-3">Type</th><th>Key</th><th>Captured value</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($snapshot->items as $item)
                                <tr>
                                    <td class="ps-3">
                                        @if ($item->item_type === \App\Models\ConfigurationSnapshotItem::TYPE_ROLE_ASSIGNMENT)
                                            <span class="badge badge-soft badge-soft-info">Role</span>
                                        @else
                                            <span class="badge badge-soft badge-soft-gold">Setting</span>
                                        @endif
                                    </td>
                                    <td class="small"><code>{{ $item->item_key }}</code></td>
                                    <td class="small text-break">{{ \Illuminate\Support\Str::limit((string) $item->captured_value, 60) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted-soft py-4">This snapshot captured no items.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-3 border-danger border-opacity-25">
                <div class="card-body">
                    <h2 class="h6 text-danger"><i class="bi bi-arrow-counterclockwise me-1"></i> Restore this configuration</h2>
                    <p class="small text-muted-soft">
                        Restoring writes the captured settings (and, if included, role assignments) back. It
                        <strong>never</strong> touches guests, bookings, payments or audit history. Type the snapshot
                        name to confirm.
                    </p>

                    <form method="POST" action="{{ route('restore-points.restore', $snapshot) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-7">
                            <label for="confirmation" class="form-label small">Type <code>{{ $snapshot->name }}</code> to confirm</label>
                            <input type="text" id="confirmation" name="confirmation" class="form-control @error('confirmation') is-invalid @enderror"
                                   autocomplete="off" required>
                            @error('confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Restore configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Delete this restore point</div>
                        <div class="small text-muted-soft">The captured configuration is removed; nothing is restored.</div>
                    </div>
                    <form method="POST" action="{{ route('restore-points.destroy', $snapshot) }}"
                          data-confirm="Delete restore point &quot;{{ $snapshot->name }}&quot;?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
