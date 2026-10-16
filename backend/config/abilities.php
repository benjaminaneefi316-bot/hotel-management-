<?php

declare(strict_types=1);

/**
 * Server-side RBAC matrix.
 *
 * Every ability maps to the roles that hold it. These are registered as
 * Laravel gates in AppServiceProvider, so they are enforced in controllers,
 * routes (`can:` middleware), Blade (`@can`) and the API - and cannot be
 * bypassed by hiding a button in the UI.
 *
 * Role definitions:
 *   admin        - full access
 *   receptionist - day-to-day front-desk operations
 *   guest        - self-service only
 */
return [

    // Navigation / area access
    'access-staff-area' => ['admin', 'receptionist'],
    'access-guest-area' => ['admin', 'receptionist', 'guest'],
    'view-dashboard' => ['admin', 'receptionist'],

    // User & role administration
    'manage-users' => ['admin'],
    'manage-roles' => ['admin'],
    'view-archived-users' => ['admin'],
    'manage-own-profile' => ['admin', 'receptionist', 'guest'],

    // Rooms
    'manage-room-types' => ['admin'],
    'manage-rooms' => ['admin'],
    'update-room-status' => ['admin', 'receptionist'],

    // Guests
    'manage-guests' => ['admin', 'receptionist'],
    'view-guest-sensitive-data' => ['admin', 'receptionist'],

    // Bookings
    'manage-bookings' => ['admin', 'receptionist'],
    'view-own-bookings' => ['admin', 'receptionist', 'guest'],
    'book-own-room' => ['guest'],

    // Front desk
    'check-in-out' => ['admin', 'receptionist'],

    // Money
    'manage-payments' => ['admin', 'receptionist'],
    'view-reports' => ['admin'],
    'export-reports' => ['admin'],

    // System administration
    'manage-settings' => ['admin'],
    'manage-backups' => ['admin'],
    'manage-restore-points' => ['admin'],
    'view-audit-logs' => ['admin'],
];
