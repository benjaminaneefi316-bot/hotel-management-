@extends('layouts.app')

@section('title', 'Backups')
@section('eyebrow', 'System')
@section('heading', 'Backups')

@section('actions')
    <form method="POST" action="{{ route('backups.store') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-gold">
            <i class="bi bi-cloud-arrow-up me-1"></i> Run backup now
        </button>
    </form>
    <form method="POST" action="{{ route('backups.prune') }}" class="d-inline"
          data-confirm="Delete every expired backup archive?">
        @csrf
        <button type="submit" class="btn btn-soft">
            <i class="bi bi-trash3 me-1"></i> Prune expired
        </button>
    </form>
@endsection

@section('content')
    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-shield-lock text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            <div>
                Backups are encrypted before leaving the server. Current driver:
                <span class="badge badge-soft badge-soft-gold">{{ strtoupper($driver) }}</span>,
                retention {{ $retentionDays }} day(s).
            </div>
            @if ($driver !== 's3')
                <div class="text-muted-soft mt-1">
                    Local simulation mode is active: no AWS credentials are configured, so archives are written to
                    local disk only. Configure AWS credentials to push to S3.
                </div>
            @endif
        </div>
    </div>

    <div class="card table-card">
        @if ($backups->isEmpty())
            @include('partials.empty-state', [
                'icon' => 'bi-hdd-stack',
                'title' => 'No backups yet',
                'message' => 'Run your first backup to create an encrypted database archive.',
            ])
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Reference</th>
                            <th>Status</th>
                            <th>Driver</th>
                            <th>Size</span></th>
                            <th>Verified</th>
                            <th>Started</td></th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('backups.show', $backup) }}" class="fw-semibold text-dark">{{ $backup->reference }}</a>
                                    <div class="small text-muted-soft">{{ $backup->filename }}</div>
                                </td>
                                <td>
                                    @if ($backup->isSuccess())
                                        <span class="badge badge-soft badge-soft-success">Success</span>
                                    @elseif ($backup->isFailed())
                                        <span class="badge badge-soft badge-soft-danger">Failed</span>
                                    @else
                                        <span class="badge badge-soft badge-soft-warning">Running</span>
                                    @endif
                                    @if ($backup->is_pruned)
                                        <div class="small text-muted-soft mt-1">Pruned</div>
                                    @endif
                                </td>
                                <td class="text-uppercase small">{{ $backup->driver }}</td>
                                <td class="small text-muted-soft">{{ $backup->humanSize() }}</td>
                                <td>
                                    @if ($backup->verification_status === 'verified')
                                        <span class="badge badge-soft badge-soft-success">Verified</span>
                                    @elseif ($backup->verification_status)
                                        <span class="badge badge-soft badge-soft-neutral">{{ \Illuminate\Support\Str::headline($backup->verification_status) }}</span>
                                    @else
                                        <span class="text-muted-soft small">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted-soft">{{ $backup->started_at?->format('d M Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('backups.show', $backup) }}" class="btn btn-sm btn-soft">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted-soft">
                    Showing {{ $backups->firstItem() }}–{{ $backups->lastItem() }} of {{ $backups->total() }}
                </span>
                {{ $backups->links() }}
            </div>
        @endif
    </div>
@endsection
