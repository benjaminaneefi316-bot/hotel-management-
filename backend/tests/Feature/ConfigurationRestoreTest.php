<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ConfigurationSnapshotService;
use App\Services\RoleService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * MANDATORY ASSIGNMENT TEST.
 *
 * A restore point must roll back APPROVED CONFIGURATION ONLY. The scenario is
 * exactly the one the brief describes:
 *
 *   1. capture a snapshot of settings + role assignments
 *   2. a guest and a booking are created
 *   3. an administrator changes a setting and changes a user's role
 *   4. another guest and another booking (and a payment) are created
 *   5. the snapshot is restored
 *
 * After the restore we assert that:
 *   - the setting is back to the captured value,
 *   - the role is back to the captured value,
 *   - EVERY guest, booking and payment created along the way still exists,
 *   - the restore itself is written to the audit trail.
 */
class ConfigurationRestoreTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    private ConfigurationSnapshotService $snapshots;

    private SettingsService $settings;

    private RoleService $roles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedSettings();

        $this->snapshots = app(ConfigurationSnapshotService::class);
        $this->settings = app(SettingsService::class);
        $this->roles = app(RoleService::class);
    }

    public function test_restore_reverts_configuration_but_never_touches_business_data(): void
    {
        // --- 1. Approve a configuration baseline ---------------------------
        $admin = User::factory()->admin()->create(['email' => 'admin.actor@example.test']);
        $colleague = User::factory()->receptionist()->create(['email' => 'colleague@example.test']);

        $snapshot = $this->snapshots->create(
            name: 'Approved baseline',
            description: 'Signed off by the general manager.',
            includeRoleAssignments: true,
            actor: $admin,
        );

        $this->assertSame('Azure Bay Hotel', $this->settings->get('hotel_name'));
        $this->assertSame(['receptionist'], $colleague->fresh()->roleNames());

        // --- 2. Business happens after the snapshot ------------------------
        $roomType = RoomType::factory()->create();
        $roomOne = Room::factory()->forType($roomType)->create();

        $guestOne = Guest::factory()->create();
        $bookingOne = Booking::factory()->create([
            'guest_id' => $guestOne->getKey(),
            'user_id' => $admin->getKey(),
        ]);
        $bookingOne->rooms()->attach($roomOne->getKey(), ['rate' => 150.00]);

        // --- 3. Configuration is changed -----------------------------------
        $this->settings->set('hotel_name', 'Changed Name Hotel', $admin);
        $this->roles->syncRoles($colleague, [RoleName::Admin], $admin);

        $this->assertSame('Changed Name Hotel', $this->settings->get('hotel_name'));
        $this->assertSame(['admin'], $colleague->fresh()->roleNames());

        // --- 4. More business happens after the change ---------------------
        $roomTwo = Room::factory()->forType($roomType)->create();
        $guestTwo = Guest::factory()->create();
        $bookingTwo = Booking::factory()->create([
            'guest_id' => $guestTwo->getKey(),
            'user_id' => $colleague->getKey(),
        ]);
        $bookingTwo->rooms()->attach($roomTwo->getKey(), ['rate' => 150.00]);

        $payment = Payment::factory()->create([
            'booking_id' => $bookingOne->getKey(),
            'user_id' => $admin->getKey(),
            'amount' => 450.00,
        ]);

        $guestsBefore = Guest::query()->count();
        $bookingsBefore = Booking::query()->count();
        $paymentsBefore = Payment::query()->count();

        // --- 5. Restore the configuration baseline -------------------------
        $applied = $this->snapshots->restore($snapshot->load('items'), $admin);

        $this->assertNotEmpty($applied);

        // The setting is back to the captured value.
        $this->assertSame('Azure Bay Hotel', $this->settings->get('hotel_name'));

        // The role assignment is back to the captured value.
        $this->assertSame(['receptionist'], $colleague->fresh()->roleNames());

        // Business data is completely untouched.
        $this->assertSame($guestsBefore, Guest::query()->count());
        $this->assertSame($bookingsBefore, Booking::query()->count());
        $this->assertSame($paymentsBefore, Payment::query()->count());

        $this->assertDatabaseHas('guests', ['id' => $guestOne->getKey()]);
        $this->assertDatabaseHas('guests', ['id' => $guestTwo->getKey()]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingOne->getKey()]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingTwo->getKey()]);
        $this->assertDatabaseHas('payments', ['id' => $payment->getKey()]);

        // The restore is audited.
        $this->assertDatabaseHas('audit_logs', ['action' => 'restore_point.restore', 'outcome' => 'success']);
    }

    public function test_snapshot_never_contains_business_rows_or_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        Guest::factory()->create();

        $snapshot = $this->snapshots->create('Baseline', null, false, $admin)->load('items');

        $keys = $snapshot->items->pluck('item_key')->all();

        foreach ($keys as $key) {
            $this->assertFalse($this->settings->isForbidden($key), "Snapshot captured a forbidden key: {$key}");
        }

        $this->assertNotContains('guests', $keys);
        $this->assertNotContains('bookings', $keys);
        $this->assertNotContains('password', $keys);
    }

    public function test_a_tampered_snapshot_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $snapshot = $this->snapshots->create('Baseline', null, false, $admin)->load('items');

        $snapshot->items()->first()?->forceFill(['captured_value' => 'tampered'])->save();

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->snapshots->restore($snapshot->fresh('items'), $admin);
    }
}
