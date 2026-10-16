<?php

declare(strict_types=1);

/**
 * Backup configuration.
 *
 * Backups are taken server-side, compressed, encrypted with AES-256-GCM and
 * then either stored on the local disk (`local`, the labelled development
 * simulation that needs NO AWS credentials) or uploaded to a private Amazon S3
 * bucket (`s3`).
 *
 * Least-privilege IAM: the application's IAM user/role only needs
 *   s3:PutObject, s3:GetObject, s3:DeleteObject, s3:ListBucket
 * scoped to the backup bucket/prefix. See docs/AWS_DEPLOYMENT.md.
 */
return [

    'enabled' => (bool) env('BACKUP_ENABLED', true),

    /*
    | local = development simulation (no AWS account required)
    | s3    = upload the encrypted archive to Amazon S3
    */
    'driver' => env('BACKUP_DRIVER', 'local'),

    'disk' => env('BACKUP_DISK', 'local'),

    'path' => env('BACKUP_PATH', 'backups'),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        'enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
        'time' => env('BACKUP_TIME', '02:30'),
        'timezone' => env('BACKUP_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Archive format
    |--------------------------------------------------------------------------
    | MyISAM/InnoDB dumps are gzipped, then encrypted with the application's
    | AES-256-GCM field key (APP_ENCRYPTION_KEY). The SHA-256 checksum of the
    | encrypted archive is recorded so integrity can be verified later.
    */
    'compression' => true,
    'encrypt' => true,

    /*
    |--------------------------------------------------------------------------
    | mysqldump
    |--------------------------------------------------------------------------
    | For the local development simulation a portable mysqldump binary is used.
    | In production use the managed RDS snapshot API and keep this as a logical
    | secondary dump.
    */
    'mysqldump' => [
        'binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        /*
         * Least-privilege dump flags. `--no-tablespaces` avoids needing the
         * PROCESS privilege and `--routines`/`--events` are opt-in because they
         * require extra privileges the application's database user does not
         * (and should not) hold.
         */
        'extra_args' => array_values(array_filter([
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            (bool) env('BACKUP_DUMP_ROUTINES', false) ? '--routines' : null,
            (bool) env('BACKUP_DUMP_EVENTS', false) ? '--events' : null,
        ])),
        'timeout' => (int) env('BACKUP_TIMEOUT_SECONDS', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | S3 destination (driver = s3)
    |--------------------------------------------------------------------------
    */
    's3' => [
        'bucket' => env('AWS_BACKUP_BUCKET'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'prefix' => env('AWS_BACKUP_PREFIX', 'hotel-backups'),
        // Private by default; never rely on bucket-level public access.
        'visibility' => 'private',
        // Server-side encryption: 'AES256' (SSE-S3) or 'aws:kms' (SSE-KMS).
        'sse' => env('AWS_BACKUP_SSE', 'AES256'),
        'kms_key_id' => env('AWS_BACKUP_KMS_KEY_ID'),
        'storage_class' => env('AWS_BACKUP_STORAGE_CLASS', 'STANDARD_IA'),
        'versioning' => (bool) env('AWS_BACKUP_VERSIONING', true),

        /*
         * Explicit credentials are OPTIONAL. When empty the AWS SDK falls back
         * to its default chain (environment, shared credentials file, or an
         * EC2/ECS instance role). Never hard-code a credential here.
         */
        'key' => (string) env('AWS_ACCESS_KEY_ID', ''),
        'secret' => (string) env('AWS_SECRET_ACCESS_KEY', ''),
        'session_token' => env('AWS_SESSION_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Failure alerts
    |--------------------------------------------------------------------------
    | A failed or unverifiable backup emails this address and writes an audit
    | entry. In production this maps to an SNS topic / CloudWatch alarm.
    */
    'alert' => [
        'mail_to' => env('BACKUP_ALERT_EMAIL'),
        'on_failure' => true,
        'on_verify_failure' => true,
    ],
];
