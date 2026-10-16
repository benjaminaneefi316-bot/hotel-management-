<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the two-factor challenge pages.
 *
 * After a correct password the user is NOT logged in yet: only the user id is
 * placed in the session ("pending 2FA"). These pages must therefore be
 * reachable by a guest session, but only when a pending challenge exists and
 * it has not expired.
 */
class EnsurePendingTwoFactorChallenge
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get((string) config('security.pending_2fa_session_key'));
        $startedAt = $request->session()->get((string) config('security.pending_2fa_started_key'));

        if (! $userId || ! $startedAt) {
            return redirect()->route('login')->withErrors([
                'email' => 'Please sign in to continue.',
            ]);
        }

        // The challenge must be completed quickly (default: 10 minutes).
        if (now()->diffInMinutes((string) $startedAt, true) > (int) config('security.two_factor_challenge_minutes', 10)) {
            $request->session()->forget([
                (string) config('security.pending_2fa_session_key'),
                (string) config('security.pending_2fa_started_key'),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Your verification session expired. Please sign in again.',
            ]);
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->isActive()) {
            $request->session()->forget([
                (string) config('security.pending_2fa_session_key'),
                (string) config('security.pending_2fa_started_key'),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'This account is no longer available.',
            ]);
        }

        return $next($request);
    }
}
