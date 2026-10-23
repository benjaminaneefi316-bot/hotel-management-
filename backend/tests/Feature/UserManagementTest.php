<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Admin user management: creating accounts and toggling two-factor.
 *
 * The important rule is that an account can never be left in a state where
 * sign-in demands a code it cannot produce: two-factor is only switched on
 * once an authenticator app has actually been enrolled.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seedSettings();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Staff',
            'username' => 'teststaff',
            'email' => 'teststaff@example.test',
            'status' => 'active',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'password_confirmation' => UserFactory::DEFAULT_PASSWORD,
            'roles' => ['receptionist'],
        ], $overrides);
    }

    public function test_an_administrator_can_create_a_user_who_can_immediately_sign_in(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', $this->payload());

        $user = User::query()->where('username', 'teststaff')->firstOrFail();

        $response->assertRedirect(route('users.show', $user));
        $this->assertSame(['receptionist'], $user->roleNames());
        $this->assertFalse($user->two_factor_enabled, 'New accounts must start without two-factor.');
    }

    public function test_a_freshly_created_account_can_sign_in_with_its_password(): void
    {
        $user = User::factory()->receptionist()->withoutTwoFactor()->create([
            'email' => 'newhire@example.test',
        ]);

        $response = $this->post('/login', [
            'email' => 'newhire@example.test',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $this->assertTrue(auth()->check(), 'The account should be signed in.');
        $this->assertSame($user->getKey(), auth()->id());
        $this->assertStringNotContainsString('two-factor', (string) $response->headers->get('Location'));
    }

    public function test_two_factor_cannot_be_switched_on_for_an_account_without_an_authenticator(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', $this->payload([
            'two_factor_enabled' => '1',
        ]));

        $response->assertSessionHasErrors('two_factor_enabled');
        $this->assertDatabaseMissing('users', ['username' => 'teststaff']);
    }

    public function test_two_factor_cannot_be_switched_on_from_the_edit_form_either(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->receptionist()->withoutTwoFactor()->create([
            'username' => 'plainstaff',
            'email' => 'plainstaff@example.test',
        ]);

        $response = $this->actingAs($admin)->put('/users/'.$staff->getKey(), $this->payload([
            'username' => $staff->username,
            'email' => $staff->email,
            'two_factor_enabled' => '1',
        ]));

        $response->assertSessionHasErrors('two_factor_enabled');
        $this->assertFalse($staff->fresh()->two_factor_enabled);
    }

    public function test_two_factor_can_be_switched_on_once_an_authenticator_is_enrolled(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->receptionist()->withoutTwoFactor()->create([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put('/users/'.$staff->getKey(), $this->payload([
            'username' => $staff->username,
            'email' => $staff->email,
            'two_factor_enabled' => '1',
        ]));

        $response->assertRedirect(route('users.show', $staff));
        $this->assertTrue($staff->fresh()->two_factor_enabled);
    }

    public function test_the_create_form_warns_instead_of_offering_the_two_factor_switch(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/users/create');

        $response->assertOk();
        $response->assertDontSee('id="two_factor_enabled"', false);
        $response->assertSee('Two-factor switches on later', false);
    }

    public function test_validation_messages_are_returned_for_bad_input(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/users', $this->payload([
                'username' => 'weakuser',
                'email' => 'weak@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'roles' => [],
            ]))
            ->assertSessionHasErrors(['password', 'roles']);

        $this->actingAs($admin)
            ->post('/users', $this->payload([
                'username' => 'jane.doe',
                'email' => 'jane.doe@example.test',
            ]))
            ->assertSessionHasErrors('username');
    }

    public function test_the_stored_password_verifies_against_the_pepper_scheme(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', $this->payload())->assertRedirect();

        $user = User::query()->where('username', 'teststaff')->firstOrFail();

        $this->assertNotNull($user->password_salt);
        $this->assertNotSame(Hash::make(UserFactory::DEFAULT_PASSWORD), $user->password);
        $this->assertTrue(app(\App\Services\PasswordService::class)->verify(UserFactory::DEFAULT_PASSWORD, $user->password, $user->password_salt));
    }
}
