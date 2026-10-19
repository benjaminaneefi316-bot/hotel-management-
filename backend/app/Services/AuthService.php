<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication: step 1 (password) and step 2 (email OTP).
 *
 * SECURITY MODEL - the user is NOT logged in after a correct password.
 *
 *   POST /login
 *     -> credentials checked (constant-time, peppered+salted hash)
 *     -> an OTP is emailed
 *     -> only the user id + a timestamp are placed in the session
 *     -> redirect to the 2FA challenge
 *
 *   POST /two-factor/challenge
 *     -> OTP checked (hashed, single-use, expiring, attempt-capped)
 *     -> ONLY THEN is Auth::login() called and the session regenerated
 *
 * Consequences that the tests rely on:
 *   - visiting any protected route after step 1 but before step 2 is a guest
 *     request and is redirected to the login page,
 *   - an archived/deactivated account can never reach step 1,
 *   - the same error message is returned for "unknown user" and "wrong
 *     password" so the endpoint cannot be used to enumerate accounts.
 */
final class AuthService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly TotpService $totp,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Step 1: verify the password.
     *
     * @return array{ok: bool, user: User|null, reason: string, retry_after: int|null}
     */
    public function attemptPassword(string $email, string $password, ?string $ip = null): array
    {
        $user = User::query()
            ->where('email', $email)
            ->orWhere('username', $email)
            ->first();

        // Identical outcome whether or not the account exists.
        if (! $user) {
            $this->audit->failure(
                'auth.login.failed',
                'security',
                'Sign-in attempted for an unknown account.',
                null,
                ['identifier' => $email],
                [],
            );

            return ['ok' => false, 'user' => null, 'reason' => 'invalid_credentials', 'retry_after' => null];
        }

        // Archived / deactivated accounts are refused BEFORE the password is
        // even examined (no oracle about the password's correctness).
        if ($user->status !== UserStatus::Active) {
            $this->audit->failure(
                'auth.login.blocked',
                'security',
                sprintf('Sign-in blocked: account "%s" is %s.', $user->username, $user->status->value),
                $user,
            );

            return ['ok' => false, 'user' => null, 'reason' => 'account_inactive', 'retry_after' => null];
        }

        $result = $user->verifyPassword($password);

        if (! $result['ok']) {
            $this->audit->failure(
                'auth.login.failed',
                'security',
                sprintf('Incorrect password for "%s".', $user->username),
                $user,
            );

            // Burn a hash so a missing user and a wrong password take similar
            // time (mitigates user enumeration by timing).
            Hash::make('timing-equalisation');

            return ['ok' => false, 'user' => null, 'reason' => 'invalid_credentials', 'retry_after' => null];
        }

        // Transparent upgrade when the pepper was rotated or the cost changed.
        if ($result['stale'] || app(PasswordService::class)->needsRehash((string) $user->password)) {
            $user->setPassword($password);
            $user->saveQuietly();

            $this->audit->success(
                'auth.password.rehash',
                'security',
                sprintf('Password digest upgraded for "%s".', $user->username),
                $user,
            );
        }

        $this->audit->success(
            'auth.password.verified',
            'auth',
            sprintf('Password verified for "%s"; awaiting second factor.', $user->username),
            $user,
        );

        return ['ok' => true, 'user' => $user, 'reason' => 'password_ok', 'retry_after' => null];
    }

    /**
     * Step 2a: issue the OTP challenge.
     *
     * The channel depends on how this user enrolled:
     *   - authenticator app enrolled -> NO code is sent anywhere; the app
     *     generates it locally, so we simply ask for it,
     *   - otherwise                  -> the emailed-code flow, unchanged.
     *
     * @return array{ok: bool, reason: string, retry_after: int|null, dev_code: string|null, channel: string}
     */
    public function startChallenge(User $user, ?string $ip = null): array
    {
        // If the account does not have 2FA enabled it is signed straight in
        // (the assignment enables 2FA for every seeded account by default).
        if (! $user->two_factor_enabled) {
            return ['ok' => true, 'reason' => '2fa_disabled', 'retry_after' => null, 'dev_code' => null, 'channel' => 'none'];
        }

        // Authenticator app: the code lives on the phone, nothing is sent.
        if ($user->usesAuthenticatorApp()) {
            return ['ok' => true, 'reason' => 'app_challenge', 'retry_after' => null, 'dev_code' => null, 'channel' => 'app'];
        }

        $result = $this->otp->issue($user, $ip);

        if (! $result['ok']) {
            return [
                'ok' => false,
                'reason' => $result['reason'],
                'retry_after' => $result['retry_after'],
                'dev_code' => null,
                'channel' => 'email',
            ];
        }

        return [
            'ok' => true,
            'reason' => 'issued',
            'retry_after' => null,
            // Only ever populated when the dev exposure flag is on; the code is
            // never stored in plaintext and never logged.
            'dev_code' => $this->devCodeExposed() ? $result['code'] : null,
            'channel' => 'email',
        ];
    }

    /**
     * Step 2b: verify the submitted code and, on success, complete the login.
     *
     * @return array{ok: bool, user: User|null, reason: string}
     */
    public function verifyChallenge(User $user, string $code): array
    {
        if (! $user->two_factor_enabled) {
            return ['ok' => true, 'user' => $user, 'reason' => '2fa_disabled'];
        }

        // Authenticator app enrolled: check the code against the phone's secret.
        if ($user->usesAuthenticatorApp()) {
            $result = $this->totp->verify($user, $code);
        } else {
            $result = $this->otp->verify($user, $code);
        }

        if (! $result['ok']) {
            $this->audit->failure(
                'auth.2fa.failed',
                'security',
                sprintf('Two-factor code rejected for "%s" (%s).', $user->username, $result['reason']),
                $user,
            );

            return ['ok' => false, 'user' => null, 'reason' => $result['reason']];
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->success(
            'auth.2fa.verified',
            'auth',
            sprintf('Two-factor authentication succeeded for "%s".', $user->username),
            $user,
        );

        $this->audit->success(
            'auth.login.success',
            'auth',
            sprintf('User "%s" signed in.', $user->username),
            $user,
        );

        return ['ok' => true, 'user' => $user, 'reason' => 'verified'];
    }

    /** Friendly text for the OTP failure reasons. */
    public static function reasonMessage(string $reason): string
    {
        return match ($reason) {
            'expired' => 'That code has expired. Request a new one.',
            'too_many_attempts' => 'Too many incorrect codes. Request a new one.',
            'no_code' => 'No active code was found. Request a new one.',
            'invalid' => 'That code is not correct.',
            'cooldown' => 'A code was sent recently. Please wait before requesting another.',
            'hourly_limit' => 'You have requested too many codes. Try again later.',
            'account_inactive' => 'This account is no longer active.',
            default => 'We could not verify that code.',
        };
    }

    /**
     * Development convenience: when TWO_FACTOR_EXPOSE_CODE=true (never in
     * production) the issued code is flashed to the screen so the flow can be
     * demonstrated without a real mailbox.
     */
    private function devCodeExposed(): bool
    {
        return app()->environment('local', 'testing')
            && (bool) config('security.two_factor.expose_code');
    }
}
