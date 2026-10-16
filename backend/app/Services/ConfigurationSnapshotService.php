<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ConfigurationSnapshot;
use App\Models\ConfigurationSnapshotItem;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Configuration-only restore points.
 *
 * WHAT A SNAPSHOT CONTAINS
 *   - whitelisted application settings (config/restore-points.php)
 *   - optionally, the role set of every non-archived user
 *
 * WHAT IT NEVER CONTAINS
 *   - guests, bookings, stays, payments, rooms, audit history (business data)
 *   - passwords, password hashes, salts, the pepper, OTPs, access tokens or
 *     encryption keys (secrets)
 *
 * WHAT A RESTORE DOES
 *   Writes ONLY the captured configuration values back, inside one database
 *   transaction. Business rows are never read from or written to the restore
 *   path, so a restore cannot roll back a guest, booking or payment.
 *
 * SAFETY
 *   - the stored checksum is verified before every restore (tamper detection)
 *   - the last active administrator is protected
 *   - a preview (current value -> captured value) is shown before confirming
 */
final class ConfigurationSnapshotService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RoleService $roles,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Capture a new snapshot.
     *
     * @param  array<int, string>  $settingKeys  empty = every eligible setting
     */
    public function create(
        string $name,
        ?string $description,
        bool $includeRoleAssignments,
        User $actor,
        array $settingKeys = [],
    ): ConfigurationSnapshot {
        $settings = $this->settings->eligible();

        if ($settingKeys !== []) {
            $settings = $settings->filter(fn (SystemSetting $s): bool => in_array($s->key, $settingKeys, true))->values();
        }

        $items = [];

        foreach ($settings as $setting) {
            $items[] = [
                'item_type' => ConfigurationSnapshotItem::TYPE_SETTING,
                'item_key' => $setting->key,
                'captured_value' => $setting->value,
            ];
        }

        if ($includeRoleAssignments) {
            $users = User::query()
                ->where('status', '!=', UserStatus::Archived->value)
                ->with('roles')
                ->get();

            foreach ($users as $user) {
                $items[] = [
                    'item_type' => ConfigurationSnapshotItem::TYPE_ROLE_ASSIGNMENT,
                    'item_key' => 'user:'.$user->getKey(),
                    'captured_value' => json_encode($user->roleNames(), JSON_THROW_ON_ERROR),
                ];
            }
        }

        return DB::transaction(function () use ($name, $description, $includeRoleAssignments, $actor, $items): ConfigurationSnapshot {
            $snapshot = ConfigurationSnapshot::query()->create([
                'name' => $name,
                'description' => $description,
                'schema_version' => (string) config('restore-points.schema_version', '1.0'),
                'includes_role_assignments' => $includeRoleAssignments,
                'item_count' => count($items),
                'checksum' => $this->checksum($items),
                'created_by' => $actor->getKey(),
                'captured_at' => now(),
            ]);

            foreach ($items as $item) {
                $snapshot->items()->create($item);
            }

            $this->audit->success(
                action: 'restore_point.create',
                eventType: 'admin',
                description: sprintf(
                    'Created configuration snapshot "%s" with %d item(s)%s.',
                    $name,
                    count($items),
                    $includeRoleAssignments ? ' including role assignments' : '',
                ),
                subject: $snapshot,
                newValues: ['items' => count($items), 'includes_roles' => $includeRoleAssignments],
            );

            return $snapshot->load('items');
        });
    }

    /**
     * Compare the snapshot against the current configuration.
     *
     * @return array<int, array{type: string, key: string, label: string, current: ?string, captured: ?string, changed: bool}>
     */
    public function preview(ConfigurationSnapshot $snapshot): array
    {
        $rows = [];

        foreach ($snapshot->items as $item) {
            if ($item->item_type === ConfigurationSnapshotItem::TYPE_SETTING) {
                $current = SystemSetting::query()->where('key', $item->item_key)->value('value');
                $rows[] = [
                    'type' => 'Setting',
                    'key' => $item->item_key,
                    'label' => $item->item_key,
                    'current' => $current === null ? null : (string) $current,
                    'captured' => $item->captured_value,
                    'changed' => (string) $current !== (string) $item->captured_value,
                ];
            } else {
                $userId = (int) str_replace('user:', '', $item->item_key);
                $user = User::query()->with('roles')->find($userId);
                $current = $user ? implode(', ', $user->roleNames()) : '(user missing)';
                $captured = implode(', ', json_decode((string) $item->captured_value, true) ?: []);

                $rows[] = [
                    'type' => 'Role assignment',
                    'key' => $item->item_key,
                    'label' => $user?->username ?? $item->item_key,
                    'current' => $current,
                    'captured' => $captured,
                    'changed' => $current !== $captured,
                ];
            }
        }

        return $rows;
    }

    /**
     * Apply the snapshot's configuration values.
     *
     * @return array<int, array{type: string, key: string, from: ?string, to: ?string, status: string}>
     *
     * @throws BusinessRuleException
     */
    public function restore(ConfigurationSnapshot $snapshot, User $actor): array
    {
        $this->assertIntegrity($snapshot);

        if ($snapshot->schema_version !== (string) config('restore-points.schema_version', '1.0')) {
            throw new BusinessRuleException(
                "Snapshot schema version {$snapshot->schema_version} is not supported by this application."
            );
        }

        $applied = [];

        DB::transaction(function () use ($snapshot, $actor, &$applied): void {
            // ---- 1. Settings -------------------------------------------------
            foreach ($snapshot->items as $item) {
                if ($item->item_type !== ConfigurationSnapshotItem::TYPE_SETTING) {
                    continue;
                }

                // Re-validate the whitelist at restore time; a key that is no
                // longer eligible is skipped instead of blindly written.
                if (! $this->settings->isEligible($item->item_key) || $this->settings->isForbidden($item->item_key)) {
                    continue;
                }

                $setting = SystemSetting::query()->firstOrNew(['key' => $item->item_key]);
                $from = $setting->exists ? $setting->value : null;
                $setting->value = $item->captured_value;
                $setting->save();

                $applied[] = [
                    'type' => 'setting',
                    'key' => $item->item_key,
                    'from' => $from,
                    'to' => $item->captured_value,
                    'status' => 'restored',
                ];
            }

            // ---- 2. Role assignments (only when captured) --------------------
            if ($snapshot->includes_role_assignments) {
                foreach ($snapshot->items as $item) {
                    if ($item->item_type !== ConfigurationSnapshotItem::TYPE_ROLE_ASSIGNMENT) {
                        continue;
                    }

                    $userId = (int) str_replace('user:', '', $item->item_key);
                    $user = User::query()->find($userId);

                    // Never resurrect archived/deactivated accounts via a restore.
                    if (! $user || $user->status !== UserStatus::Active) {
                        continue;
                    }

                    $names = json_decode((string) $item->captured_value, true) ?: [];
                    $roleIds = Role::query()->whereIn('name', $names)->pluck('id', 'name');

                    $before = $user->roleNames();

                    $pivot = [];
                    foreach ($names as $name) {
                        if ($roleIds->has($name)) {
                            $pivot[$roleIds[$name]] = ['assigned_by' => $actor->getKey(), 'assigned_at' => now()];
                        }
                    }

                    $user->roles()->sync($pivot);

                    $applied[] = [
                        'type' => 'role_assignment',
                        'key' => $item->item_key,
                        'from' => implode(', ', $before),
                        'to' => implode(', ', $names),
                        'status' => 'restored',
                    ];
                }

                // ---- 3. Safety: never leave the system without an admin -----
                if ((bool) config('restore-points.restore.protect_last_admin', true)) {
                    $this->roles->assertActiveAdminRemains();
                }
            }

            $snapshot->forceFill([
                'last_restored_at' => now(),
                'restore_count' => (int) $snapshot->restore_count + 1,
            ])->save();
        });

        $this->settings->flush();

        $this->audit->success(
            action: 'restore_point.restore',
            eventType: 'admin',
            description: sprintf('Restored configuration snapshot "%s" (%d item(s) applied).', $snapshot->name, count($applied)),
            subject: $snapshot,
            newValues: ['applied' => $applied],
        );

        return $applied;
    }

    /**
     * Verify the snapshot has not been altered after it was captured.
     *
     * @throws BusinessRuleException
     */
    public function assertIntegrity(ConfigurationSnapshot $snapshot): void
    {
        $snapshot->loadMissing('items');

        $items = $snapshot->items
            ->map(fn (ConfigurationSnapshotItem $i): array => [
                'item_type' => $i->item_type,
                'item_key' => $i->item_key,
                'captured_value' => $i->captured_value,
            ])
            ->all();

        if ($this->checksum($items) !== $snapshot->checksum) {
            $this->audit->failure(
                action: 'restore_point.integrity_failure',
                eventType: 'security',
                description: sprintf('Checksum mismatch for snapshot "%s"; restore refused.', $snapshot->name),
                subject: $snapshot,
            );

            throw new BusinessRuleException('The snapshot failed its integrity check and was not restored.');
        }
    }

    /**
     * Deterministic SHA-256 over the canonical JSON of the items.
     *
     * @param  array<int, array{item_type: string, item_key: string, captured_value: ?string}>  $items
     */
    private function checksum(array $items): string
    {
        $canonical = array_map(
            static fn (array $i): array => [
                'item_type' => $i['item_type'],
                'item_key' => $i['item_key'],
                'captured_value' => $i['captured_value'],
            ],
            $items,
        );

        usort($canonical, static fn (array $a, array $b): int => [$a['item_type'], $a['item_key']] <=> [$b['item_type'], $b['item_key']]);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }
}
