<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks any request made by an account that is no longer active.
 *
 * Archiving or deactivating a user takes effect IMMEDIATELY, even for a browser
 * that was already signed in: the session is invalidated on the next request.
 * This is what makes "an archived account cannot authenticate" hold for
 * existing sessions too, not just for new logins.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->status !== UserStatus::Active) {
            $reason = $user->status === UserStatus::Archived
                ? 'This account has been archived.'
                : 'This account has been deactivated.';

            // Web sessions must be torn down immediately. API requests are
            // stateless (bearer tokens) and have no session, so guard for it.
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $reason], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('login')->withErrors(['email' => $reason]);
        }

        return $next($request);
    }
}
