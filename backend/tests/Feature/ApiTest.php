<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\RoomType;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * The REST API consumed by the Flutter mobile client.
 *
 * It mirrors the web 2FA flow: step one returns a short-lived challenge token,
 * step two exchanges the emailed OTP for a 30-day bearer token.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_health_and_catalogue_endpoints_are_public(): void
    {
        RoomType::factory()->create();

        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');

        $this->getJson('/api/room-types')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'base_price', 'capacity']]]);
    }

    public function test_two_step_login_issues_a_mobile_token(): void
    {
        $user = User::factory()->receptionist()->create();

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => \Database\Factories\UserFactory::DEFAULT_PASSWORD,
        ]);

        $login->assertOk()->assertJsonPath('status', 'two_factor_required');

        $challenge = $login->json('challenge_token');
        $code = $login->json('dev_code');

        $this->assertNotEmpty($challenge);
        // TWO_FACTOR_EXPOSE_CODE=true in phpunit.xml exposes the code to tests.
        $this->assertNotEmpty($code);

        $verify = $this->postJson('/api/auth/two-factor', [
            'challenge_token' => $challenge,
            'code' => $code,
        ]);

        $verify->assertOk()->assertJsonPath('status', 'authenticated');

        $token = (string) $verify->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_bad_credentials_return_401(): void
    {
        $user = User::factory()->receptionist()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertStatus(401);
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_a_guest_token_cannot_reach_staff_endpoints(): void
    {
        $guest = User::factory()->guest()->create();
        $token = $guest->createToken('test-device', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/dashboard/summary')->assertForbidden();
    }

    public function test_a_staff_token_can_reach_staff_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test-device', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/dashboard/summary')->assertOk();
    }

    public function test_an_archived_account_cannot_use_an_existing_api_token(): void
    {
        $user = User::factory()->guest()->create(['status' => UserStatus::Archived]);
        $token = $user->createToken('test-device', ['*'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/bookings')
            ->assertForbidden();
    }
}
