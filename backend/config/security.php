<?php

declare(strict_types=1);

/**
 * Security-sensitive configuration.
 *
 * EVERYTHING here comes from environment variables so that no secret is ever
 * committed to Git. In production these values live in AWS Secrets Manager
 * (or EC2 instance IAM + SSM parameters) and are injected as environment
 * variables. See docs/SECURITY.md and docs/AWS_DEPLOYMENT.md.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Application password pepper
    |--------------------------------------------------------------------------
    | A secret value that is NEVER stored in the database. It is combined with
    | a per-user salt and the plaintext password before hashing, so a stolen
    | database dump alone cannot be cracked with a password dictionary.
    |
    | Generate one with:  php artisan security:generate-secrets
    */
    'pepper' => env('APP_PASSWORD_PEPPER'),

    /*
    | Previous peppers, comma separated. Used only to verify passwords that
    | were hashed with an older pepper during a pepper rotation. When such a
    | password verifies successfully it is transparently re-hashed with the
    | current pepper (see PasswordService::check()). Remove old peppers once
    | every user has logged in at least once.
    */
    'previous_peppers' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('APP_PASSWORD_PEPPER_PREVIOUS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Field encryption key (AES-256-GCM)
    |--------------------------------------------------------------------------
    | Base64-encoded 32-byte key, kept OUTSIDE the database. It protects
    | sensitive guest fields (phone, national id, address).
    */
    'encryption_key' => env('APP_ENCRYPTION_KEY'),
    'encryption_cipher' => 'aes-256-gcm',

    /*
    |--------------------------------------------------------------------------
    | Email OTP (second authentication factor)
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'purpose' => 'login_2fa',
        'length' => (int) env('OTP_LENGTH', 6),
        'expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 5),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
        'max_resends_per_hour' => (int) env('OTP_MAX_RESENDS_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Login throttling
    |--------------------------------------------------------------------------
    */
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session / 2FA challenge marker
    |--------------------------------------------------------------------------
    | The name of the session key that marks a user as "password verified but
    | 2FA still pending".
    */
    'pending_2fa_session_key' => 'auth.2fa.user_id',
    'pending_2fa_started_key' => 'auth.2fa.started_at',

    /*
     * How long a password-verified session may sit on the 2FA challenge screen
     * before it is discarded and the user must sign in again.
     */
    'two_factor_challenge_minutes' => (int) env('TWO_FACTOR_CHALLENGE_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Development conveniences
    |--------------------------------------------------------------------------
    | NEVER enable these in production. `expose_code` flashes the OTP on the
    | login screen (only honoured when APP_ENV is local/testing as well);
    | `check_breached` calls the HaveIBeenPwned network API.
    |
    | These live in config rather than calling env() directly so that they
    | survive `php artisan config:cache`, which the container runs on boot.
    */
    'two_factor' => [
        'expose_code' => (bool) env('TWO_FACTOR_EXPOSE_CODE', false),
    ],

    'password' => [
        'check_breached' => (bool) env('PASSWORD_CHECK_BREACHED', false),
    ],
];
