@extends('layouts.app')

@section('title', 'Audit entry #'.$log->id)
@section('eyebrow', 'System / Audit logs')
@section('heading', $log->action)

@section('actions')
    <a href="{{ route('audit-logs.index') }}" class="btn btn-soft">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">Entry</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted-soft">Action</dt>
                        <dd class="col-7"><code>{{ $log->action }}</code></dd>

                        <dt class="col-5 text-muted-soft">Event type</dt>
                        <dd class="col-7"><span class="badge badge-soft badge-soft-neutral">{{ $log->eventLabel() }}</span></dd>

                        <dt class="col-5 text-muted-soft">Outcome</dt>
                        <dd class="col-7">
                            @if ($log->outcome === \App\Enums\AuditOutcome::Success)
                                <span class="badge badge-soft badge-soft-success">Success</span>
                            @elseif ($log->outcome === \App\Enums\AuditOutcome::Denied)
                                <span class="badge badge-soft badge-soft-danger">Denied</span>
                            @else
                                <span class="badge badge-soft badge-soft-warning">{{ $log->outcome?->label() ?? '—' }}</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted-soft">Actor</dt>
                        <dd class="col-7">
                            {{ $log->user?->name ?? $log->actor_username ?? 'system' }}
                            @if ($log->actor_username)
                                <div class="text-muted-soft">{{ '@'.$log->actor_username }}</div>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted-soft">When</dt>
                        <dd class="col-7">{{ $log->created_at?->format('d M Y H:i:s') }}</dd>

                        <dt class="col-5 text-muted-soft">IP address</dt>
                        <dd class="col-7">{{ $log->ip_address ?: '—' }}</dd>

                        <dt class="col-5 text-muted-soft">Subject</dt>
                        <dd class="col-7 text-break">
                            @if ($log->auditable_type)
                                <code>{{ class_basename($log->auditable_type) }}#{{ $log->auditable_id }}</code>
                            @else
                                —
                            @endif
                        </dd>

                        <dt class="col-5 text-muted-soft">User agent</dt>
                        <dd class="col-7 text-break small text-muted-soft">{{ $log->user_agent ?: '—' }}</dd>
                    </dl>

                    @if ($log->description)
                        <hr>
                        <p class="small mb-0">{{ $log->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Before</div>
                <div class="card-body">
                    @if (! empty($log->old_values))
                        <pre class="small mb-0 text-break">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <p class="text-muted-soft small mb-0">No previous values recorded.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">After</div>
                <div class="card-body">
                    @if (! empty($log->new_values))
                        <pre class="small mb-0 text-break">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @else
                        <p class="text-muted-soft small mb-0">No new values recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
