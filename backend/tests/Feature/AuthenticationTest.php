<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\TwoFactorOtpMail;
use App\Models\User;
use App\Services\OtpService;
use App\Services\UserArchiveService;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsHotel;
use Tests\TestCase;

/**
 * Two-step authentication for the web application.
 *
 * The security guarantee under test: a correct password does NOT sign a user
 * in. Only a valid, unexpired, single-use email OTP completes the login.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsHotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        Mail::fake();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'definitely-the-wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_correct_password_does_not_sign_the_user_in(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        Mail::assertSent(TwoFactorOtpMail::class, fn (TwoFactorOtpMail $mail): bool => $mail->user->is($user));

        // A protected route is still a guest request before step two.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_a_valid_two_factor_code_completes_the_login(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $code = $this->capturedCode();

        $this->post('/two-factor/challenge', ['code' => $code])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_incorrect_two_factor_code_is_rejected(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $code = $this->capturedCode();
        $wrong = str_pad((string) ((((int) $code) + 1) % 1000000), strlen($code), '0', STR_PAD_LEFT);

        $this->post('/two-factor/challenge', ['code' => $wrong])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_two_factor_code_is_single_use(): void
    {
        $user = User::factory()->admin()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $code = $this->capturedCode();

        $this->post('/two-factor/challenge', ['code' => $code]);
        $this->assertAuthenticatedAs($user);

        $second = app(OtpService::class)->verify($user, $code);

        $this->assertFalse($second['ok']);
    }

    public function test_an_archived_account_cannot_sign_in(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        app(UserArchiveService::class)->archive($admin, $otherAdmin, 'left the company');

        $response = $this->from('/login')->post('/login', [
            'email' => $admin->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->receptionist()->inactive()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function capturedCode(): string
    {
        $code = null;

        Mail::assertSent(TwoFactorOtpMail::class, function (TwoFactorOtpMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->assertIsString($code, 'No two-factor code email was sent.');

        return $code;
    }
}
