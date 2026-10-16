<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Liveness probe for the deployment platform and the mobile client.
 *
 * Implemented as a controller action (not a route closure) so that
 * `php artisan route:cache` can serialise the route table — Laravel cannot
 * cache routes whose action is a Closure, and the container runs route:cache
 * on every boot.
 */
final class ApiHealthController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
