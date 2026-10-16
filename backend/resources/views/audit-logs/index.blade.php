@extends('layouts.app')

@section('title', 'Audit logs')
@section('eyebrow', 'System')
@section('heading', 'Audit logs')

@section('content')
    <div class="alert alert-light border d-flex align-items-start">
        <i class="bi bi-journal-text text-gold me-2 mt-1"></i>
        <div class="small mb-0">
            Every security-relevant action is recorded here. Sensitive values (passwords, salts, tokens, OTPs and keys)
            are redacted before they are written, so the trail never becomes a source of secrets.
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="action" class="form-label small text-muted-soft">Action contains</label>
                    <input type="text" id="action" name="action" class="form-control"
                           value="{{ request('action') }}" placeholder="e.g. login">
                </div>
                <div class="col-md-3">
                    <label for="event_type" class="form-label small text-muted-soft">Event type</label>
                    <select id="event_type" name="event_type" class="form-select">
                        <option value="">All events</option>
                        @foreach ($eventTypes as $type)
                            <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="outcome" class="form-label small text-muted-soft">Outcome</label>
                    <select id="outcome" name="outcome" class="form-select">
                        <option value="">Any</option>
                        @foreach ($outcomes as $outcome)
                            <option value="{{ $outcome->value }}" @selected(request('outcome') === $outcome->value)>{{ $outcome->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="from" class="form-label small text-muted-soft">From</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ request('from') }}">
                </div>
                <div class="col-md-2">
                    <label for="to" class="form-label small text-muted-soft">To</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ request('to') }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-charcoal"><i class="bi bi-search me-1"></i> Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-soft">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Event</th>
                        <th>Outcome</th>
                        <th>IP</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="ps-3 small text-muted-soft text-nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $log->user?->name ?? $log->actor_username ?? 'system' }}</div>
                                @if ($log->actor_username)
                                    <div class="small text-muted-soft">{{ '@'.$log->actor_username }}</div>
                                @endif
                            </td>
                            <td class="small"><code>{{ $log->action }}</code></td>
                            <td><span class="badge badge-soft badge-soft-neutral">{{ $log->eventLabel() }}</span></td>
                            <td>
                                @if ($log->outcome === \App\Enums\AuditOutcome::Success)
                                    <span class="badge badge-soft badge-soft-success">Success</span>
                                @elseif ($log->outcome === \App\Enums\AuditOutcome::Denied)
                                    <span class="badge badge-soft badge-soft-danger">Denied</span>
                                @else
                                    <span class="badge badge-soft badge-soft-warning">{{ $log->outcome?->label() ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="small text-muted-soft">{{ $log->ip_address ?: '—' }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('audit-logs.show', $log) }}" class="btn btn-sm btn-soft">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('partials.empty-state', [
                                    'icon' => 'bi-journal-text',
                                    'title' => 'No audit entries',
                                    'message' => 'No activity matches the current filters.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted-soft">
                    Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}
                </span>
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
