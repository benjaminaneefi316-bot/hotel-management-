<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RoleService;
use App\Services\UserArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * User administration (administrators only, enforced by route + controller).
 *
 * Passwords are never set through mass assignment: every write goes through
 * User::setPassword() so the pepper and a fresh per-user salt are always used.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly UserArchiveService $archive,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
            'two_factor_enabled' => ['nullable', 'boolean'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleName::names())],
        ]);

        // A brand-new account has no authenticator app yet, so enabling 2FA
        // here would demand a code the person cannot produce (the fallback is
        // an emailed code, and this deployment has no working mail transport).
        // Two-factor switches itself on when they enrol from their profile.
        if ($request->boolean('two_factor_enabled')) {
            return back()->withInput()->withErrors([
                'two_factor_enabled' => 'New accounts start without two-factor. Create the user first; it switches on automatically once they enrol an authenticator app from Profile → Two-factor.',
            ]);
        }

        $user = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'status' => $data['status'],
            'two_factor_enabled' => false,
        ]);

        $user->setPassword($data['password']);
        $user->save();

        try {
            $this->roles->syncRoles($user, $data['roles'], $request->user());
        } catch (BusinessRuleException $e) {
            $user->delete();

            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit->success(
            action: 'user.create',
            eventType: 'admin',
            description: sprintf('Created user %s with role(s) [%s].', $user->username, implode(', ', $data['roles'])),
            subject: $user,
            newValues: ['username' => $user->username, 'roles' => $data['roles']],
        );

        return redirect()->route('users.show', $user)->with('status', 'User account created.');
    }

    public function show(User $user): View
    {
        $user->load('roles');

        return view('users.show', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'bookingsCount' => $user->bookings()->count(),
            'guestProfiles' => $user->guestProfiles()->count(),
        ]);
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('users.edit', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->getKey())],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->getKey())],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
            'two_factor_enabled' => ['nullable', 'boolean'],
        ]);

        // Deactivating the last active administrator is never allowed.
        if ($data['status'] !== UserStatus::Active->value && $user->isAdmin()) {
            try {
                $this->roles->assertActiveAdminRemains(excluding: $user);
            } catch (BusinessRuleException $e) {
                return back()->withInput()->withErrors(['business_rule' => $e->getMessage()]);
            }
        }

        // Two-factor can only be switched on for someone who has already
        // enrolled an authenticator app; otherwise they would be locked out.
        $hasEnrolledAuthenticator = $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;

        if ($request->boolean('two_factor_enabled') && ! $hasEnrolledAuthenticator) {
            return back()->withInput()->withErrors([
                'two_factor_enabled' => 'This user has not enrolled an authenticator app yet. Ask them to sign in and enrol from Profile → Two-factor; it switches on automatically.',
            ]);
        }

        $before = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status->value,
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
        ];

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'status' => $data['status'],
            'two_factor_enabled' => $request->boolean('two_factor_enabled'),
        ])->save();

        $this->audit->success(
            action: 'user.update',
            eventType: 'admin',
            description: sprintf('Updated user %s.', $user->username),
            subject: $user,
            oldValues: $before,
            newValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'status' => $user->status->value,
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
            ],
        );

        return redirect()->route('users.show', $user)->with('status', 'User account updated.');
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleName::names())],
        ]);

        try {
            $this->roles->syncRoles($user, $data['roles'], $request->user());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['business_rule' => $e->getMessage()]);
        }

        return redirect()->route('users.show', $user)->with('status', 'Roles updated.');
    }

    public function activate(User $user): RedirectResponse
    {
        $user->forceFill(['status' => UserStatus::Active])->save();

        $this->audit->success(
            action: 'user.activate',
            eventType: 'admin',
            description: sprintf('Activated user %s.', $user->username),
            subject: $user,
            newValues: ['status' => UserStatus::Active->value],
        );

        return back()->with('status', 'User account activated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            try {
                $this->roles->assertActiveAdminRemains(excluding: $user);
            } catch (BusinessRuleException $e) {
                return back()->withErrors(['business_rule' => $e->getMessage()]);
            }
        }

        $user->forceFill(['status' => UserStatus::Inactive])->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $this->audit->success(
            action: 'user.deactivate',
            eventType: 'admin',
            description: sprintf('Deactivated user %s.', $user->username),
            subject: $user,
            newValues: ['status' => UserStatus::Inactive->value],
        );

        return back()->with('status', 'User account deactivated.');
    }

    public function archive(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->archive->archive($user, $request->user(), $data['reason'] ?? null);
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['business_rule' => $e->getMessage()]);
        }

        return redirect()->route('users.index')->with('status', 'User account archived.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->setPassword($data['password']);
        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $this->audit->success(
            action: 'user.password.reset',
            eventType: 'admin',
            description: sprintf('Reset the password for user %s.', $user->username),
            subject: $user,
        );

        return back()->with('status', 'Password reset.');
    }
}
