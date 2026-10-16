# 🏨 Secure Hotel Management System

A college assignment project implementing a **secure, full-stack hotel management system**:

* a **responsive web application** (public booking site + staff/admin back office),
* a **Flutter mobile application** (guest-facing, talks to the REST API),
* a **MySQL / MariaDB** database,
* **email OTP two-factor authentication**,
* **role-based access control (RBAC)** enforced on the server,
* **salted + peppered password hashing** (Argon2id),
* **AES-256-GCM field encryption** for sensitive guest data,
* **automated, encrypted AWS S3 backups** (with a local simulation mode),
* **configuration-only restore points** with an automated regression test, and
* **user archiving** (soft delete) with last-active-admin protection.

> **Status:** complete and tested. `php artisan test` → **50 tests / 220 assertions passing**;
> `flutter test` → **10 tests passing**; `flutter analyze` → **no issues**.
> See [`docs/`](docs/) for the full documentation set.

---

## 1. Repository layout

```
hotel-management/
├─ backend/     Laravel 13 (PHP 8.4) web application + REST API
│  ├─ Dockerfile, railway.json   container/host scaffolding (see §7)
│  └─ .env.production.example    production variable checklist
├─ mobile/      Flutter mobile application (talks to the backend API)
├─ docs/        Documentation and the assignment report
├─ tools/       Portable toolchain + local database helper scripts
├─ render.yaml  Render blueprint (deployment scaffolding – see §7)
└─ README.md
```

---

## 2. Documentation index

| Document | What it covers |
| --- | --- |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Portable toolchain, database, migrate/seed, running web + mobile |
| [`docs/SECURITY.md`](docs/SECURITY.md) | Threat model and every security control, mapped to code |
| [`docs/PASSWORD_SALT_PEPPER.md`](docs/PASSWORD_SALT_PEPPER.md) | Argon2id + per-user salt + application pepper design |
| [`docs/TWO_FACTOR_AUTHENTICATION.md`](docs/TWO_FACTOR_AUTHENTICATION.md) | Email OTP flow for web and API |
| [`docs/BACKUP_RESTORE.md`](docs/BACKUP_RESTORE.md) | Backup pipeline, S3, retention, restore points |
| [`docs/AWS_DEPLOYMENT.md`](docs/AWS_DEPLOYMENT.md) | Target cloud architecture + container/blueprint files |
| [`docs/API.md`](docs/API.md) | Every REST endpoint, auth flow, payloads, error contract |
| [`docs/RAILWAY_DEPLOYMENT.md`](docs/RAILWAY_DEPLOYMENT.md) | Step-by-step: put the app online on Railway with MySQL (GitHub → deploy → go live) |
| [`docs/TESTING.md`](docs/TESTING.md) | How to run the suites; what each test proves |
| [`docs/DEMO_SCRIPT.md`](docs/DEMO_SCRIPT.md) | A step-by-step script for demonstrating the system |
| [`docs/ASSIGNMENT_REPORT.md`](docs/ASSIGNMENT_REPORT.md) | The two-page assignment write-up |

---

## 3. Local environment (how this project is run here)

The project was built on Windows **without administrator rights**, so a portable per-user
toolchain was used instead of machine-wide installers:

| Tool | Version | Location |
| --- | --- | --- |
| PHP | 8.4.26 (NTS) | `C:\Users\CHARMIES\tools\php84` |
| Composer | 2.10.3 | `C:\Users\CHARMIES\tools\composer.phar` |
| Git | 2.56 | `C:\Users\CHARMIES\tools\git` |
| Flutter | 3.47.7 | `C:\Users\CHARMIES\tools\flutter` |
| MariaDB | 11.4.4 | `C:\Users\CHARMIES\tools\mariadb` (server), config `C:\Users\CHARMIES\tools\my.ini`, data `C:\Users\CHARMIES\tools\mariadb-data`, port **3307** |
| Node / npm | 24.19 / 11.17 | `C:\Program Files\nodejs` (pre-installed) |

Full step-by-step instructions: **[`docs/INSTALLATION.md`](docs/INSTALLATION.md)**.

---

## 4. Quick start (web application)

```powershell
# 0. Put the portable tools on PATH for this PowerShell session
. .\tools\set-env.ps1

# 1. Start the local MariaDB server (idempotent — safe to re-run)
.\tools\start-db.ps1

# 2. Configure + migrate + seed the Laravel app
cd backend
copy .env.example .env          # first run only
composer install
php artisan key:generate
php artisan migrate:fresh --seed

# 3. Run it
php artisan serve                # http://127.0.0.1:8000
```

