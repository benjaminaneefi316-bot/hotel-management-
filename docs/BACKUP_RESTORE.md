# Backup & Restore

This document describes the backup pipeline and the **configuration restore-point**
feature as they are actually implemented in this repository.

- Backup pipeline: `app/Services/BackupService.php`
- Backup config: `config/backup.php`
- Restore-point config: `config/restore-points.php`
- Restore-point service: `app/Services/ConfigurationSnapshotService.php`
- Settings whitelist/guard: `app/Services/SettingsService.php`
- Backups admin controller: `app/Http/Controllers/BackupController.php`
- Restore points admin controller: `app/Http/Controllers/RestorePointController.php`
- Tests: `tests/Feature/BackupPipelineTest.php`, `tests/Feature/ConfigurationRestoreTest.php`

> **Scope note.** There are two unrelated ideas in this repo that both use the word
> "restore":
> 1. **Database backup archive** – a full SQL dump, encrypted, for disaster recovery.
> 2. **Configuration restore point** – a whitelisted snapshot of *settings* (and optionally
>    role assignments) that deliberately **never** touches guests, bookings or payments.
>
> They are implemented separately and are not connected to each other.

---

## 1. What gets backed up

Only the **default database connection** is dumped. The service reads
`config('database.default')` and that connection's `driver`, `host`, `port`,
`username`, `password` and `database`.

| Database driver | What happens |
| --- | --- |
| `mysql` / `mariadb` (any non-sqlite driver) | Runs the external `mysqldump`/`mariadb-dump` binary (see below). |
| `sqlite` (file) | Copies the database file. |
| `sqlite` (`:memory:`) | Writes a small **simulated** placeholder dump (development/testing only) so the rest of the pipeline is still exercised. |

No application tables are exported selectively and no files/attachments are included:
the archive is a logical database dump.

### The pipeline (from `BackupService::run()`)

1. **Dump** the database to a temporary workspace (`storage/app/backups/tmp`).
2. **Compress** with gzip (level 6, streamed in 256 KiB chunks) unless
   `backup.compression` is disabled. `backup.compression` is **hard-coded `true`** in
   `config/backup.php` (not env-driven).
3. **Encrypt** with **AES-256-GCM** using `APP_ENCRYPTION_KEY` (`App\Support\Encryption\FileEncryptor`).
   The envelope is `nonce[12] . tag[16] . ciphertext`; a corrupt/tampered archive fails to
   decrypt rather than producing garbage. `backup.encrypt` is also **hard-coded `true`**.
   The whole file is processed **in memory** — fine for a classroom-sized DB, but note the
   code comment recommends a streaming construction or managed snapshots for very large dumps.
4. **Store** the archive (local disk or S3, see §2).
5. **Verify**:
   - SHA-256 checksum of the encrypted file is compared against the stored value;
   - decrypt + gunzip round-trip must succeed and the head of the dump must contain
     `CREATE TABLE`, `-- Simulated dump` or `SQLite format`;
   - for S3, a `HeadObject` must return a `ContentLength` equal to the local size.
6. **Record** the attempt in the `backup_logs` table and **raise an alert on failure**.

### File naming

```
hotel-backup-<Ymd-His>.sql.gz.enc
# e.g. hotel-backup-20261015-023000.sql.gz.enc
```

Each attempt also gets a human reference like `BK-20261015023000-A1B2`
(`BK-` + `YmdHis` + `-` + 4 random uppercase characters).

### Stored metadata (`backup_logs`)

`reference`, `filename`, `driver`, `path`, `bucket`, `status`
(`running`/`success`/`failed`), `verification_status` (`pending`/`verified`/`failed`),
`verification_detail`, `size_bytes`, `checksum` (SHA-256), `is_encrypted`,
`started_at`, `finished_at`, `duration_ms`, `error`, `retention_days`,
`is_pruned`, `pruned_at`, `alerted_at`, `created_by`.

---

## 2. The two drivers

`BACKUP_DRIVER` selects the destination. The `s3` driver is the only one that
requires AWS credentials.

### `local` — the no-AWS development simulation

- Writes to `storage/app/<BACKUP_PATH>` (default `storage/app/backups`).
- Requires **no AWS account and no AWS credentials**.
- The recorded `path` is relative to `storage/app` (e.g. `backups/hotel-backup-….sql.gz.enc`).

