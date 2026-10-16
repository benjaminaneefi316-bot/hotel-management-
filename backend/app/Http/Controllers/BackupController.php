<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BackupLog;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('backups.index', [
            'backups' => BackupLog::query()->with('triggeredBy')->latest()->paginate(15),
            'driver' => (string) config('backup.driver', 'local'),
            'retentionDays' => (int) config('backup.retention_days', 30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-backups');

        $log = $this->backups->run($request->user());

        return redirect()->route('backups.show', $log)->with(
            $log->isSuccess() ? 'status' : 'error',
            $log->isSuccess()
                ? 'Backup '.$log->reference.' completed and archived.'
                : 'Backup '.$log->reference.' failed: '.($log->error ?? 'unknown error'),
        );
    }

    public function show(BackupLog $backup): View
    {
        $backup->load('triggeredBy');

        return view('backups.show', ['backup' => $backup]);
    }

    public function verify(BackupLog $backup): RedirectResponse
    {
        $this->authorize('manage-backups');

        $ok = $this->backups->verifyExisting($backup);

        return back()->with(
            $ok ? 'status' : 'error',
            $ok ? 'Archive re-verified successfully.' : 'Verification failed — see the detail on the record.',
        );
    }

    public function destroy(BackupLog $backup): RedirectResponse
    {
        $this->authorize('manage-backups');

        $backup->delete();

        return redirect()->route('backups.index')->with('status', 'Backup record removed.');
    }

    public function prune(): RedirectResponse
    {
        $this->authorize('manage-backups');

        $removed = $this->backups->prune();

        return back()->with('status', $removed.' expired archive(s) pruned.');
    }
}
