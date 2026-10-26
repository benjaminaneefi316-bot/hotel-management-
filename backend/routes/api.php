<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| REST API (consumed by the Flutter mobile application)
|--------------------------------------------------------------------------
| All responses are JSON. Authenticated endpoints use Laravel Sanctum bearer
| tokens obtained from POST /api/auth/two-factor. Rate limiting is applied per
| route group. See docs/API.md for the full contract.
*/

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiBookingController;
use App\Http\Controllers\Api\ApiDashboardController;
use App\Http\Controllers\Api\ApiFrontDeskController;
use App\Http\Controllers\Api\ApiHealthController;
use App\Http\Controllers\Api\ApiPaymentController;
use App\Http\Controllers\Api\ApiRoomController;
use App\Http\Controllers\Api\ApiStayController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [ApiHealthController::class, 'show']);

// --- Public ------------------------------------------------------------------
Route::get('/room-types', [ApiRoomController::class, 'roomTypes']);
Route::get('/availability', [ApiRoomController::class, 'availability']);

// --- Authentication ---------------------------------------------------------
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [ApiAuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/two-factor', [ApiAuthController::class, 'twoFactor'])->middleware('throttle:two-factor');
    Route::post('/resend', [ApiAuthController::class, 'resend'])->middleware('throttle:otp-resend');
});

// --- Authenticated ----------------------------------------------------------
Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/auth/me', [ApiAuthController::class, 'me']);
    Route::post('/auth/logout', [ApiAuthController::class, 'logout']);
    Route::put('/auth/password', [ApiAuthController::class, 'changePassword']);

    // Bookings - staff see everything, a guest sees only their own.
    Route::get('/bookings', [ApiBookingController::class, 'index']);
    Route::post('/bookings', [ApiBookingController::class, 'store']);
    Route::get('/bookings/{booking}', [ApiBookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [ApiBookingController::class, 'cancel']);

    Route::middleware('can:manage-bookings')->group(function (): void {
        Route::post('/bookings/{booking}/confirm', [ApiBookingController::class, 'confirm']);
    });

    // Front desk (admin + receptionist only).
    Route::middleware('can:check-in-out')->group(function (): void {
        Route::get('/front-desk', [ApiFrontDeskController::class, 'index']);
        Route::post('/bookings/{booking}/check-in', [ApiStayController::class, 'checkIn']);
        Route::post('/stays/{stay}/check-out', [ApiStayController::class, 'checkOut']);
    });

    // Staff operations.
    Route::middleware('can:access-staff-area')->group(function (): void {
        Route::get('/dashboard/summary', [ApiDashboardController::class, 'summary']);
        Route::get('/rooms', [ApiRoomController::class, 'index']);
        Route::post('/payments', [ApiPaymentController::class, 'store'])->middleware('can:manage-payments');
    });
});
