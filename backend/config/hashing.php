<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | The assignment mandates Argon2id, so it is the *default* here rather than
    | bcrypt: even if HASH_DRIVER is absent from the environment, passwords are
    | still hashed with Argon2id. PasswordService layers a per-user salt and an
    | application pepper on top of this (see docs/PASSWORD_SALT_PEPPER.md).
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Argon2id tuning: memory is in kibibytes. 64 MiB / 4 iterations meets the
    | OWASP "recommended" profile. These are read from the environment so a
    | deployment can raise them without a code change.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Silently upgrades a stored hash when the algorithm or cost changes.
    |
    */

    'rehash_on_login' => true,

];
