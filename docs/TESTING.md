# Testing Guide

This document describes how the test suites are organised and how to run them.
Everything below reflects the current test files in `backend/tests/` and
`mobile/test/`, plus `backend/phpunit.xml`.

> **Verified run (this checkout).**
> * `php artisan test` in `backend/` → **48 tests, 209 assertions, all passing.**
> * `flutter test` in `mobile/` → **10 tests, all passing.**
> * `flutter analyze` in `mobile/` → **no issues found.**

Because the backend suite uses `RefreshDatabase` and an in-memory SQLite
database, the test run is repeatable and self-contained.

---

## 1. Running the suites

### Backend (Laravel / PHPUnit)

Run from the `backend` directory:

```powershell
cd C:\Users\CHARMIES\Desktop\project\hotel-management\backend
php artisan test
```

Using the Composer convenience script instead (it clears the config cache
first, through the `test` script in `backend/composer.json`):

```powershell
cd C:\Users\CHARMIES\Desktop\project\hotel-management\backend
composer test
```

If `php` is not already on your `PATH`, put the portable toolchain on it first
(from the repository root) or call the binary directly:

```powershell
# From the repository root - adds php/composer/git/flutter for this session
. .\tools\set-env.ps1

# ...or invoke the portable PHP directly
& 'C:\Users\CHARMIES\tools\php84\php.exe' artisan test
```

### Mobile (Flutter)

Run from the `mobile/` directory:

```powershell
cd C:\Users\CHARMIES\Desktop\project\hotel-management\mobile
flutter test
flutter analyze
```

If `flutter` is not on your `PATH`, dot-source `tools\set-env.ps1` first, or call
`C:\Users\CHARMIES\tools\flutter\bin\flutter.bat` directly.

---

## 2. Test environment

The backend test environment is defined in `backend/phpunit.xml`. Key values:

| Setting | Value | Why |
| --- | --- | --- |
| `APP_ENV` | `testing` | Framework knows it is a test run. |
| `DB_CONNECTION` | `sqlite` | No MySQL/MariaDB server needed. |
| `DB_DATABASE` | `:memory:` | Database exists only inside the process. |
| `MAIL_MAILER` | `array` | No e-mail is actually sent; `Mail::assertSent(...)` inspects the array. |
| `SESSION_DRIVER` | `array` | Sessions live in memory only. |
| `CACHE_STORE` | `array` | Cache lives in memory only. |
| `QUEUE_CONNECTION` | `sync` | Jobs run inline; no queue worker. |
| `BROADCAST_CONNECTION` | `null` | Broadcasting is disabled. |
| `BACKUP_ENABLED` / `BACKUP_DRIVER` | `true` / `local` | Backup pipeline runs against local storage, no AWS. |
| `BACKUP_ALERT_EMAIL` | `""` | Failure alerts are not e-mailed during tests. |
| `TWO_FACTOR_EXPOSE_CODE` | `true` | The OTP is surfaced so the 2FA flow is testable. |
| `PULSE_ENABLED`, `TELESCOPE_ENABLED`, `NIGHTWATCH_ENABLED` | `false` | Observability tooling off. |
| `BCRYPT_ROUNDS` | `4` | Legacy cheap cost (not used for app hashing). |

Hashing in tests uses the **same algorithm as the application** (Argon2id),
but with deliberately cheap parameters (`ARGON_MEMORY=1024`, `ARGON_TIME=1`,
`ARGON_THREADS=1`). The suite also pins fixed, test-only secrets
(`APP_PASSWORD_PEPPER`, `APP_ENCRYPTION_KEY`) so results are deterministic and
completely offline. These values are never used outside tests.

Feature tests that need database rows use the `RefreshDatabase` trait, which
migrates the in-memory database and wraps each test in a transaction so tests
cannot see each other's data. The `Tests\Concerns\SeedsHotel` helper creates the baseline system
roles and a small, deterministic set of settings directly (it deliberately does
**not** call the production seeders, so tests are independent of demo data).
Shared test setup lives in `backend/tests/TestCase.php`.

### Why this is safe

* The in-memory database is discarded when the process ends — no developer,
  staging or production data is touched.
* `array`/`sync`/`null` drivers mean tests perform **no outbound network calls**
  (no SMTP, no S3, no cache/queue servers).