> The `BACKUP_DISK` value and the `s3` filesystem disk in `config/filesystems.php`
  are **not used** by `BackupService`. The local driver writes directly under
  `storage/app/<BACKUP_PATH>`; `config/filesystems.php` configures general application
  file storage (keyed by `AWS_BUCKET`), which is a different concern.

### `s3` — private Amazon S3 bucket

- Object key: `<AWS_BACKUP_PREFIX>/<filename>` (default prefix `hotel-backups`).
- Uploaded with (`putObject`):
  - `ContentType: application/octet-stream`
  - `ServerSideEncryption` = `AWS_BACKUP_SSE` (default `AES256` / SSE-S3)
  - `StorageClass` = `AWS_BACKUP_STORAGE_CLASS` (default `STANDARD_IA`)
  - `SSEKMSKeyId` is added **only** when `AWS_BACKUP_SSE=aws:kms` **and**
    `AWS_BACKUP_KMS_KEY_ID` is set.
- Requires `AWS_BACKUP_BUCKET`; if it is empty the run fails with a clear message.
- AWS SDK client: region from `AWS_DEFAULT_REGION` (default `us-east-1`). If **both**
  `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` are set they are used explicitly
  (plus optional `AWS_SESSION_TOKEN`); otherwise the SDK uses its **default credential
  chain** (env vars, shared credentials file, or an EC2/ECS IAM role). No credential is
  ever hard-coded.

### Environment variables (all from `config/backup.php`)

| Variable | Default | Purpose |
| --- | --- | --- |
| `BACKUP_ENABLED` | `true` | Enables the backup feature flag. |
| `BACKUP_DRIVER` | `local` | `local` or `s3`. |
| `BACKUP_DISK` | `local` | Declared in config; **not used by `BackupService`**. |
| `BACKUP_PATH` | `backups` | Local sub-path under `storage/app`. |
| `BACKUP_RETENTION_DAYS` | `30` | Retention window used by prune. |
| `BACKUP_SCHEDULE_ENABLED` | `true` | Declared schedule flag (see §4 — the schedule is wired in `routes/console.php`). |
| `BACKUP_TIME` | `02:30` | Declared schedule time (informational; the wired schedule uses `dailyAt('02:30')`). |
| `BACKUP_TIMEZONE` | `APP_TIMEZONE` or `UTC` | Declared schedule timezone. |
| `MYSQLDUMP_BINARY` | `mysqldump` | Full path/name of the dump binary. |
| `BACKUP_TIMEOUT_SECONDS` | `900` | Dump process timeout. |
| `BACKUP_DUMP_ROUTINES` | `false` | Include stored routines (`--routines`); needs extra DB privileges. |
| `BACKUP_DUMP_EVENTS` | `false` | Include scheduled events (`--events`); needs extra DB privileges. |
| `BACKUP_ALERT_EMAIL` | *(empty)* | Recipient for failure mail. If empty, no alert email is sent. |
| `AWS_BACKUP_BUCKET` | *(empty)* | Required by the `s3` driver. |
| `AWS_DEFAULT_REGION` | `us-east-1` | Bucket region / SDK region. |
| `AWS_BACKUP_PREFIX` | `hotel-backups` | Object key prefix. |
| `AWS_BACKUP_SSE` | `AES256` | `AES256` (SSE-S3) or `aws:kms` (SSE-KMS). |
| `AWS_BACKUP_KMS_KEY_ID` | *(empty)* | KMS key id/ARN, used only when SSE is `aws:kms`. |
| `AWS_BACKUP_STORAGE_CLASS` | `STANDARD_IA` | S3 storage class for uploads. |
| `AWS_BACKUP_VERSIONING` | `true` | Declared in config; **the service does not act on it** (versioning is a bucket-level setting). |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_SESSION_TOKEN` | *(empty)* | Optional explicit credentials; otherwise default chain. |
| `APP_ENCRYPTION_KEY` | *(required)* | Base64-encoded 32-byte key used to encrypt the archive. Missing/invalid ⇒ run fails. |

> Never print real secrets. Use placeholders such as `<your-pepper>`,
> `<your-encryption-key>`, `<aws-access-key-id>` in any environment files you share.

### mysqldump invocation

The password is passed via the **`MYSQL_PWD` environment variable**, never on the
command line, so it does not appear in the process list.

```
<MYSQLDUMP_BINARY> --host=<host> --port=<port> --user=<user> \
  --single-transaction --quick --skip-lock-tables --no-tablespaces \
  <database>
