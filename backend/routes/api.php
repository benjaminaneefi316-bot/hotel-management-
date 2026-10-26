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
use App\Http\Controllers\Api\ApiHotelController;
use App\Http\Controllers\Api\ApiPaymentController;
use App\Http\Controllers\Api\ApiRoomController;
use App\Http\Controllers\Api\ApiStayController;
use App\Http\Controllers\Api\ApiTwoFactorController;
use App\Http\Controllers\Api\ApiUserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [ApiHealthController::class, 'show']);

// --- Public ------------------------------------------------------------------
Route::get('/room-types', [ApiRoomController::class, 'roomTypes']);
Route::get('/availability', [ApiRoomController::class, 'availability']);
Route::get('/hotel', [ApiHotelController::class, 'show']);
Route::post('/hotel/contact', [ApiHotelController::class, 'contact'])->middleware('throttle:10,1');

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

    // Authenticator-app enrolment for the signed-in user.
    Route::get('/two-factor', [ApiTwoFactorController::class, 'show']);
    Route::post('/two-factor/setup', [ApiTwoFactorController::class, 'setup'])->middleware('throttle:6,1');
    Route::post('/two-factor/confirm', [ApiTwoFactorController::class, 'confirm'])->middleware('throttle:6,1');
    Route::delete('/two-factor', [ApiTwoFactorController::class, 'destroy'])->middleware('throttle:6,1');

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

        Route::middleware('can:manage-payments')->group(function (): void {
            Route::get('/payments', [ApiPaymentController::class, 'index']);
            Route::get('/payments/summary', [ApiPaymentController::class, 'summary']);
            Route::post('/payments/{payment}/refund', [ApiPaymentController::class, 'refund']);
        });
    });

    // User administration (administrators only).
    Route::middleware('can:manage-users')->group(function (): void {
        Route::get('/users', [ApiUserController::class, 'index']);
        Route::get('/users/roles', [ApiUserController::class, 'roles']);
        Route::post('/users', [ApiUserController::class, 'store']);
        Route::get('/users/{user}', [ApiUserController::class, 'show']);
        Route::put('/users/{user}', [ApiUserController::class, 'update']);
        Route::put('/users/{user}/roles', [ApiUserController::class, 'updateRoles']);
        Route::post('/users/{user}/activate', [ApiUserController::class, 'activate']);
        Route::post('/users/{user}/deactivate', [ApiUserController::class, 'deactivate']);
        Route::post('/users/{user}/password', [ApiUserController::class, 'resetPassword']);
    });
});