* `TWO_FACTOR_EXPOSE_CODE=true` only affects the test process; it is documented
  as a development convenience and is read from the environment in
  `app/Services/AuthService.php`.

---

## 3. Backend test files

Backend tests are split into `tests/Unit` and `tests/Feature` (see the
`<testsuites>` block in `phpunit.xml`).

| File | Tests | What it covers |
| --- | ---: | --- |
| `tests/Unit/ExampleTest.php` | 1 | Framework placeholder (`assertTrue(true)`); not application logic. |
| `tests/Unit/FieldEncryptorTest.php` | 4 | AES-256-GCM field encryptor: encrypt/decrypt round trip, `gcm1:` envelope, ciphertext bound to its column (AAD), tamper returns `null`, and `null`/`''`/`isEncrypted()` behaviour. |
| `tests/Feature/AuthenticationTest.php` | 7 | Web 2FA. Wrong password rejected; a **correct password does not sign in** (redirects to the 2FA challenge and mails a code); valid code completes login; incorrect code rejected; a code is single-use; archived and inactive accounts cannot sign in. |
| `tests/Feature/RbacAuthorizationTest.php` | 5 | The named-gate matrix matches role definitions. Receptionist is forbidden from `/users`; guest forbidden from `/dashboard`; receptionist forbidden from `/restore-points`; admin allowed on `/users`. |
| `tests/Feature/LastAdminProtectionTest.php` | 5 | The last active administrator cannot be demoted or archived; demotion/archiving succeeds when another active admin remains; an archived account is removed from the authentication path. |
| `tests/Feature/GuestDataEncryptionTest.php` | 4 | Guest `phone`/`national_id`/`address` are stored as AES-256-GCM ciphertext (never plaintext), transparently decrypted by the model, produce different ciphertext for identical plaintext, and tampered ciphertext is refused (returns `null`). |
| `tests/Feature/ConfigurationRestoreTest.php` | 3 | Configuration-only restore points — the full assignment scenario, that snapshots never contain business rows or secrets, and that a tampered snapshot is refused. See §4. |
| `tests/Feature/BookingServiceTest.php` | 5 | Reservation logic: create/attach/price; overlapping double-booking is refused; adjacent stays do not overlap; half-open interval `hasOverlap`; `availableRooms` excludes reserved rooms. |
| `tests/Feature/BackupPipelineTest.php` | 2 | Backup pipeline end to end with the `local` driver: dump → gzip → AES-256-GCM encrypt → store → verify → audit, including the success record; and pruning of expired archives. |
| `tests/Feature/ApiTest.php` | 7 | The REST API used by Flutter: public `/api/health` and `/api/room-types`; two-step login returning a mobile bearer token; bad credentials `401`; unauthenticated request `401`; a guest token is forbidden from staff endpoints; a staff token is allowed; an **archived** account's existing bearer token is refused (`403`). |
| `tests/Feature/AdminPagesSmokeTest.php` | 2 | Renders **every** authenticated page as an administrator (all HTTP 200), and every guest page as a guest account (all HTTP 200). Catches broken Blade views, missing controller variables and bad eager-loading. |
| `tests/Feature/PublicSiteTest.php` | 3 | Public marketing site: home renders, accommodation index and detail pages render, and the login page renders. |

Totals: **48 tests** (5 in `tests/Unit`, 43 in `tests/Feature`).

---

## 4. The configuration restore point (what it actually asserts)

`tests/Feature/ConfigurationRestoreTest.php` is the suite's most important
test. It asserts that a restore point rolls back **approved configuration
only**, never business data. The main test,
`test_restore_reverts_configuration_but_never_touches_business_data`, follows
the brief exactly:

1. **Capture a baseline.** An admin creates a snapshot named `Approved baseline`
   with `includeRoleAssignments: true`, while a baseline setting
   (`hotel_name = 'Azure Bay Hotel'`) and a colleague's role
   (`receptionist`) are in place.
2. **Business happens.** A room type, a room, a guest and a booking
   (attached to the room) are created.
3. **Configuration changes.** The admin sets `hotel_name` to
   `'Changed Name Hotel'` and syncs the colleague's roles to `['admin']`.
