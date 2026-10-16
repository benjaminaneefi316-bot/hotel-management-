<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SettingsUpdateRequest;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): View
    {
        return view('settings.index', [
            'groups' => $this->settings->all()->groupBy('group'),
            'eligible' => $this->settings->eligible()->pluck('key')->all(),
        ]);
    }

    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $submitted = $request->validated()['settings'] ?? [];

        // Only existing keys (or whitelisted keys from config/restore-points.php)
        // may be written, so the form can never create arbitrary settings.
        $existing = $this->settings->all()->pluck('key')->all();
        $eligible = $this->settings->eligible()->pluck('key')->all();

        $saved = 0;

        foreach ($submitted as $key => $value) {
            $key = (string) $key;

            if (! in_array($key, $existing, true) && ! in_array($key, $eligible, true)) {
                continue;
            }

            $this->settings->set($key, $value === '' ? null : $value, $request->user());
            $saved++;
        }

        return redirect()->route('settings.index')->with('status', $saved.' setting(s) saved.');
    }
}
