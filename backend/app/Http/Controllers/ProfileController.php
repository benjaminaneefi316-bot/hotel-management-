<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The signed-in user's own profile.
 *
 * A user may edit their own name/username/email and change their own password,
 * but can never change their own roles or status (that is an admin action, and
 * is guarded server-side by the `manage-users` gate).
 */
final class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'recentLogins' => $this->recentActivity($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $before = $user->only(['name', 'username', 'email', 'phone']);

        $user->fill($data)->save();

        $this->audit->success(
            action: 'profile.update',
            eventType: 'data',
            description: sprintf('Updated own profile (%s).', $user->username),
            subject: $user,
            oldValues: $before,
            newValues: $data,
        );

        return back()->with('status', 'Your profile has been updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if (! $user->verifyPassword($validated['current_password'])['ok']) {
            $this->audit->failure(
                action: 'profile.password.failed',
                eventType: 'security',
                description: sprintf('Incorrect current password supplied for %s.', $user->username),
                subject: $user,
            );

            return back()->withErrors(['current_password' => 'Your current password is not correct.']);
        }

        $user->setPassword($validated['password']);
        $user->save();

        $this->audit->success(
            action: 'profile.password.changed',
            eventType: 'security',
            description: sprintf('%s changed their own password.', $user->username),
            subject: $user,
        );

        return back()->with('status', 'Your password has been changed.');
    }

    /**
     * The user's own recent security activity (from the audit trail).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\AuditLog>
     */
    private function recentActivity(Request $request): \Illuminate\Support\Collection
    {
        return \App\Models\AuditLog::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest('id')
            ->limit(8)
            ->get();
    }
}
