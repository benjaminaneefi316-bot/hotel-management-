<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Authenticator-app enrolment from the mobile app.
 *
 * The central guarantee: a half-finished setup never changes the account, and
 * a secret is only persisted after a live code has been verified.
 */
class ApiTwoFactorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    private function token(User $user): string
    {
        return $user->createToken('test-device', ['*'])->plainTextToken;
    }

    public function test_a_new_account_reports_two_factor_off(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $this->withToken($this->token($user))
            ->getJson('/api/two-factor')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('channel', 'email');
    }

    public function test_setup_returns_a_secret_but_does_not_enable_two_factor(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['two_factor_enabled' => false, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $response = $this->withToken($this->token($user))->postJson('/api/two-factor/setup')->assertOk();

        $this->assertNotEmpty($response->json('secret'));
        $this->assertStringStartsWith('otpauth://totp/', (string) $response->json('otpauth_uri'));
        $this->assertSame($user->email, $response->json('account'));

        $this->assertFalse($user->fresh()->usesAuthenticatorApp(), 'A pending setup must not enable 2FA.');
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_setup_reuses_the_pending_secret_on_reopen(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user);

        $first = $this->withToken($token)->postJson('/api/two-factor/setup')->json('secret');
        $second = $this->withToken($token)->postJson('/api/two-factor/setup')->json('secret');

        $this->assertSame($first, $second);
    }

    public function test_a_valid_code_enables_two_factor(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->token($user);

        $secret = $this->withToken($token)->postJson('/api/two-factor/setup')->json('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withToken($token)
            ->postJson('/api/two-factor/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('channel', 'app')
            ->assertJsonPath('user.two_factor_enabled', true);

        $this->assertTrue($user->fresh()->usesAuthenticatorApp());
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
    }

    public function test_a_wrong_code_leaves_the_account_untouched(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->token($user);

        $this->withToken($token)->postJson('/api/two-factor/setup')->assertOk();

        $this->withToken($token)
            ->postJson('/api/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_code');

        $this->assertFalse($user->fresh()->usesAuthenticatorApp());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_enrolling_twice_is_refused(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->token($user);

        $secret = $this->withToken($token)->postJson('/api/two-factor/setup')->json('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->withToken($token)->postJson('/api/two-factor/confirm', ['code' => $code])->assertOk();

        $this->withToken($token)
            ->postJson('/api/two-factor/setup')
            ->assertStatus(409)
            ->assertJsonPath('reason', 'already_enabled');
    }

    public function test_a_valid_code_can_remove_the_authenticator(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->token($user);

        $secret = $this->withToken($token)->postJson('/api/two-factor/setup')->json('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->withToken($token)->postJson('/api/two-factor/confirm', ['code' => $code])->assertOk();

        // A fresh code: the one used above is consumed by the TOTP service.
        $fresh = app(Google2FA::class)->getCurrentOtp($secret);

        $this->withToken($token)
            ->deleteJson('/api/two-factor', ['code' => $fresh])
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('channel', 'email');

        $this->assertFalse($user->fresh()->usesAuthenticatorApp());
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->two_factor_enabled);

        // With no authenticator, sign-in is password-only rather than waiting
        // for an email the production mailer would only write to a log file.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => \Database\Factories\UserFactory::DEFAULT_PASSWORD,
        ])->assertOk()->assertJsonPath('status', 'authenticated');
    }

    public function test_removing_with_a_wrong_code_is_refused(): void
    {
        $user = User::factory()->admin()->create();
        $token = $this->token($user);

        $secret = $this->withToken($token)->postJson('/api/two-factor/setup')->json('secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->withToken($token)->postJson('/api/two-factor/confirm', ['code' => $code])->assertOk();

        $this->withToken($token)
            ->deleteJson('/api/two-factor', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_code');

        $this->assertTrue($user->fresh()->usesAuthenticatorApp());
    }

    public function test_a_guest_cannot_enrol_for_someone_else(): void
    {
        $guest = User::factory()->guest()->create();
        $other = User::factory()->admin()->create();

        $this->withToken($this->token($guest))
            ->postJson('/api/two-factor/setup', ['user_id' => $other->getKey()])
            ->assertOk();

        $this->assertFalse($other->fresh()->usesAuthenticatorApp());
    }
}
