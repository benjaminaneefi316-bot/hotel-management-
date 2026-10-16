<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AuditOutcome;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A small, believable audit trail so the audit-log screens are not empty on a
 * fresh install. Real activity is written by AuditLogger as the application is
 * used; this only provides demonstration history for fictional accounts.
 */
class AuditLogSeeder extends Seeder
{
    /**
     * action => [event type, description, outcome]
     *
     * @var array<int, array{0:string,1:string,2:string,3:AuditOutcome}>
     */
    private const ENTRIES = [
        ['auth.login.success', 'auth', 'Signed in successfully.', AuditOutcome::Success],
        ['auth.login.failed', 'auth', 'Incorrect password supplied.', AuditOutcome::Failure],
        ['auth.2fa.verified', 'security', 'Two-factor code accepted.', AuditOutcome::Success],
        ['auth.2fa.failed', 'security', 'Invalid two-factor code rejected.', AuditOutcome::Denied],
        ['booking.create', 'data', 'Created a reservation.', AuditOutcome::Success],
        ['booking.cancel', 'data', 'Cancelled a reservation.', AuditOutcome::Success],
        ['stay.check_in', 'data', 'Checked a guest into a room.', AuditOutcome::Success],
        ['stay.check_out', 'data', 'Checked a guest out and settled the folio.', AuditOutcome::Success],
        ['payment.create', 'data', 'Recorded a payment.', AuditOutcome::Success],
        ['payment.refund', 'data', 'Refunded a payment.', AuditOutcome::Success],
        ['setting.update', 'system', 'Updated an application setting.', AuditOutcome::Success],
        ['backup.run', 'system', 'Completed an encrypted backup to object storage.', AuditOutcome::Success],
        ['restore_point.create', 'admin', 'Captured a configuration restore point.', AuditOutcome::Success],
        ['user.archive', 'admin', 'Archived a user account.', AuditOutcome::Success],
        ['role.denied', 'security', 'Blocked an action the actor was not authorised to perform.', AuditOutcome::Denied],
    ];

    public function run(): void
    {
        $users = User::query()->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->command->warn('AuditLogSeeder skipped: run UserSeeder first.');

            return;
        }

        foreach (self::ENTRIES as $index => [$action, $eventType, $description, $outcome]) {
            $actor = $users[$index % $users->count()];

            $log = AuditLog::query()->create([
                'user_id' => $actor->getKey(),
                'actor_username' => $actor->username,
                'action' => $action,
                'event_type' => $eventType,
                'description' => $description,
                'auditable_type' => null,
                'auditable_id' => null,
                'ip_address' => '203.0.113.'.(10 + $index),
                'user_agent' => 'AzureBayDemo/1.0',
                'old_values' => null,
                'new_values' => null,
                'outcome' => $outcome,
            ]);

            // Backdate so the history looks chronological (created_at is not
            // mass assignable on AuditLog, hence forceFill).
            $log->forceFill([
                'created_at' => now()->subMinutes((count(self::ENTRIES) - $index) * 37),
            ])->save();
        }

        $this->command->info('Audit logs seeded: '.count(self::ENTRIES));
    }
}
