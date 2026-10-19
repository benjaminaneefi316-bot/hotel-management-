<?php

declare(strict_types=1);

use App\Http\Controllers\ArchivedUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontDeskController;
use App\Http\Controllers\Guest\BookingController as GuestBookingController;
use App\Http\Controllers\Guest\DashboardController as GuestDashboardController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\AccommodationController;
use App\Http\Controllers\Public\BookingSearchController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RestorePointController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StayController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public marketing site (no authentication)
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('public.home');
Route::get('/gallery', [HomeController::class, 'gallery'])->name('public.gallery');
Route::get('/about', [HomeController::class, 'about'])->name('public.about');

Route::get('/accommodation', [AccommodationController::class, 'index'])->name('public.accommodation');
Route::get('/accommodation/{roomType:slug}', [AccommodationController::class, 'show'])->name('public.accommodation.show');

Route::get('/contact', [ContactController::class, 'show'])->name('public.contact');
Route::post('/contact', [ContactController::class, 'store'])->name('public.contact.store');

Route::get('/booking', [BookingSearchController::class, 'index'])->name('public.booking');
Route::post('/booking/search', [BookingSearchController::class, 'search'])->name('public.booking.search');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.attempt');
});

// The password step does NOT log the user in: only a "pending 2FA" session
// marker is set, and these pages are reachable while still a guest.
Route::middleware('pending-2fa')->group(function (): void {
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verify'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.verify');
    Route::post('/two-factor/resend', [TwoFactorController::class, 'resend'])
        ->middleware('throttle:otp-resend')
        ->name('two-factor.resend');
});

Route::post('/logout', [LogoutController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated area (active accounts only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function (): void {

    // --- Staff dashboard ------------------------------------------------------
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:view-dashboard')
        ->name('dashboard');

    // --- Own profile ----------------------------------------------------------
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // --- Authenticator app (TOTP) enrolment -----------------------------------
    Route::get('/profile/two-factor', [TwoFactorSetupController::class, 'create'])
        ->name('two-factor.setup');
    Route::post('/profile/two-factor', [TwoFactorSetupController::class, 'confirm'])
        ->middleware('throttle:two-factor')
        ->name('two-factor.confirm');
    Route::delete('/profile/two-factor', [TwoFactorSetupController::class, 'destroy'])
        ->name('two-factor.remove');

    // --- Guest self-service portal -------------------------------------------
    Route::middleware('can:access-guest-area')->prefix('guest')->name('guest.')->group(function (): void {
        Route::get('/dashboard', [GuestDashboardController::class, 'index'])->name('dashboard');
        Route::get('/bookings', [GuestBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [GuestBookingController::class, 'create'])->name('bookings.create');
        Route::post('/bookings', [GuestBookingController::class, 'store'])->name('bookings.store');
        Route::get('/bookings/{booking}', [GuestBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [GuestBookingController::class, 'cancel'])->name('bookings.cancel');
    });

    /*
    |----------------------------------------------------------------------
    | Staff back-office (admin + receptionist)
    |----------------------------------------------------------------------
    */
    Route::middleware('can:access-staff-area')->group(function (): void {

        Route::get('/front-desk', [FrontDeskController::class, 'index'])->name('front-desk.index');

        // --- Users ----------------------------------------------------------
        Route::middleware('can:manage-users')->group(function (): void {
            Route::resource('users', UserController::class)->except(['destroy']);
            Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
            Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
            Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('/users/{user}/archive', [UserController::class, 'archive'])->name('users.archive');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        });

        // --- Archived users -------------------------------------------------
        Route::middleware('can:view-archived-users')->group(function (): void {
            Route::get('/archived-users', [ArchivedUserController::class, 'index'])->name('archived-users.index');
            Route::post('/archived-users/{archivedUser}/restore', [ArchivedUserController::class, 'restore'])->name('archived-users.restore');
        });

        // --- Roles ----------------------------------------------------------
        Route::middleware('can:manage-roles')->group(function (): void {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        });

        // --- Rooms ----------------------------------------------------------
        Route::post('/rooms/{room}/status', [RoomController::class, 'updateStatus'])
            ->middleware('can:update-room-status')
            ->name('rooms.status');

        Route::middleware('can:manage-rooms')->group(function (): void {
            Route::resource('rooms', RoomController::class);
        });

        // --- Room types -----------------------------------------------------
        Route::middleware('can:manage-room-types')->group(function (): void {
            Route::resource('room-types', RoomTypeController::class)->parameters(['room-types' => 'roomType']);
        });

        // --- Guests ---------------------------------------------------------
        Route::middleware('can:manage-guests')->group(function (): void {
            Route::resource('guests', GuestController::class);
        });

        // --- Bookings -------------------------------------------------------
        Route::middleware('can:manage-bookings')->group(function (): void {
            Route::get('/bookings/availability', [BookingController::class, 'availability'])->name('bookings.availability');
            Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
            Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');
            Route::post('/bookings/{booking}/no-show', [BookingController::class, 'noShow'])->name('bookings.no-show');
            Route::resource('bookings', BookingController::class)->except(['destroy']);
        });

        // --- Check-in / check-out -------------------------------------------
        Route::middleware('can:check-in-out')->group(function (): void {
            Route::post('/bookings/{booking}/check-in', [StayController::class, 'checkIn'])->name('bookings.check-in');
            Route::post('/stays/{stay}/check-out', [StayController::class, 'checkOut'])->name('stays.check-out');
        });

        // --- Payments -------------------------------------------------------
        Route::middleware('can:manage-payments')->group(function (): void {
            Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
            Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show']);
        });

        // --- Reports (admin) ------------------------------------------------
        Route::middleware('can:view-reports')->group(function (): void {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/occupancy', [ReportController::class, 'occupancy'])->name('reports.occupancy');
            Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
            Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
        });

        // --- Settings (admin) -----------------------------------------------
        Route::middleware('can:manage-settings')->group(function (): void {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        // --- Restore points (admin) -----------------------------------------
        Route::middleware('can:manage-restore-points')->group(function (): void {
            Route::get('/restore-points', [RestorePointController::class, 'index'])->name('restore-points.index');
            Route::get('/restore-points/create', [RestorePointController::class, 'create'])->name('restore-points.create');
            Route::post('/restore-points', [RestorePointController::class, 'store'])->name('restore-points.store');
            Route::get('/restore-points/{snapshot}', [RestorePointController::class, 'show'])->name('restore-points.show');
            Route::get('/restore-points/{snapshot}/preview', [RestorePointController::class, 'preview'])->name('restore-points.preview');
            Route::post('/restore-points/{snapshot}/restore', [RestorePointController::class, 'restore'])->name('restore-points.restore');
            Route::delete('/restore-points/{snapshot}', [RestorePointController::class, 'destroy'])->name('restore-points.destroy');
        });

        // --- Backups (admin) ------------------------------------------------
        Route::middleware('can:manage-backups')->group(function (): void {
            Route::post('/backups/prune', [BackupController::class, 'prune'])->name('backups.prune');
            Route::post('/backups/{backup}/verify', [BackupController::class, 'verify'])->name('backups.verify');
            Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
            Route::get('/backups/{backup}', [BackupController::class, 'show'])->name('backups.show');
            Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
        });

        // --- Audit logs (admin) ---------------------------------------------
        Route::middleware('can:view-audit-logs')->group(function (): void {
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        });
    });
});
