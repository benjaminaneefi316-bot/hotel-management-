<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function show(): View
    {
        return view('auth.login');
    }

    /**
     * Step 1 of authentication: verify the password.
     *
     * A correct password does NOT sign the user in. Instead we email an OTP and
     * place only the user id + a timestamp in the session ("pending 2FA").
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $result = $this->auth->attemptPassword($data['email'], $data['password'], $request->ip());

        if (! $result['ok'] || $result['user'] === null) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $result['reason'] === 'account_inactive'
                        ? 'This account is not active. Please contact an administrator.'
                        : 'These credentials do not match our records.',
                ]);
        }

        $user = $result['user'];
        $challenge = $this->auth->startChallenge($user, $request->ip());

        // 2FA disabled for this account: sign in immediately.
        if ($challenge['ok'] && $challenge['reason'] === '2fa_disabled') {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended($this->homeFor($user))
                ->with('status', 'Welcome back, '.$user->name.'.');
        }

        $request->session()->put((string) config('security.pending_2fa_session_key'), $user->getKey());
        $request->session()->put((string) config('security.pending_2fa_started_key'), now()->toIso8601String());

        if (! empty($challenge['dev_code'])) {
            $request->session()->put('two_factor.dev_code', $challenge['dev_code']);
        }

        $request->session()->regenerate();

        $redirect = redirect()->route('two-factor.challenge');

        if ($challenge['channel'] === 'app') {
            return $redirect->with('status', 'Open your authenticator app to get your verification code.');
        }

        if ($challenge['ok']) {
            return $redirect->with('status', 'We emailed a verification code to '.$user->email.'.');
        }

        // Cooldown / hourly-limit: a previous code may still be valid.
        return $redirect->with('error', AuthService::reasonMessage($challenge['reason']));
    }
}
