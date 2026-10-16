<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ArchivedUser;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ConfigurationSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Renders every authenticated page (admin/staff and guest) with realistic data
 * and asserts a 200. This is the catalogue that catches broken Blade views,
 * missing controller variables and bad eager-loading.
 */
class AdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedSettings();
    }

    public function test_every_staff_page_renders_for_an_administrator(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create(['name' => 'Rita Reception']);

        $guestUser = User::factory()->guest()->create();
        $guest = Guest::factory()->withAccount($guestUser)->create();

        $type = RoomType::factory()->create();
        $room = Room::factory()->forType($type)->create();

        $booking = Booking::factory()->create([
            'guest_id' => $guest->getKey(),
            'user_id' => $admin->getKey(),
        ]);
        $booking->rooms()->attach($room->getKey(), ['rate' => 150.00]);

        $payment = Payment::factory()->create([
            'booking_id' => $booking->getKey(),
            'user_id' => $admin->getKey(),
        ]);

        $auditLog = AuditLog::factory()->create();
        $backup = BackupLog::factory()->create(['created_by' => $admin->getKey()]);
        $failedBackup = BackupLog::factory()->failed()->create(['created_by' => $admin->getKey()]);
        $snapshot = app(ConfigurationSnapshotService::class)->create('Smoke baseline', null, true, $admin);
        $archived = ArchivedUser::factory()->create();

        $role = Role::query()->where('name', 'admin')->firstOrFail();

        $pages = [
            '/dashboard',
            '/front-desk',
            '/profile',

            '/users',
            '/users/create',
            '/users/'.$receptionist->getKey(),
            '/users/'.$receptionist->getKey().'/edit',
            '/archived-users',

            '/roles',
            '/roles/'.$role->getKey(),

            '/rooms',
            '/rooms/create',
            '/rooms/'.$room->getKey(),
            '/rooms/'.$room->getKey().'/edit',

            '/room-types',
            '/room-types/create',
            '/room-types/'.$type->slug,
            '/room-types/'.$type->slug.'/edit',

            '/guests',
            '/guests/create',
            '/guests/'.$guest->getKey(),
            '/guests/'.$guest->getKey().'/edit',

            '/bookings',
            '/bookings/create',
            '/bookings/'.$booking->getKey(),
            '/bookings/'.$booking->getKey().'/edit',

            '/payments',
            '/payments/create',
            '/payments/'.$payment->getKey(),

            '/reports',
            '/reports/occupancy',
            '/reports/revenue',

            '/settings',

            '/restore-points',
            '/restore-points/'.$snapshot->getKey(),
            '/restore-points/'.$snapshot->getKey().'/preview',

            '/backups',
            '/backups/'.$backup->getKey(),
            '/backups/'.$failedBackup->getKey(),

            '/audit-logs',
            '/audit-logs/'.$auditLog->getKey(),
        ];

        foreach ($pages as $uri) {
            $response = $this->actingAs($admin)->get($uri);
            $this->assertSame(200, $response->getStatusCode(), "GET {$uri} returned {$response->getStatusCode()}");
        }

        $this->assertDatabaseHas('archived_users', ['id' => $archived->getKey()]);
    }

    public function test_every_guest_page_renders_for_a_guest_account(): void
    {
        $guestUser = User::factory()->guest()->create();
        $guest = Guest::factory()->withAccount($guestUser)->create();

        $type = RoomType::factory()->create();
        $room = Room::factory()->forType($type)->create();

        $booking = Booking::factory()->create([
            'guest_id' => $guest->getKey(),
            'user_id' => $guestUser->getKey(),
        ]);
        $booking->rooms()->attach($room->getKey(), ['rate' => 150.00]);

        foreach (['/profile', '/guest/dashboard', '/guest/bookings', '/guest/bookings/create', '/guest/bookings/'.$booking->getKey()] as $uri) {
            $response = $this->actingAs($guestUser)->get($uri);
            $this->assertSame(200, $response->getStatusCode(), "GET {$uri} returned {$response->getStatusCode()}");
        }
    }
}
