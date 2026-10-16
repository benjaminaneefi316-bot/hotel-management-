<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ArchivedUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Archive (soft delete) and restore user accounts.
 *
 * Why an `archived_users` table instead of a deleted_at column?
 *   - the account disappears from normal user lists and can never sign in,
 *   - the full record (including its password HASH, never plaintext) is kept
 *     in a separate, audit-friendly table so it can be restored,
 *   - business rows that referenced the user keep a consistent history.
 *
 * Hard deleting users is intentionally NOT offered by the UI.
 */
final class UserArchiveService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RoleService $roles,
    ) {}

    /**
     * Archive a user account.
     *
     * @throws BusinessRuleException
     */
    public function archive(User $user, User $actor, ?string $reason = null): ArchivedUser
    {
        if ($user->getKey() === $actor->getKey()) {
            throw new BusinessRuleException('You cannot archive your own account.');
        }

        if ($user->status === UserStatus::Archived) {
            throw new BusinessRuleException('This account is already archived.');
        }

        // Never archive the last active administrator.
        if ($user->hasRole(RoleName::Admin)) {
            $this->roles->assertActiveAdminRemains(excluding: $user);
        }

        return DB::transaction(function () use ($user, $actor, $reason): ArchivedUser {
            $archive = ArchivedUser::query()->create([
                'original_user_id' => $user->getKey(),
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'password_hash' => (string) $user->password,
                'password_salt' => (string) $user->password_salt,
                'status_at_archive' => $user->status->value,
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
                'roles' => $user->roleNames(),
                'attributes' => [
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                ],
                'archived_by' => $actor->getKey(),
                'reason' => $reason,
                'archived_at' => now(),
            ]);

            // Immediately block authentication.
            $user->forceFill(['status' => UserStatus::Archived])->save();

            // Revoke any API tokens so existing mobile sessions stop working.
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            $this->audit->success(
                action: 'user.archive',
                eventType: 'admin',
                description: sprintf('Archived user %s (%s).', $user->username, $reason ?: 'no reason given'),
                subject: $user,
                oldValues: ['status' => UserStatus::Active->value, 'roles' => $user->roleNames()],
                newValues: ['status' => UserStatus::Archived->value],
            );

            return $archive;
        });
    }

    /**
     * Restore an archived account. Roles are re-attached only for roles that
     * still exist, and the account is brought back as ACTIVE.
     *
     * @throws BusinessRuleException
     */
    public function restore(ArchivedUser $archive, User $actor): User
    {
        if ($archive->isRestored()) {
            throw new BusinessRuleException('This account has already been restored.');
        }

        return DB::transaction(function () use ($archive, $actor): User {
            /** @var User|null $user */
            $user = User::query()->find($archive->original_user_id);

            if (! $user) {
                throw new BusinessRuleException(
                    'The original user record no longer exists, so it cannot be restored. The archive entry has been kept for audit.'
                );
            }

            $user->forceFill([
                'status' => UserStatus::Active,
                // Restore the original credentials (hash + salt only).
                'password' => $archive->password_hash,
                'password_salt' => $archive->password_salt,
            ])->save();

            // Re-attach roles that still exist in the system.
            $roleIds = Role::query()
                ->whereIn('name', $archive->roleNames())
                ->pluck('id');

            $pivot = [];
            foreach ($roleIds as $roleId) {
                $pivot[$roleId] = ['assigned_by' => $actor->getKey(), 'assigned_at' => now()];
            }
            $user->roles()->sync($pivot);
            $user->unsetRelation('roles');

            $archive->forceFill([
                'restored_at' => now(),
                'restored_by' => $actor->getKey(),
            ])->save();

            $this->audit->success(
                action: 'user.restore',
                eventType: 'admin',
                description: sprintf('Restored archived user %s and re-attached roles [%s].', $user->username, implode(', ', $archive->roleNames()) ?: '-'),
                subject: $user,
                oldValues: ['status' => UserStatus::Archived->value],
                newValues: ['status' => UserStatus::Active->value, 'roles' => $archive->roleNames()],
            );

            return $user;
        });
    }
}
