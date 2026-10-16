<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * RBAC is enforced server-side through named gates (config/abilities.php), so
 * hiding a menu item is never the security boundary.
 */
class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_the_ability_matrix_matches_the_role_definitions(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create();
        $guest = User::factory()->guest()->create();

        // Administrator: everything.
        $this->assertTrue(Gate::forUser($admin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-roles'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-backups'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-restore-points'));
        $this->assertTrue(Gate::forUser($admin)->allows('view-reports'));

        // Receptionist: operations only.
        $this->assertTrue(Gate::forUser($receptionist)->allows('manage-bookings'));
        $this->assertTrue(Gate::forUser($receptionist)->allows('check-in-out'));
        $this->assertFalse(Gate::forUser($receptionist)->allows('manage-users'));
        $this->assertFalse(Gate::forUser($receptionist)->allows('manage-backups'));
        $this->assertFalse(Gate::forUser($receptionist)->allows('view-reports'));

        // Guest: self-service only.
        $this->assertTrue(Gate::forUser($guest)->allows('book-own-room'));
        $this->assertTrue(Gate::forUser($guest)->allows('view-own-bookings'));
        $this->assertFalse(Gate::forUser($guest)->allows('access-staff-area'));
        $this->assertFalse(Gate::forUser($guest)->allows('manage-guests'));
    }

    public function test_a_receptionist_is_forbidden_from_user_administration(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get('/users')->assertForbidden();
    }

    public function test_a_guest_cannot_open_the_staff_dashboard(): void
    {
        $guest = User::factory()->guest()->create();

        $this->actingAs($guest)->get('/dashboard')->assertForbidden();
    }

    public function test_a_receptionist_cannot_open_restore_points(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->actingAs($receptionist)->get('/restore-points')->assertForbidden();
    }

    public function test_an_administrator_can_open_user_administration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/users')->assertOk();
    }
}
