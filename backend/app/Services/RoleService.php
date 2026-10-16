<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Role-based access control (RBAC) management.
 *
 * Business rules enforced here (server-side, so they cannot be bypassed from
 * the UI):
 *   - only known roles can be assigned
 *   - the LAST ACTIVE ADMINISTRATOR can never be demoted
 *   - every change is written to the audit trail with old + new roles
 */
final class RoleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Replace a user's roles with exactly the given set.
     *
     * @param  array<int, RoleName|string>  $roles
     */
    public function syncRoles(User $user, array $roles, ?User $actor = null): void
    {
        $names = $this->normalise($roles);
        $roleIds = Role::query()->whereIn('name', $names)->pluck('id', 'name');

        // Reject unknown role names rather than silently ignoring them.
        $unknown = array_diff($names, $roleIds->keys()->all());
        if ($unknown !== []) {
            throw new BusinessRuleException('Unknown role(s): '.implode(', ', $unknown));
        }

        $before = $user->roleNames();

        DB::transaction(function () use ($user, $roleIds, $names, $actor, $before): void {
            $pivot = [];
            foreach ($names as $name) {
                $pivot[$roleIds[$name]] = [
                    'assigned_by' => $actor?->id,
                    'assigned_at' => now(),
                ];
            }

            $user->roles()->sync($pivot);

            // Refuse to leave the system without an active administrator.
            if (in_array(RoleName::Admin->value, $before, true)
                && ! in_array(RoleName::Admin->value, $names, true)) {
                $this->assertActiveAdminRemains(excluding: $user);
            }
        });

        $user->unsetRelation('roles');

        $this->audit->success(
            action: 'user.role.change',
            eventType: 'admin',
            description: sprintf('Roles for %s changed from [%s] to [%s].', $user->username, implode(', ', $before) ?: '-', implode(', ', $names) ?: '-'),
            subject: $user,
            oldValues: ['roles' => $before],
            newValues: ['roles' => $names],
        );
    }

    /** Grant a single role without removing the others. */
    public function assignRole(User $user, RoleName $role, ?User $actor = null): void
    {
        $current = $user->roleNames();

        if (in_array($role->value, $current, true)) {
            return;
        }

        $this->syncRoles($user, [...$current, $role->value], $actor);
    }

    /**
     * Ensure at least one ACTIVE administrator exists.
     *
     * @throws BusinessRuleException
     */
    public function assertActiveAdminRemains(?User $excluding = null): void
    {
        $query = User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Admin->value));

        if ($excluding !== null) {
            $query->whereKeyNot($excluding->getKey());
        }

        if ($query->count() < 1) {
            throw new BusinessRuleException(
                'This change would remove the last active administrator. Assign the Administrator role to another active user first.'
            );
        }
    }

    public function activeAdminCount(): int
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Admin->value))
            ->count();
    }

    /**
     * @param  array<int, RoleName|string>  $roles
     * @return string[]
     */
    private function normalise(array $roles): array
    {
        $names = array_map(
            static fn (RoleName|string $r): string => $r instanceof RoleName ? $r->value : $r,
            $roles,
        );

        return array_values(array_unique(array_filter($names, static fn (string $n): bool => $n !== '')));
    }
}
