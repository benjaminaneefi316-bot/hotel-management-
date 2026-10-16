<?php

declare(strict_types=1);

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePendingTwoFactorChallenge;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aliases available to routes.
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'pending-2fa' => EnsurePendingTwoFactorChallenge::class,
        ]);

        // An account that is archived/deactivated is logged out immediately,
        // even with a live session.
        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);

        // API requests are authenticated with Sanctum tokens, and are stateless.
        $middleware->statefulApi();

        // Apply the general API rate limiter (60/min) to the whole /api group.
        $middleware->throttleApi();

        // Unauthenticated browser visits go to the login page.
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin() || $request->user()?->isReceptionist()
            ? route('dashboard')
            : route('guest.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Business-rule violations (double booking, last-admin protection,
         * checking out an inactive stay, ...) are the user's problem, not a
         * server fault: render them as 422 with a readable message.
         */
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['business_rule' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors(['business_rule' => $e->getMessage()]);
        });

        // Never leak internals for the "not allowed" page.
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You are not allowed to perform this action.'], 403);
            }

            return response()->view('errors.403', [
                'message' => 'You are not allowed to perform this action.',
            ], 403);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        });

        // Any other HTTP error keeps its status code but uses our styled page.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            if (view()->exists("errors.{$status}")) {
                return response()->view("errors.{$status}", ['message' => $e->getMessage()], $status);
            }

            return null;
        });
    })->create();
