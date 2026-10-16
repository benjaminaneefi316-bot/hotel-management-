<?php

declare(strict_types=1);

/**
 * Configuration-only restore points.
 *
 * A "restore point" captures APPROVED CONFIGURATION values (and, optionally,
 * role assignments) so an administrator can roll the *settings* back without
 * touching business data. Guests, bookings, stays, payments and audit history
 * are NEVER part of a snapshot and are NEVER modified by a restore.
 *
 * Only the keys listed in `eligible_settings` may be captured. Anything not on
 * the list is ignored, which keeps secret or security-critical configuration
 * (2FA enforcement, login throttling, encryption keys, passwords, peppers)
 * permanently out of the snapshot/restore path.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Snapshot schema version
    |--------------------------------------------------------------------------
    | Stored on each snapshot so a future change to the item format can be
    | detected and migrated instead of restoring incompatible data.
    */
    'schema_version' => '1.0',

    /*
    |--------------------------------------------------------------------------
    | Eligible settings (WHITELIST)
    |--------------------------------------------------------------------------
    | These are operational settings an administrator may safely roll back.
    */
    'eligible_settings' => [
        // Hotel identity
        'hotel_name',
        'hotel_tagline',
        'hotel_email',
        'hotel_phone',
        'hotel_address',
        'support_email',
        'hotel_currency',
        'hotel_timezone',

        // Stay policy
        'check_in_time',
        'check_out_time',
        'booking_lead_time_days',
        'max_advance_booking_days',
        'cancellation_window_hours',

        // Financials
        'tax_rate',
        'service_charge_rate',
        'default_nightly_rate',

        // Loyalty / invoicing
        'loyalty_points_per_night',
        'invoice_footer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Never snapshotted
    |--------------------------------------------------------------------------
    | Defensively excluded even if someone adds them to the whitelist. Matched
    | case-insensitively as a substring of the setting key.
    */
    'never_snapshot' => [
        'password', 'passwd', 'salt', 'pepper', 'secret', 'token',
        'otp', 'code', 'key', 'credential', 'access', 'private',
        'session', 'cookie', 'csrf', '2fa', 'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role assignments
    |--------------------------------------------------------------------------
    | When enabled, the role set of every non-archived user is captured too, so
    | a restore can revert an accidental privilege change. This is OFF by
    | default because most teams only want settings restored.
    */
    'include_role_assignments_by_default' => false,

    /*
    |--------------------------------------------------------------------------
    | Restore safety
    |--------------------------------------------------------------------------
    */
    'restore' => [
        // Always keep at least one active administrator after a restore.
        'protect_last_admin' => true,
        // Wrap the restore in a single transaction and roll back on any error.
        'transactional' => true,
        // Require the admin to type the snapshot name to confirm.
        'require_confirmation_text' => true,
    ],
];
