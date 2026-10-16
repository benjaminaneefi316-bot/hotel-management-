@extends('layouts.app')

@section('title', 'Backup '.$backup->reference)
@section('eyebrow', 'System / Backups')
@section('heading', 'Backup '.$backup->reference)

@section('actions')
    <a href="{{ route('backups.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    @if ($backup->isFailed() && $backup->error)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-octagon me-2"></i>
            <strong>Backup failed.</strong> {{ $backup->error }}
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Archive details</span>
                    @if ($backup->isSuccess())
                        <span class="badge badge-soft badge-soft-success">Success</span>
                    @elseif ($backup->isFailed())
                        <span class="badge badge-soft badge-soft-danger">Failed</span>
                    @else
                        <span class="badge badge-soft badge-soft-warning">Running</span>
                    @endif
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-muted-soft">Reference</dt>
                        <dd class="col-sm-8"><code>{{ $backup->reference }}</code></dd>

                        <dt class="col-sm-4 text-muted-soft">Filename</dt>
                        <dd class="col-sm-8">{{ $backup->filename }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Driver</dt>
                        <dd class="col-sm-8 text-uppercase">{{ $backup->driver }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Destination</dt>
                        <dd class="col-sm-8">
                            <div class="text-break">{{ $backup->path ?: '—' }}</div>
                            @if ($backup->bucket)
                                <div class="text-muted-soft">bucket: {{ $backup->bucket }}</div>
                            @endif
                        </dd>

                        <dt class="col-sm-4 text-muted-soft">Size</dt>
                        <dd class="col-sm-8">{{ $backup->humanSize() }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Encrypted</dt>
                        <dd class="col-sm-8">{{ $backup->is_encrypted ? 'Yes (AES-256-GCM)' : 'No' }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Checksum (SHA-256)</dt>
                        <dd class="col-sm-8 text-break"><code>{{ $backup->checksum ?: '—' }}</code></dd>

                        <dt class="col-sm-4 text-muted-soft">Started</dt>
                        <dd class="col-sm-8">{{ $backup->started_at?->format('d M Y H:i:s') ?? '—' }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Finished</dt>
                        <dd class="col-sm-8">{{ $backup->finished_at?->format('d M Y H:i:s') ?? '—' }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Duration</dt>
                        <dd class="col-sm-8">{{ $backup->duration_ms !== null ? number_format((int) $backup->duration_ms).' ms' : '—' }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Triggered by</dt>
                        <dd class="col-sm-8">{{ $backup->triggeredBy?->name ?? 'system / scheduler' }}</dd>

                        <dt class="col-sm-4 text-muted-soft">Retention</dt>
                        <dd class="col-sm-8">{{ $backup->retention_days }} day(s)</dd>

                        <dt class="col-sm-4 text-muted-soft">Pruned</dt>
                        <dd class="col-sm-8">
                            {{ $backup->is_pruned ? ($backup->pruned_at?->format('d M Y H:i') ?? 'Yes') : 'No' }}
                        </dd>

                        @if ($backup->alerted_at)
                            <dt class="col-sm-4 text-muted-soft">Alert sent</dt>
                            <dd class="col-sm-8">{{ $backup->alerted_at->format('d M Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white fw-semibold">Verification</div>
                <div class="card-body">
                    @if ($backup->verification_status === 'verified')
                        <div class="d-flex align-items-center gap-2 text-success mb-2">
                            <i class="bi bi-patch-check-fill"></i>
                            <span class="fw-semibold">Archive verified</span>
                        </div>
                    @elseif ($backup->verification_status)
                        <div class="d-flex align-items-center gap-2 text-danger mb-2">
                            <i class="bi bi-x-octagon-fill"></i>
                            <span class="fw-semibold">{{ \Illuminate\Support\Str::headline($backup->verification_status) }}</span>
                        </div>
                    @else
                        <p class="text-muted-soft small">This archive has not been verified yet.</p>
                    @endif

                    @if ($backup->verification_detail)
                        <p class="small text-muted-soft text-break mb-3">{{ $backup->verification_detail }}</p>
                    @endif

                    @can('manage-backups')
                        <form method="POST" action="{{ route('backups.verify', $backup) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-charcoal w-100">
                                <i class="bi bi-shield-check me-1"></i> Re-verify archive
                            </button>
                        </form>
                    @endcan
                </div>
            </div>

            @can('manage-backups')
                <div class="card mt-3">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">Remove record</div>
                            <div class="small text-muted-soft">Deletes this backup log entry.</div>
                        </div>
                        <form method="POST" action="{{ route('backups.destroy', $backup) }}"
                              data-confirm="Delete backup record {{ $backup->reference }}?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
