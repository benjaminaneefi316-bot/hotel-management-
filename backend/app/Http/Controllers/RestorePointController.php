<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RestoreConfigurationRequest;
use App\Http\Requests\StoreRestorePointRequest;
use App\Models\ConfigurationSnapshot;
use App\Services\ConfigurationSnapshotService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestorePointController extends Controller
{
    public function __construct(
        private readonly ConfigurationSnapshotService $snapshots,
        private readonly SettingsService $settings,
    ) {}

    public function index(): View
    {
        return view('restore-points.index', [
            'snapshots' => ConfigurationSnapshot::query()
                ->with('creator')
                ->latest('captured_at')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('restore-points.create', [
            'settings' => $this->settings->eligible(),
            'includeRolesByDefault' => (bool) config('restore-points.include_role_assignments_by_default', false),
        ]);
    }

    public function store(StoreRestorePointRequest $request): RedirectResponse
    {
        $this->authorize('manage-restore-points');

        $data = $request->validated();

        $snapshot = $this->snapshots->create(
            $data['name'],
            $data['description'] ?? null,
            $request->boolean('include_role_assignments'),
            $request->user(),
            array_values($data['settings'] ?? []),
        );

        return redirect()->route('restore-points.show', $snapshot)
            ->with('status', 'Restore point "'.$snapshot->name.'" captured.');
    }

    public function show(ConfigurationSnapshot $snapshot): View
    {
        $snapshot->load(['items', 'creator']);

        return view('restore-points.show', ['snapshot' => $snapshot]);
    }

    public function preview(ConfigurationSnapshot $snapshot): View
    {
        $snapshot->loadMissing(['items', 'creator']);

        return view('restore-points.preview', [
            'snapshot' => $snapshot,
            'rows' => $this->snapshots->preview($snapshot),
        ]);
    }

    public function restore(RestoreConfigurationRequest $request, ConfigurationSnapshot $snapshot): RedirectResponse
    {
        $this->authorize('manage-restore-points');

        $applied = $this->snapshots->restore($snapshot, $request->user());

        return redirect()->route('restore-points.show', $snapshot)
            ->with('status', count($applied).' configuration item(s) restored.');
    }

    public function destroy(ConfigurationSnapshot $snapshot): RedirectResponse
    {
        $this->authorize('manage-restore-points');

        $snapshot->delete();

        return redirect()->route('restore-points.index')->with('status', 'Restore point deleted.');
    }
}
