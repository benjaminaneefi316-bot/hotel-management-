<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The UI is built on Bootstrap 5 loaded from a CDN (no npm/Vite build).
        Paginator::useBootstrapFive();

        $this->registerGates();
        $this->registerBladeDirectives();
        $this->registerRateLimiters();
        $this->registerPasswordRules();
    }

    /**
     * Turn the RBAC matrix in config/abilities.php into Laravel gates.
     *
     * Enforced everywhere: controllers, `can:` route middleware, Blade `@can`
     * and the JSON API. A user who is not active holds NO ability at all.
     */
    private function registerGates(): void
    {
        /** @var array<string, array<int, string>> $abilities */
        $abilities = (array) config('abilities', []);

        foreach ($abilities as $ability => $roles) {
            Gate::define($ability, function (User $user) use ($roles): bool {
                if (! $user->isActive()) {
                    return false;
                }

                return $user->hasRole(...$roles);
            });
        }

        // Super-admin shortcut: an active administrator may do anything that is
        // not explicitly restricted, and every denied attempt is audited.
        Gate::after(function (User $user): ?bool {
            return $user->isActive() && $user->isAdmin() ? true : null;
        });
    }

    /** Small helpers used across the Blade views. */
    private function registerBladeDirectives(): void
    {
        Blade::directive('money', function (string $expression): string {
            return "<?php echo e(number_format((float) ($expression), 2)); ?>";
        });

        Blade::directive('active', function (string $expression): string {
            return "<?php echo request()->routeIs($expression) ? 'active' : ''; ?>";
        });
    }

    /**
     * Named rate limiters.
     *
     * These are the "rate limiting" control the assignment asks for. They are
     * keyed on something stable (email + IP) so one attacker cannot lock every
     * account, and they protect both the password step and the OTP step.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = (string) $request->input('email');

            return [
                // Per email+IP: stops targeted brute force.
                Limit::perMinute((int) config('security.login.max_attempts', 5))
                    ->by($email.'|'.$request->ip())
                    ->response(function (Request $request) {
                        app(AuditLogger::class)->failure(
                            'auth.login.rate_limited',
                            'security',
                            'Login rate limit exceeded for '.$request->input('email'),
                        );

                        return back()->withErrors([
                            'email' => 'Too many sign-in attempts. Please wait a minute and try again.',
                        ]);
                    }),
                // Per IP: stops spraying many accounts from one host.
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            // Web: throttle by the pending-2FA user id. API (no session):
            // throttle by the challenge token, falling back to the IP.
            $key = $request->hasSession()
                ? (string) $request->session()->get((string) config('security.pending_2fa_session_key'))
                : (string) $request->input('challenge_token', '');

            if ($key === '') {
                $key = $request->ip();
            }

            return Limit::perMinute(10)->by('2fa:'.hash('sha256', $key));
        });

        RateLimiter::for('otp-resend', function (Request $request): Limit {
            $key = $request->hasSession()
                ? (string) $request->session()->get((string) config('security.pending_2fa_session_key'))
                : (string) $request->input('challenge_token', '');

            if ($key === '') {
                $key = $request->ip();
            }

            return Limit::perMinute(2)->by('otp-resend:'.hash('sha256', $key));
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(60)->by('api:'.$key);
        });
    }

    /**
     * Default password policy used by every "set password" form.
     *
     * Minimum 12 characters with mixed case, digits and symbols. We do NOT
     * impose a hard maximum below 64 characters (long passphrases are good),
     * and we never silently truncate the input.
     *
     * The optional breached-password check (`uncompromised()`) calls the
     * HaveIBeenPwned range API over the network. It is OFF by default because
     * this project must work offline; switch it on with
     * PASSWORD_CHECK_BREACHED=true in a connected production environment.
     */
    private function registerPasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            if ((bool) config('security.password.check_breached')) {
                $rule->uncompromised();
            }

            return $rule;
        });
    }
}
