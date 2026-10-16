<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authentication for the mobile client: step 1 verifies the password, step 2
 * exchanges the emailed one-time code for a bearer token.
 *
 * The user is NOT authenticated after step 1. A short-lived, single-purpose
 * "challenge" token stands in for the pending-2FA session the browser uses, so
 * the two steps map cleanly onto a stateless API.
 */
final class ApiAuthController extends ApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->auth->attemptPassword($data['email'], $data['password'], $request->ip());

        if (! $result['ok'] || $result['user'] === null) {
            return $this->fail(
                $result['reason'] === 'account_inactive'
                    ? 'This account is not active.'
                    : 'The provided credentials are incorrect.',
                $result['reason'] === 'account_inactive' ? 403 : 401,
                $result['reason'],
            );
        }

        $user = $result['user'];
        $challenge = $this->auth->startChallenge($user, $request->ip());

        if (! $challenge['ok']) {
            return $this->fail(AuthService::reasonMessage($challenge['reason']), 429, $challenge['reason']);
        }

        if ($challenge['reason'] === '2fa_disabled') {
            return $this->ok([
                'status' => 'authenticated',
                'token' => $this->issueToken($user),
                'user' => $this->userPayload($user),
            ]);
        }

        $payload = [
            'status' => 'two_factor_required',
            'challenge_token' => $user->createToken('2fa-challenge', ['2fa:challenge'], now()->addMinutes(10))->plainTextToken,
            'expires_in' => 600,
            'email' => $user->email,
        ];

        if ($challenge['dev_code'] !== null) {
            $payload['dev_code'] = $challenge['dev_code'];
        }

        return $this->ok($payload);
    }

    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $token = $this->resolveChallengeToken($data['challenge_token']);

        if ($token === null) {
            return $this->fail('The verification session has expired. Please sign in again.', 401, 'challenge_expired');
        }

        /** @var User $user */
        $user = $token->tokenable;

        $result = $this->auth->verifyChallenge($user, $data['code']);

        if (! $result['ok']) {
            return $this->fail(AuthService::reasonMessage($result['reason']), 422, $result['reason']);
        }

        $token->delete();

        return $this->ok([
            'status' => 'authenticated',
            'token' => $this->issueToken($user),
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge_token' => ['required', 'string']]);

        $token = $this->resolveChallengeToken($data['challenge_token']);

        if ($token === null) {
            return $this->fail('The verification session has expired. Please sign in again.', 401, 'challenge_expired');
        }

        /** @var User $user */
        $user = $token->tokenable;
        $challenge = $this->auth->startChallenge($user, $request->ip());

        if (! $challenge['ok']) {
            return $this->fail(AuthService::reasonMessage($challenge['reason']), 429, $challenge['reason']);
        }

        if ($challenge['reason'] === '2fa_disabled') {
            return $this->fail('Two-factor authentication is not enabled for this account.', 400, '2fa_disabled');
        }

        $payload = ['status' => 'sent'];

        if ($challenge['dev_code'] !== null) {
            $payload['dev_code'] = $challenge['dev_code'];
        }

        return $this->ok($payload);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(['status' => 'signed_out']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if (! $user->verifyPassword($data['current_password'])['ok']) {
            return $this->fail('The current password is incorrect.', 422, 'invalid_password');
        }

        $user->setPassword($data['password']);
        $user->save();

        // Sign other devices out but keep the caller's token valid.
        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->getKey()))
            ->delete();

        return $this->ok(['status' => 'password_changed']);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('mobile', ['*'], now()->addDays(30))->plainTextToken;
    }

    /**
     * Resolve a pending-2FA challenge token, enforcing name, ability and expiry.
     */
    private function resolveChallengeToken(string $plainText): ?PersonalAccessToken
    {
        $token = PersonalAccessToken::findToken($plainText);

        if (! $token instanceof PersonalAccessToken || ! $token->can('2fa:challenge')) {
            return null;
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return null;
        }

        $user = $token->tokenable;

        if (! $user instanceof User || $user->status !== UserStatus::Active) {
            $token->delete();

            return null;
        }

        return $token;
    }
}