Stop the database afterwards with `.\tools\stop-db.ps1`.

Run the test suites:

```powershell
cd backend
php artisan test                 # 50 tests / 220 assertions

cd ..\mobile
flutter pub get
flutter analyze                  # no issues
flutter test                     # 10 tests
```

---

## 5. Demo accounts

Created by `php artisan migrate:fresh --seed` (see `backend/database/seeders/UserSeeder.php`).
**Shared demo password:** `Password!2345` — change it before any real deployment.
Override it for a run with the `SEED_DEMO_PASSWORD` environment variable.

| Role | Username | Email | 2FA |
| --- | --- | --- | --- |
| **Administrator (super admin)** | `admin` | `benjaminaneefi316@gmail.com` | on |
| Receptionist | `reception` | `reception@azurebay.test` | on |
| Receptionist | `reception2` | `sofia@azurebay.test` | on |
| Guest | `guest` | `guest@azurebay.test` | on |
| Guest | `guest2` | `mei@azurebay.test` | off |

The super-admin account also owns the configured contact details: phone **0539460356**,
email **benjaminaneefi316@gmail.com**. These are stored in `system_settings` and are editable
from **Admin → Settings** — nothing is hard-coded in views.

**Reading the OTP in local development.** The development mailer is the `log` driver, so the
one-time code is written to `backend/storage/logs/laravel.log`. When `APP_ENV` is `local` or
`testing` **and** `TWO_FACTOR_EXPOSE_CODE=true`, the login screen also shows the code inline and
the API returns it as `dev_code`. This must never be enabled in production.

---

## 6. Features at a glance

**Public site** — home, accommodation catalogue, gallery, about, contact and a booking search
that checks live availability. Images are served locally with lazy loading, descriptive alt
text and a placeholder fallback, so there are no broken images.

**Guest portal** — sign in (with email OTP), browse room types, create and manage bookings,
view payments.

**Staff workspace** — dashboard KPIs, front desk / check-in, reservations, guests, payments.

**Administrator** — users & roles (RBAC matrix in `backend/config/abilities.php`), room types
and rooms, system settings, audit log, archived users, backups, restore points and reports.

**REST API** — Sanctum bearer tokens with a two-step 2FA handshake; documented in
[`docs/API.md`](docs/API.md).

**Security** — Argon2id + per-user salt + app pepper; AES-256-GCM encryption of guest PII;
CSRF, validation, rate limiting, secure sessions and audit logging. Details in
[`docs/SECURITY.md`](docs/SECURITY.md).

---

## 7. Honest scope notes (what is verified vs. what is scaffolding)

* **Verified locally.** Web app renders and serves all routes; `php artisan test` (50 tests,
  220 assertions) and `flutter test` (10 tests) pass; `flutter analyze` is clean. The Flutter
  app also builds for web (`flutter build web`) and runs against the local API.
* **Production config caching is verified.** `php artisan config:cache`, `route:cache` and
  `view:cache` (what the container entrypoint runs on boot) all succeed against this
  codebase, and the app serves correctly with them active — the one closure route was moved
  into a controller for exactly this reason.
* **AWS S3 backups are unverified end-to-end.** The pipeline is implemented with a *local
  simulation mode* that needs no AWS credentials, plus a real S3 driver used when credentials
  are present. No live AWS deployment was performed because no credentials were available.
* **Container / cloud files are scaffolding and have not been built or run.** `backend/Dockerfile`,
  `backend/Procfile`, `backend/railway.json` and `render.yaml` are provided as a starting point;
  Docker is not installed on this machine, so no image was built. The Railway walkthrough
  ([`docs/RAILWAY_DEPLOYMENT.md`](docs/RAILWAY_DEPLOYMENT.md)) is the standard flow to follow and
  verify on the first deploy; see also [`docs/AWS_DEPLOYMENT.md`](docs/AWS_DEPLOYMENT.md).
* **No Android APK was produced** — this machine has no JDK or Android SDK. The `mobile/android/`
  project is present and buildable given those toolchains; the web build is used as the live demo.
* **Restore points restore *configuration only*** (settings and role assignments), never a full
  database wipe — so guests, bookings and payments are preserved. Proven by
  `backend/tests/Feature/ConfigurationRestoreTest.php`.