4. **More business happens.** A second room, guest and booking are created,
   plus a payment against the first booking. The test records the counts of
   guests, bookings and payments.
5. **Restore.** The snapshot is restored, and the test asserts:
   * `hotel_name` is back to `'Azure Bay Hotel'`;
   * the colleague's roles are back to `['receptionist']`;
   * **every** guest, booking and payment created along the way still exists
     (counts are unchanged, and `assertDatabaseHas` finds each specific row);
   * the restore itself is written to `audit_logs` with action
     `restore_point.restore` and outcome `success`.

The same file also has:

* `test_snapshot_never_contains_business_rows_or_secrets` — every captured item
  key passes `SettingsService::isForbidden()`, and the snapshot does not
  contain `guests`, `bookings` or `password`.
* `test_a_tampered_snapshot_is_refused` — modifying a stored item's
  `captured_value` then restoring throws `BusinessRuleException` (the checksum
  guard in `ConfigurationSnapshotService::assertIntegrity()`).

---

## 5. Mobile test files

Mobile tests are in `mobile/test/widget_test.dart` (the Flutter project is
`azure_bay_mobile`). The suite is unit/widget level.

| Test | Type | What it covers |
| --- | --- | --- |
| Login screen renders the brand and the sign-in form | widget | The login UI shows the expected labels (brand, `Sign in`, `Continue`, fields). |
| Login screen validates an empty submission | widget | Empty form shows `Enter your email address` and `Enter your password`. |
| Login screen rejects a malformed email | widget | Bad e-mail shows `Enter a valid email address`. |
| `ApiClient.resolveAsset` — prefixes a root-relative path with the origin | unit | `/assets/...` becomes an absolute URL against the API origin. |
| `ApiClient.resolveAsset` — leaves an already-absolute URL untouched | unit | Absolute `https://` URLs are returned unchanged. |
| `ApiClient.resolveAsset` — returns an empty string for null/blank input | unit | `null` and whitespace yield `''`. |
| `ApiClient.resolveAsset` — works when the base URL has no `/api` suffix | unit | Origin derivation handles a trailing slash. |
| `Models` — `AppUser` derives initials and parses optional phone | unit | `initials`, phone, `isStaff` parsing. |
| `Models` — `RoomType` reads catalogue fields | unit | capacity, base price, amenities, image URL. |
| `Models` — `Booking` reads nested rooms and payments | unit | reference, rooms, payments, `canCancel`, balance. |

The `_host()` helper wires the same `Provider`s the real app uses
(`SessionController` with `AuthRepository`, and `HotelRepository`), with a
`MaterialApp` using `AppTheme.light()`. Ten tests in total.

`flutter analyze` currently reports **no issues**.

---

## 6. What is **not** covered by automated tests

Honesty matters here. The current suites do **not**:

* Run a **browser/E2E** test (no Dusk, Selenium or Playwright). Blade pages are
  *rendered* by `AdminPagesSmokeTest`, but no test drives a real browser or
  clicks through a UI workflow.
* Exercise the **Flutter app against a live backend**. The widget tests use a
  fixed fake base URL and providers; there is no integration or
  device/emulator test. `flutter analyze` is static analysis, not runtime.
* Test the **S3 backup driver**. `BackupPipelineTest` runs the `local` driver
  (and the SQLite dump path); `uploadToS3()`, the HEAD verification and IAM
  behaviour are not exercised.
* Send **real e-mail**. The `array` mailer is used; tests assert via
  `Mail::assertSent(TwoFactorOtpMail::class, ...)`.
* Run the **scheduler**. `routes/console.php` registers `backup:run`,
  `backup:prune` and `otp:prune` on the `Schedule` (confirmed with
  `php artisan schedule:list`), but no test asserts the schedule or executes
  the commands; the suite calls `BackupService` directly.
* Cover every **HTTP write** flow. Auth/2FA and API auth are covered, but e.g.
  creating a booking through the web form, the public availability search
  `POST`, the public contact form and refund/check-in endpoints are covered at
  service level (or via read-only smoke GETs) rather than full request tests.
* Perform **load/performance**, **accessibility** or **visual-regression**
  testing.

If you extend the suite, the natural next steps are a browser E2E test for the
booking wizard and an S3 backup test using a local S3 stub.
