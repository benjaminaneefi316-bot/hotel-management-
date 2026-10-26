<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Authenticator-app enrolment from inside the mobile client.
 *
 * The same three steps as the website, but the pending secret lives in the
 * cache (keyed by user id) instead of the browser session:
 *
 *   1. setup   - create (or re-use) a pending secret and hand it back together
 *                with the otpauth:// URI. Nothing is written to the account.
 *   2. confirm - prove a live code can be read; only then is the secret saved.
 *   3. destroy - switch the second factor off entirely.
 *
 * The plain secret is only ever returned to the authenticated owner, never
 * logged, and never included in user payloads.
 */
final class ApiTwoFactorController extends ApiController
{
    private const PENDING_TTL_MINUTES = 15;

    public function __construct(
        private readonly TotpService $totp,
        private readonly AuditLogger $audit,
    ) {}

    /** Current state of the caller's second factor. */
    public function show(Request $request): JsonResponse
    {
        return $this->ok($this->statusPayload($request->user()));
    }

    /** Step 1: create or re-use the pending secret. */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->usesAuthenticatorApp()) {
            return $this->fail(
                'An authenticator app is already connected to this account.',
                409,
                'already_enabled',
            );
        }

        $key = $this->cacheKey($user);
        $pending = Cache::get($key);

        $fresh = is_array($pending)
            && is_string($pending['secret'] ?? null)
            && $pending['secret'] !== ''
            && isset($pending['started_at'])
            && now()->lessThan(\Illuminate\Support\Carbon::parse($pending['started_at'])->addMinutes(self::PENDING_TTL_MINUTES));

        if (! $fresh) {
            $pending = [
                'secret' => $this->totp->generateSecret(),
                'started_at' => now()->toIso8601String(),
            ];

            Cache::put($key, $pending, now()->addMinutes(self::PENDING_TTL_MINUTES));
        }

        $issuer = (string) config('security.two_factor.issuer');
        $uri = $this->totp->provisioningUri($pending['secret'], (string) $user->email, $issuer);

        return $this->ok([
            'status' => 'pending',
            // Typed by hand when scanning is not possible (same phone, etc.).
            'secret' => $pending['secret'],
            'otpauth_uri' => $uri,
            'issuer' => $issuer,
            'account' => $user->email,
            'expires_in' => self::PENDING_TTL_MINUTES * 60,
            'instructions' => 'Add this account to your authenticator app, then enter the 6-digit code it shows.',
        ]);
    }

    /** Step 2: check a live code and persist the secret. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ], [
            'code.regex' => 'Enter the 6-digit code shown in your authenticator app.',
            'code.size' => 'Enter the 6-digit code shown in your authenticator app.',
        ]);

        $user = $request->user();

        if ($user->usesAuthenticatorApp()) {
            return $this->fail(
                'An authenticator app is already connected to this account.',
                409,
                'already_enabled',
            );
        }

        $key = $this->cacheKey($user);
        $pending = Cache::get($key);

        if (! is_array($pending) || ! is_string($pending['secret'] ?? null) || $pending['secret'] === '') {
            return $this->fail('That setup expired. Please start again.', 422, 'setup_expired');
        }

        if (isset($pending['started_at'])
            && now()->greaterThan(\Illuminate\Support\Carbon::parse($pending['started_at'])->addMinutes(self::PENDING_TTL_MINUTES))) {
            Cache::forget($key);

            return $this->fail('That setup expired. Please start again.', 422, 'setup_expired');
        }

        $probe = new User();
        $probe->two_factor_secret = $pending['secret'];
        $probe->id = $user->getKey();

        $result = $this->totp->verify($probe, $data['code']);

        if (! $result['ok']) {
            $this->audit->failure(
                'auth.2fa.enrol.failed',
                'security',
                sprintf('Authenticator enrolment via the app rejected a code for "%s".', $user->username),
                $user,
            );

            return $this->fail('That code is not correct. Check your app and try again.', 422, 'invalid_code');
        }

        $user->forceFill([
            'two_factor_secret' => $pending['secret'],
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled' => true,
        ])->save();

        Cache::forget($key);

        $this->audit->success(
            'auth.2fa.enrol.success',
            'security',
            sprintf('Authenticator app enrolled via the app for "%s".', $user->username),
            $user,
        );

        return $this->ok($this->statusPayload($user->fresh()));
    }

    /** Step 3: switch the second factor off and forget the secret. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = $request->user();

        if (! $user->usesAuthenticatorApp()) {
            return $this->fail('Two-factor authentication is not enabled.', 422, 'not_enabled');
        }

        $result = $this->totp->verify($user, $data['code']);

        if (! $result['ok']) {
            $this->audit->failure(
                'auth.2fa.enrol.disable_failed',
                'security',
                sprintf('Wrong authenticator code while disabling 2FA for "%s".', $user->username),
                $user,
            );

            return $this->fail('That code is not correct. Check your app and try again.', 422, 'invalid_code');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_enabled' => false,
        ])->save();

        $this->audit->success(
            'auth.2fa.enrol.removed',
            'security',
            sprintf('Authenticator app removed via the app for "%s"; emailed codes are in use.', $user->username),
            $user,
        );

        return $this->ok($this->statusPayload($user->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(User $user): array
    {
        return [
            'enabled' => $user->usesAuthenticatorApp(),
            'channel' => $user->usesAuthenticatorApp() ? 'app' : 'email',
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ];
    }

    private function cacheKey(User $user): string
    {
        return 'api:2fa:setup:'.$user->getKey();
    }
}
