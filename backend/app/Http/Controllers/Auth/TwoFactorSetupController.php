<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Enrolling an authenticator app (Google Authenticator, Authy, 1Password, ...).
 *
 * Deliberately three steps, because getting this wrong locks people out:
 *
 *   1. start  - a secret is created and held ONLY in the session. The user
 *               scans the QR. Nothing is written to their account yet, so
 *               closing the tab simply discards the attempt.
 *   2. confirm- the user proves they can read a live code off the app. Only
 *               now is the secret saved and the account switched over.
 *   3. disable- an account can be moved back to emailed codes at any time.
 *
 * Until step 2 completes, the account keeps using emailed codes exactly as
 * before - there is no state in which a half-finished setup blocks a login.
 */
final class TwoFactorSetupController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuditLogger $audit,
    ) {}

    /** Step 1: create a pending secret and show the QR code. */
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->usesAuthenticatorApp()) {
            return view('auth.two-factor-setup', [
                'stage' => 'active',
                'user' => $user,
                'qrSvg' => null,
                'secret' => null,
            ]);
        }

        // Re-opening the page must NOT silently swap the QR code underneath
        // someone who is mid-setup: reuse the pending secret while it is fresh.
        $secret = $request->session()->get('2fa.pending_secret');
        $startedAt = $request->session()->get('2fa.pending_started_at');

        $fresh = is_string($secret) && $secret !== ''
            && $startedAt !== null
            && now()->lessThan(\Illuminate\Support\Carbon::parse($startedAt)->addMinutes(15));

        if (! $fresh) {
            $secret = $this->totp->generateSecret();
            $request->session()->put('2fa.pending_secret', $secret);
            $request->session()->put('2fa.pending_started_at', now()->toIso8601String());
        }

        $uri = $this->totp->provisioningUri(
            $secret,
            $user->email,
            (string) config('security.two_factor.issuer'),
        );

        return view('auth.two-factor-setup', [
            'stage' => 'pending',
            'user' => $user,
            'qrSvg' => $this->totp->qrSvg($uri),
            // Shown as text for anyone who cannot scan (or prefers to type it).
            'secret' => $secret,
        ]);
    }

    /** Step 2: verify a live code, then persist the secret for real. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ], [
            'code.regex' => 'Enter the 6-digit code shown in your authenticator app.',
            'code.size' => 'Enter the 6-digit code shown in your authenticator app.',
        ]);

        // Read (not pull): a wrong code must leave the setup intact so the
        // user can simply try the next code instead of scanning again.
        $secret = $request->session()->get('2fa.pending_secret');

        if (! is_string($secret) || $secret === '') {
            return back()->withErrors(['code' => 'That setup expired. Please start again.']);
        }

        // Pending secrets older than 15 minutes are discarded.
        $started = $request->session()->get('2fa.pending_started_at');
        if ($started !== null && now()->greaterThan(\Illuminate\Support\Carbon::parse($started)->addMinutes(15))) {
            $request->session()->forget(['2fa.pending_secret', '2fa.pending_started_at']);

            return back()->withErrors(['code' => 'That setup expired. Please start again.']);
        }

        // Verify against a throwaway model so the account itself is untouched
        // until we know the user can genuinely read the app.
        $probe = new User();
        $probe->two_factor_secret = $secret;
        $probe->id = $user->getKey();

        $result = $this->totp->verify($probe, $data['code']);

        if (! $result['ok']) {
            $this->audit->failure(
                'auth.2fa.enrol.failed',
                'security',
                sprintf('Authenticator enrolment rejected a code for "%s".', $user->username),
                $user,
            );

            return back()->withErrors(['code' => 'That code is not correct. Check your app and try again.']);
        }

        // Recovery codes are intentionally NOT minted here: they are not stored
        // or accepted anywhere yet, so handing them out would be a false promise.
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled' => true,
        ])->save();

        // Setup is complete: drop the pending copy from the session.
        $request->session()->forget(['2fa.pending_secret', '2fa.pending_started_at']);

        $this->audit->success(
            'auth.2fa.enrol.success',
            'security',
            sprintf('Authenticator app enrolled for "%s".', $user->username),
            $user,
        );

        return redirect()
            ->route('two-factor.setup')
            ->with('status', 'Authenticator app connected. Future sign-ins will ask for its code.');
    }

    /** Step 3: switch back to emailed codes and forget the secret. */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget(['2fa.pending_secret', '2fa.pending_started_at']);

        $this->audit->success(
            'auth.2fa.enrol.removed',
            'security',
            sprintf('Authenticator app removed for "%s"; emailed codes are in use.', $user->username),
            $user,
        );

        return back()->with('status', 'Authenticator app removed. Sign-in codes are emailed again.');
    }
}