```

(`--single-transaction --quick --skip-lock-tables --no-tablespaces` come from
`backup.mysqldump.extra_args`.) `--no-tablespaces` avoids needing the `PROCESS`
privilege, and `--routines` / `--events` are appended only when
`BACKUP_DUMP_ROUTINES` / `BACKUP_DUMP_EVENTS` are enabled, because they require extra
privileges the application's database user should not hold. This keeps the dump within
least privilege: it was verified locally with a `SELECT`-only database user.

---

## 3. Retention / prune

`BackupService::prune(?int $retentionDays = null)`:

- Defaults to `BACKUP_RETENTION_DAYS` (30).
- Selects `backup_logs` rows where `created_at < now()-days`, `is_pruned = false` and
  `status = success`.
- Deletes the artifact (`DeleteObject` on S3, unlink on local disk), then marks the row
  `is_pruned = true` and sets `pruned_at`.
- Writes an audit entry (`backup.prune`) when at least one archive is removed.

Prune runs weekly through the scheduler (see §4), and can also be triggered from the admin
UI (`POST /backups/prune`), via `php artisan backup:prune`, or by calling the service
directly.

---

## 4. Scheduling — read this carefully

`config/backup.php` **declares** a schedule block:

```php
'schedule' => [
    'enabled'  => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
    'time'     => env('BACKUP_TIME', '02:30'),
    'timezone' => env('BACKUP_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
],
```

**The schedule is wired in `routes/console.php`.** Two closure commands wrap the service and
are registered on the scheduler:

```php
Artisan::command('backup:run',   ...);   // dump → encrypt → store → verify
Artisan::command('backup:prune', ...);   // retention sweep

Schedule::command('backup:run')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:prune')
    ->weeklyOn(1, '03:30')
    ->withoutOverlapping()
    ->onOneServer();
```

`backup:run` exits non-zero when the resulting `backup_logs` row is not `success`, so a
platform scheduler / cron can alert on it. Verify what is registered with:

```powershell
php artisan schedule:list
#  30 2 * * *  php artisan backup:run    Next Due: ...
#  30 3 * * 1  php artisan backup:prune  Next Due: ...
```

The bare `backup.schedule.*` config block (`BACKUP_SCHEDULE_ENABLED`, `BACKUP_TIME`,
`BACKUP_TIMEZONE`) is still informational — the wired schedule hard-codes 02:30 — so keep
the two in step if you change the window.

**To actually fire the schedule**, the server needs the standard Laravel cron entry (or a
platform scheduler):

```cron
* * * * * cd /path/to/hotel-management/backend && php artisan schedule:run >> /dev/null 2>&1
```

That single artifact runs every minute and lets Laravel decide what is actually due. This
cron entry is the standard Laravel pattern; it is **not** created by this repository.

---

## 4a. Failure alerts

`BackupService::alertFailure()` runs when a backup fails or fails verification:

- Reads `backup.alert.mail_to` (`BACKUP_ALERT_EMAIL`) and `backup.alert.on_failure`.
- If an address is configured, sends `App\Mail\BackupFailedMail` and stamps
  `alerted_at`.
- If no address is set, **no email is sent** (the failure is still recorded in
  `backup_logs` and audited).
- `config/backup.php` also declares `alert.on_verify_failure = true`, but the code path
  keys off `on_failure` only.

Delivery uses the configured mailer (`MAIL_MAILER`). In development this is `log`, so the
mail lands in `storage/logs/laravel.log`.

---

## 5. Admin UI and manual trigger

Backups have a full admin UI, restricted by the `manage-backups` ability
(`config/abilities.php` → **admin only**).

| Method | Route | Name | Controller action |
| --- | --- | --- | --- |
| GET | `/backups` | `backups.index` | `index` — paginated list, current driver + retention |
| POST | `/backups` | `backups.store` | `store` — **runs a backup now** |
| GET | `/backups/{backup}` | `backups.show` | `show` |
| POST | `/backups/{backup}/verify` | `backups.verify` | `verify` — re-verifies an archive |
| POST | `/backups/prune` | `backups.prune` | `prune` — removes expired archives |
| DELETE | `/backups/{backup}` | `backups.destroy` | `destroy` — removes the log record |

Blade views referenced: `backups.index`, `backups.show`.

Note: `destroy` deletes the **database record**, not necessarily the artifact; pruning is
the operation that removes stored archives.

---

## 6. Configuration restore points

A **restore point** is a snapshot of *approved configuration only*. It exists so an
administrator can roll back an accidental settings/role change **without ever touching
guest, booking or payment data**.

### What is captured

Only keys in the **whitelist** `config/restore-points.php → eligible_settings`:

- Hotel identity: `hotel_name`, `hotel_tagline`, `hotel_email`, `hotel_phone`,
  `hotel_address`, `support_email`, `hotel_currency`, `hotel_timezone`
- Stay policy: `check_in_time`, `check_out_time`, `booking_lead_time_days`,
  `max_advance_booking_days`, `cancellation_window_hours`
- Financials: `tax_rate`, `service_charge_rate`, `default_nightly_rate`
- Loyalty/invoicing: `loyalty_points_per_night`, `invoice_footer`

Optionally, **role assignments** for every non-archived user can be included, controlled
by `include_role_assignments_by_default` (default **false**).

### The `never_snapshot` guard

Even if someone mistakenly whitelists a sensitive key, `SettingsService::isForbidden()`
blocks it. The guard matches **case-insensitively as a substring**:

```
password, passwd, salt, pepper, secret, token, otp, code, key,
credential, access, private, session, cookie, csrf, 2fa, auth
```

- `SettingsService::eligible()` returns only settings that are both `isEligible()` **and**
  not `isForbidden()`.
- `SettingsService::set()` **refuses** to store any key matching a forbidden pattern.
- At restore time the whitelist is re-validated: a key that is no longer eligible (or has
  become forbidden) is **skipped**, never blindly written.

### What a restore does

`ConfigurationSnapshotService::restore()`:

1. Verifies the stored **SHA-256 checksum** of the snapshot items (tamper detection);
   mismatch ⇒ `BusinessRuleException`, restore refused, security audit entry written.
2. Verifies the `schema_version` is the one the app supports.
3. Opens a **single DB transaction**.
4. Re-applies whitelisted settings only.
5. If role assignments were captured, re-syncs roles for **active** users only (archived/
   deactivated accounts are never resurrected).
6. If `restore.protect_last_admin` is true (default), asserts at least one active
   administrator remains.
7. Writes an audit entry (`restore_point.restore`).

Safety options in `config/restore-points.php`:

- `protect_last_admin` — never leave the system without an active admin.
- `transactional` — roll back on any error.
- `require_confirmation_text` — the admin must type the snapshot name exactly
  (`RestoreConfigurationRequest` validates `confirmation === $snapshot->name`).

Admin UI routes (all under `manage-restore-points`, **admin only**):
`restore-points.index`, `.create`, `.store`, `.{snapshot}`, `.{snapshot}/preview`,
`{snapshot}/restore`, `.{snapshot}` (delete).

### The automatic test (`tests/Feature/ConfigurationRestoreTest.php`)

`ConfigurationRestoreTest` is described in the file as the **mandatory assignment test**
and proves the guarantee end to end:

1. Captures a snapshot of settings **and** role assignments.
2. Creates a room type/room, a guest and a booking.
3. Changes `hotel_name` and changes a user's role to `admin`.
4. Creates a second guest, a second booking and a payment.
5. Restores the snapshot and asserts:
   - `hotel_name` is back to the captured value (`Azure Bay Hotel`);
   - the user's role is back to `receptionist`;
   - **every** guest, booking and payment created along the way still exists and the
     counts are unchanged;
   - the restore is written to `audit_logs` (`restore_point.restore` / `success`).

Two more tests in the same file:

- `test_snapshot_never_contains_business_rows_or_secrets` — asserts no captured key is
  forbidden and that `guests`, `bookings`, `password` never appear.
- `test_a_tampered_snapshot_is_refused` — mutates a captured item and expects
  `BusinessRuleException` on restore.

### Restore points admin UI

`RestorePointController` exposes `index`, `create`, `store`, `show`, `preview`, `restore`
and `destroy`, all under the `manage-restore-points` ability (**admin only**). Restore
requires typing the snapshot name (validated by `RestoreConfigurationRequest`).

---

## 6a. The backup pipeline test

`tests/Feature/BackupPipelineTest.php` forces `backup.driver = local` and verifies, against
local storage (no AWS):

- a full run succeeds (`status = success`, `verification_status = verified`, non-empty
  checksum and size);
- the stored archive is **encrypted** (it does not contain `CREATE TABLE`);
- the archive can be re-verified later (`verifyExisting()` → true);
- an audit row `backup.run` / `success` exists;
- `prune(30)` removes an archive whose `created_at` was moved 90 days into the past and
  marks `is_pruned = true`.

---

## 7. Running a backup / restore locally

### Prerequisites

```bash
cd backend
composer install
cp .env.example .env            # then add the project-specific keys (see below)
php artisan key:generate
php artisan migrate --force
php artisan db:seed             # optional: demo data
```

The project-specific secrets must be present in `.env`:

```dotenv
APP_PASSWORD_PEPPER=<your-pepper>
APP_ENCRYPTION_KEY=<your-encryption-key>
BACKUP_DRIVER=local
MYSQLDUMP_BINARY=<path-to-mysqldump-or-mariadb-dump>
```

Generate the two secrets with the real command:

```bash
php artisan security:generate-secrets
```

### Run a backup

There is **no dedicated artisan command**. Use the admin UI or the service directly:

```bash
# Admin UI: sign in as an admin, open Backups, click "Create backup".
# Or, from the console:
php artisan tinker
>>> app(App\Services\BackupService::class)->run();
```

### Verify an existing archive

```bash
php artisan tinker
>>> app(App\Services\BackupService::class)->verifyExisting(App\Models\BackupLog::latest()->first());
```

### Prune expired archives

```bash
php artisan tinker
>>> app(App\Services\BackupService::class)->prune();     # or ->prune(30)
```

### Inspect a local archive (decrypt + gunzip)

The archive is AES-256-GCM encrypted, so it is not a plain `.sql.gz`. To read it:

```bash
php artisan tinker
>>> $log = App\Models\BackupLog::latest()->first();
>>> $enc = storage_path('app/'.$log->path);
>>> $gz  = storage_path('app/backups/tmp/inspect.gz');
>>> app(App\Support\Encryption\FileEncryptor::class)->decryptFile($enc, $gz);
>>> file_put_contents(storage_path('app/backups/tmp/inspect.sql'), gzdecode(file_get_contents($gz)));
```

### Restore a whole database (manual DBA step)

**There is no in-app "restore database" feature.** Restoring a full archive is a manual
operation: decrypt, gunzip and import into MySQL/MariaDB. For example:

```bash
# after decrypting to dump.sql.gz with the tinker snippet above
gunzip -c dump.sql.gz | mysql -h <host> -u <user> -p <database>
```

Import into a scratch database first and validate before touching production.

### Restore a configuration snapshot

Normally via the admin UI (Restore points → Preview → Restore, typing the snapshot name).
From the console:

```bash
php artisan tinker
>>> $s = App\Models\ConfigurationSnapshot::first();
>>> $a = App\Models\User::where('username', 'admin')->first();
>>> app(App\Services\ConfigurationSnapshotService::class)->restore($s->load('items'), $a);
```

---

## 8. Things NOT verified / caveats

- **The local dump path *was* executed** against the local MariaDB server: `backup:run`
  produced an encrypted, verified archive (`status=success`, `verification_status=verified`).
  The **S3** upload path and the restore/import path are **not** verified end-to-end.
- **Scheduled tasks are registered but no OS-level cron is configured in this repo.**
  `routes/console.php` wires `backup:run` (daily 02:30) and `backup:prune` (weekly), and
  `php artisan schedule:list` shows them; firing them on a server still needs the cron line
  / platform scheduler shown in §4.
- The `backup.schedule.*` config block is informational (the wired schedule uses 02:30), and
  `BACKUP_DISK` / `AWS_BACKUP_VERSIONING` / `alert.on_verify_failure` are declared in config
  but not read by the service logic described above.
- The database **import** path was not executed against a live MySQL server as part of
  writing this document; it is described directly from the code.
