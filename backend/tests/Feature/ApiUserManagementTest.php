<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * User administration from the mobile app. The safety rails matter more than
 * the CRUD: no guest may reach this area, and the last active administrator
 * can never be deactivated.
 */
class ApiUserManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function token(User $user): string
    {
        return $user->createToken('test-device', ['*'])->plainTextToken;
    }

    public function test_an_admin_can_list_staff_and_guests(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = User::factory()->receptionist()->create();
        $guest = User::factory()->guest()->create();

        $response = $this->withToken($this->token($admin))->getJson('/api/users?per_page=50')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($admin->getKey(), $ids);
        $this->assertContains($receptionist->getKey(), $ids);
        $this->assertContains($guest->getKey(), $ids);
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_a_receptionist_cannot_manage_users(): void
    {
        $receptionist = User::factory()->receptionist()->create();

        $this->withToken($this->token($receptionist))->getJson('/api/users')->assertForbidden();
        $this->withToken($this->token($receptionist))->postJson('/api/users', [])->assertForbidden();
    }

    public function test_roles_are_listed_with_plain_language_descriptions(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withToken($this->token($admin))->getJson('/api/users/roles')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['admin', 'receptionist', 'guest'], $names);
        $this->assertNotEmpty($response->json('data.0.description'));
    }

    public function test_an_admin_can_create_a_receptionist(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withToken($this->token($admin))->postJson('/api/users', [
            'name' => 'Mo Frontdesk',
            'username' => 'mo_frontdesk',
            'email' => 'mo_frontdesk@example.test',
            'phone' => '+1 555 0199',
            'status' => 'active',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'password_confirmation' => UserFactory::DEFAULT_PASSWORD,
            'roles' => ['receptionist'],
        ])->assertCreated();

        $response->assertJsonPath('data.username', 'mo_frontdesk')
            ->assertJsonPath('data.roles.0', 'receptionist')
            ->assertJsonPath('data.two_factor_enabled', false);

        $this->assertDatabaseHas('users', ['username' => 'mo_frontdesk', 'status' => 'active']);
    }

    public function test_creating_a_duplicate_username_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['username' => 'taken-name']);

        $this->withToken($this->token($admin))->postJson('/api/users', [
            'name' => 'Someone Else',
            'username' => $existing->username,
            'email' => 'unique@example.test',
            'status' => 'active',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'password_confirmation' => UserFactory::DEFAULT_PASSWORD,
            'roles' => ['guest'],
        ])->assertStatus(422)->assertJsonValidationErrors('username');
    }

    public function test_deactivating_yourself_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withToken($this->token($admin))
            ->postJson('/api/users/'.$admin->getKey().'/deactivate')
            ->assertStatus(422)
            ->assertJsonPath('reason', 'self');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_editing_yourself_to_inactive_is_refused(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'self_admin']);

        $response = $this->withToken($this->token($admin))
            ->putJson('/api/users/'.$admin->getKey(), [
                'name' => $admin->name,
                'username' => $admin->username,
                'email' => $admin->email,
                'status' => 'inactive',
            ]);
        $response->assertStatus(422)
            ->assertJsonPath('reason', 'self');

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_an_admin_can_deactivate_another_admin_while_one_remains(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->withToken($this->token($admin))
            ->postJson('/api/users/'.$other->getKey().'/deactivate')
            ->assertOk();

        $this->assertFalse($other->fresh()->isActive());
        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_deactivating_a_user_signs_their_devices_out(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->guest()->create();
        $guestToken = $this->token($guest);

        $this->withToken($this->token($admin))
            ->postJson('/api/users/'.$guest->getKey().'/deactivate')
            ->assertOk();

        $this->assertFalse($guest->fresh()->isActive());
        $this->assertSame(0, $guest->tokens()->count());

        // Laravel reuses one container across test requests, so the Sanctum
        // guard can hold the previous caller. Production boots per request.
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($guestToken)->getJson('/api/auth/me');
        $response->assertUnauthorized();
    }

    public function test_a_password_reset_works_and_signs_old_devices_out(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->guest()->withoutTwoFactor()->create();
        $oldToken = $this->token($guest);

        $newPassword = 'Fresh!Passw0rd2026';

        $this->withToken($this->token($admin))
            ->postJson('/api/users/'.$guest->getKey().'/password', [
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($oldToken)->getJson('/api/auth/me')->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $guest->email,
            'password' => $newPassword,
        ])->assertOk()->assertJsonPath('status', 'authenticated');
    }

    public function test_updating_a_user_and_their_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->guest()->create();

        $this->withToken($this->token($admin))
            ->putJson('/api/users/'.$user->getKey(), [
                'name' => 'Renamed Person',
                'username' => 'renamed_person',
                'email' => $user->email,
                'phone' => '+1 555 0111',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Person');

        $this->withToken($this->token($admin))
            ->putJson('/api/users/'.$user->getKey().'/roles', ['roles' => ['receptionist']])
            ->assertOk()
            ->assertJsonPath('data.roles.0', 'receptionist');
    }
}
