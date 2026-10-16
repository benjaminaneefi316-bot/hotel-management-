<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditOutcome;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.(string) $request->input('action').'%'))
            ->when($request->filled('event_type'), fn ($q) => $q->where('event_type', $request->input('event_type')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->input('outcome')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'outcomes' => AuditOutcome::cases(),
            'eventTypes' => ['auth', 'security', 'data', 'system', 'admin'],
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('user');

        return view('audit-logs.show', ['log' => $auditLog]);
    }
}
