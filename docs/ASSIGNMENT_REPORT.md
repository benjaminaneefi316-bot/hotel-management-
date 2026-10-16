# Assignment Report — Secure Hotel Management System

## 1. Overview

This project delivers a complete hotel management system for a fictional seaside resort,
**Azure Bay Hotel**. It has three parts that share one codebase and one database:

* a **Laravel 13 / PHP 8.4 web application** (`backend/`) — a public booking website plus a
  staff and administrator back office;
* a **REST API** exposed by the same application under `/api`, protected by Laravel Sanctum;
* a **Flutter mobile application** (`mobile/`) that consumes that API and is aimed at guests.

The emphasis of the assignment is **security**, so the system is built so that the security
controls are enforced on the server and can be demonstrated and tested, rather than being
cosmetic. Configuration lives in `backend/config/` and secrets come from the environment; no
credential is hard-coded into a view or committed to the repository.

## 2. What was built

**Public website.** Home, accommodation catalogue and room-type detail pages, gallery, about and
contact pages, and a booking search that checks live availability before a guest can reserve.
Hotel photography is served locally with lazy loading, descriptive alt text and a placeholder
fallback, so the site degrades gracefully if an asset is ever missing.

**Guest area.** Guests sign in (with two-factor authentication), browse room types, create and
manage their own bookings, and review payments.

**Staff area.** A dashboard with occupancy and revenue KPIs, front-desk check-in, the
reservation book, guest records and payment capture.

**Administrator area.** User and role management (RBAC), room types and rooms, system settings,
audit log, archived-user management, the backup console, configuration restore points and
reports.

**Mobile application.** Splash and secure login, the two-factor step, a room catalogue, room
detail, a booking form, "my bookings", booking detail, a guest dashboard and a profile screen.
The API base URL is supplied at build time (`--dart-define=API_BASE_URL`), so the same binary can
point at a local emulator, a laptop or a production host.

## 3. Security design

**Passwords.** Passwords are hashed with **Argon2id**. Before hashing, the plaintext is combined
with a **per-user random salt** and an **application pepper** that is read from the environment
and never stored in the database. The result is written to a dedicated `password_hash` column and
compared through Laravel's hash API. The design is documented in `docs/PASSWORD_SALT_PEPPER.md`.

**Two-factor authentication.** After a correct password, the user receives a **one-time code by
email**. The code is stored hashed, is single-use, expires after five minutes and is rate limited.
The web session is not authenticated until the code is accepted. The API mirrors this with a
two-step handshake: step one returns a short-lived challenge token and step two exchanges a valid
OTP for a long-lived bearer token. See `docs/TWO_FACTOR_AUTHENTICATION.md`.

**Role-based access control.** Roles are `admin`, `receptionist` and `guest`. The ability matrix
is declared once in `backend/config/abilities.php`, applied to route groups and to individual
abilities, and checked again in the controller layer — so hiding a button in the interface is
never the only defence.

**Field encryption.** Sensitive guest fields are encrypted with **AES-256-GCM** using the
authenticated-encryption construction in `FieldEncryptor`, with the column name bound in as
additional authenticated data. Ciphertext is version-tagged so the format can be rotated later.

**Other controls.** CSRF protection on all state-changing web forms, thorough server-side
validation, rate limiting on authentication endpoints, secure session cookies, an append-only
audit log, and last-active-admin protection so the system can never be left without an
administrator.

## 4. Backups and restore points

A scheduled job takes a MySQL dump, compresses it, encrypts it with AES-256-GCM and uploads it to
**Amazon S3** under an IAM policy with least privilege. Retention prunes old backups, the
administrator has an on-demand "back up now" button, and any failure raises an alert email. When
no AWS credentials are present the pipeline runs in a **local simulation mode**, so the whole
feature is demonstrable offline; the real S3 path is used automatically when credentials exist.

**Restore points restore configuration only** — system settings and role assignments — and never
touch guests, bookings or payments. A dedicated automated test performs the full round trip:
take a snapshot, add guest and booking records, change a setting, change a role, add more records,
restore, then assert that the setting and the role have reverted while every guest, booking and
payment is still present, and that the restore itself was audited
(`backend/tests/Feature/ConfigurationRestoreTest.php`).

## 5. Archiving instead of deletion

Deleting a user moves the account to a dedicated **`archived_users`** record rather than removing
the row, so referential history is preserved. Archived accounts are blocked from authenticating,
and the system refuses to archive the last active administrator.

## 6. Seed data

Factories and repeatable seeders create **50+ fictional guests** and **50+ bookings** covering a
range of room types, dates, statuses and payments. All data is synthetic — no real personal
information is used. Re-running the seeder reproduces the same deterministic demo state.

## 7. Testing and verification

The backend suite (`php artisan test`, run against SQLite in memory) contains **13 test files**,
**50 tests and 220 assertions**, all passing. It covers authentication and 2FA, RBAC enforcement,
guest-data encryption, the booking/availability service, last-admin protection, the backup
pipeline, the configuration restore round trip, the REST API, the public site, admin page
smoke tests and the guarded demo-seed command. Writing the tests exposed and fixed real defects, including an availability query
that normalised dates incorrectly, a missing model import in one controller, and rate limiters
that assumed a session on stateless API routes.

The Flutter project passes `flutter analyze` with **no issues** and `flutter test` with
**10 passing tests**. The application also builds for the web, which is how it is demonstrated
live against the local API.

The repository exposes **123 routes** across **22 migrations**.

## 8. Honest limitations

Everything described above was run and observed locally, with the following exceptions, which are
declared rather than implied to work:

* **No live AWS deployment** was performed (no credentials were available), so the S3 upload path
  is implemented but not verified end-to-end; the local simulation mode is what was exercised.
* **No Android APK was built** — the build machine has no JDK or Android SDK. The Android project
  is present and buildable given those toolchains.
* **The Docker / Railway / Render files are scaffolding** and were not built or run, because
  Docker is not installed on this machine. They are provided as a deployment starting point.

## 9. Conclusion

The system meets the assignment brief: a secure, hostable hotel management system serving both web
and mobile clients, with layered authentication, encryption, auditing, automated encrypted backups
and configuration restore points — each backed by an automated test that demonstrates the
behaviour rather than merely asserting it in prose.
