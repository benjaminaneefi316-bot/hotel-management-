<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function show(Request $request): View
    {
        $devCode = $request->session()->pull('two_factor.dev_code');
        $user = $this->pendingUser($request);

        // Which second factor this account actually uses: the code generated
        // by the authenticator app, or the one we emailed.
        $channel = $user?->usesAuthenticatorApp() ? 'app' : 'email';

        return view('auth.two-factor', [
            'devCode' => $devCode,
            'channel' => $channel,
            'canResend' => $channel === 'email',
        ]);
    }

    /**
     * Step 2 of authentication: verify the emailed OTP.
     *
     * ONLY on success is the user actually signed in and the pending markers
     * cleared.
     */
    public function verify(TwoFactorRequest $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login')->withErrors(['email' => 'Please sign in to continue.']);
        }

        $result = $this->auth->verifyChallenge($user, $request->validated()['code']);

        if (! $result['ok'] || $result['user'] === null) {
            return back()->withErrors(['code' => AuthService::reasonMessage($result['reason'])]);
        }

        Auth::login($result['user']);
        $request->session()->regenerate();
        $this->clearPending($request);

        return redirect()->intended($this->homeFor($result['user']))
            ->with('status', 'Welcome back, '.$result['user']->name.'.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login')->withErrors(['email' => 'Please sign in to continue.']);
        }

        $result = $this->auth->startChallenge($user, $request->ip());

        if (! $result['ok']) {
            return back()->withErrors(['code' => AuthService::reasonMessage($result['reason'])]);
        }

        if (! empty($result['dev_code'])) {
            $request->session()->put('two_factor.dev_code', $result['dev_code']);
        }

        return back()->with('status', 'A new verification code is on its way.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = (int) $request->session()->get((string) config('security.pending_2fa_session_key'));

        return $userId > 0 ? User::query()->find($userId) : null;
    }

    private function clearPending(Request $request): void
    {
        $request->session()->forget([
            (string) config('security.pending_2fa_session_key'),
            (string) config('security.pending_2fa_started_key'),
            'two_factor.dev_code',
        ]);
    }
}
