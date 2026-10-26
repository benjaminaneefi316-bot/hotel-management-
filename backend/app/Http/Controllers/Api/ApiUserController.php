<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * User administration from the mobile client (administrators only).
 *
 * Mirrors the website's UserController rules exactly, including the safety
 * rails: the last active administrator can never be deactivated, and 2FA can
 * only be switched on for someone who has already enrolled an authenticator.
 */
final class ApiUserController extends ApiController
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));

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
            ->paginate($perPage);

        return $this->ok($this->paginated($users, fn (User $user): array => $this->userRow($user)));
    }

    public function show(User $user): JsonResponse
    {
        return $this->ok(['data' => $this->userRow($user->load('roles'))]);
    }

    /** Available roles plus their plain-language descriptions. */
    public function roles(): JsonResponse
    {
        return $this->ok([
            'data' => array_map(static fn (RoleName $role): array => [
                'name' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ], RoleName::cases()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleName::names())],
        ]);

        $user = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            // New accounts enrol themselves; see UserController::store().
            'two_factor_enabled' => false,
        ]);

        $user->setPassword($data['password']);
        $user->save();

        try {
            $this->roles->syncRoles($user, $data['roles'], $request->user());
        } catch (BusinessRuleException $e) {
            $user->delete();

            return $this->fail($e->getMessage(), 422, 'role_failed');
        }

        $this->audit->success(
            action: 'user.create',
            eventType: 'admin',
            description: sprintf('Created user %s from the app with role(s) [%s].', $user->username, implode(', ', $data['roles'])),
            subject: $user,
            newValues: ['username' => $user->username, 'roles' => $data['roles']],
        );

        return $this->ok(['data' => $this->userRow($user->fresh('roles')), 'message' => 'User account created.'], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->getKey())],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->getKey())],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])],
        ]);

        if ($user->isAdmin() && $data['status'] !== UserStatus::Active->value) {
            if ($user->is($request->user())) {
                return $this->fail('You cannot deactivate the account you are signed in with.', 422, 'self');
            }

            try {
                $this->roles->assertActiveAdminRemains(excluding: $user);
            } catch (BusinessRuleException $e) {
                return $this->fail($e->getMessage(), 422, 'last_admin');
            }
        }

        $before = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
        ];

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
        ])->save();

        if ($user->status !== UserStatus::Active) {
            $user->tokens()->delete();
        }

        $this->audit->success(
            action: 'user.update',
            eventType: 'admin',
            description: sprintf('Updated user %s from the app.', $user->username),
            subject: $user,
            oldValues: $before,
            newValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status->value,
            ],
        );

        return $this->ok(['data' => $this->userRow($user->fresh('roles')), 'message' => 'User account updated.']);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(RoleName::names())],
        ]);

        try {
            $this->roles->syncRoles($user, $data['roles'], $request->user());
        } catch (BusinessRuleException $e) {
            return $this->fail($e->getMessage(), 422, 'role_failed');
        }

        return $this->ok(['data' => $this->userRow($user->fresh('roles')), 'message' => 'Roles updated.']);
    }

    public function activate(User $user): JsonResponse
    {
        $user->forceFill(['status' => UserStatus::Active])->save();

        $this->audit->success(
            action: 'user.activate',
            eventType: 'admin',
            description: sprintf('Activated user %s from the app.', $user->username),
            subject: $user,
            newValues: ['status' => UserStatus::Active->value],
        );

        return $this->ok(['data' => $this->userRow($user->fresh('roles')), 'message' => 'User account activated.']);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->is($user)) {
            return $this->fail('You cannot deactivate the account you are signed in with.', 422, 'self');
        }

        if ($user->isAdmin()) {
            try {
                $this->roles->assertActiveAdminRemains(excluding: $user);
            } catch (BusinessRuleException $e) {
                return $this->fail($e->getMessage(), 422, 'last_admin');
            }
        }

        $user->forceFill(['status' => UserStatus::Inactive])->save();
        $user->tokens()->delete();

        $this->audit->success(
            action: 'user.deactivate',
            eventType: 'admin',
            description: sprintf('Deactivated user %s from the app.', $user->username),
            subject: $user,
            newValues: ['status' => UserStatus::Inactive->value],
        );

        return $this->ok(['data' => $this->userRow($user->fresh('roles')), 'message' => 'User account deactivated.']);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->setPassword($data['password']);
        $user->save();
        $user->tokens()->delete();

        $this->audit->success(
            action: 'user.password.reset',
            eventType: 'admin',
            description: sprintf('Reset the password for user %s from the app.', $user->username),
            subject: $user,
        );

        return $this->ok(['message' => 'Password reset. The user has been signed out everywhere.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'roles' => $user->roleNames(),
            'role_labels' => array_map(
                static fn (string $name): string => RoleName::tryFrom($name)?->label() ?? $name,
                $user->roleNames(),
            ),
            'two_factor_enabled' => $user->usesAuthenticatorApp(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
