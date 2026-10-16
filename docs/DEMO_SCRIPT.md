# Demo Script — Secure Hotel Management System

A step-by-step presenter script for demonstrating the working system. It is
written for a student who will drive the demo live and explain what the
audience is seeing at each step.

Every command, route, account and setting below is taken from the current
source: `backend/routes/web.php`, `backend/routes/api.php`,
`database/seeders/*`, `config/abilities.php`, `config/backup.php` and the
corresponding Blade views.

> **Audience:** marker / class / panel.
> **Time:** roughly 15–20 minutes end to end.
> **Golden rule:** if a screen shows an error, read it out — the system
> degrades with a friendly styled page, and that is itself worth showing.

---

## 0. Before you start (5 minutes before the demo)

These commands assume the repository root
`C:\Users\CHARMIES\Desktop\project\hotel-management`.

```powershell
# Put the portable toolchain on PATH for this PowerShell window
. .\tools\set-env.ps1

# Start the portable MariaDB instance (port 3307)
.\tools\start-db.ps1

# Prepare the application
cd backend
composer install
php artisan migrate:fresh --seed   # a clean, fully-seeded database
php artisan serve                  # http://127.0.0.1:8000
```

`php artisan migrate:fresh --seed` runs `DatabaseSeeder`, which seeds in this
order: roles → users → room types → rooms → guests → settings → bookings →
audit logs. You end up with 3 roles, 5 demo users, 6 room types, 30 rooms,
60 guests, 80 bookings and a short audit history.

Keep `php artisan serve` running in its own terminal. Open
**http://127.0.0.1:8000** in a browser.

> If you are re-running the demo, `migrate:fresh --seed` is the quickest reset.

---

## 1. Demo accounts

All demo accounts share one password, read from `SEED_DEMO_PASSWORD`
(default **`Password!2345`**). They are created with Argon2id + per-user salt +
application pepper, exactly like real accounts.

| Name | Username | Email (this is the login) | Role | 2FA |
| --- | --- | --- | --- | --- |
| Benjamin Aneefi | `admin` | `benjaminaneefi316@gmail.com` | Admin | Enabled |
| Liam Chen | `reception` | `reception@azurebay.test` | Receptionist | Enabled |
| Sofia Rossi | `reception2` | `sofia@azurebay.test` | Receptionist | Enabled |
| Noah Williams | `guest` | `guest@azurebay.test` | Guest | Enabled |
| Mei Tanaka | `guest2` | `mei@azurebay.test` | Guest | Disabled |

**Password (all):** `Password!2345`

`SEED_DEMO_PASSWORD` can override the password at seed time, but this is the
default. **Say out loud that this password is a demo credential and must be
changed before any real deployment** — the seeder itself prints
`CHANGE THIS IN PRODUCTION`.

The `admin` user's phone is `0539460356`. Role definitions come from
`RoleSeeder`; the ability matrix is `config/abilities.php`:

* **admin** — everything.
* **receptionist** — day-to-day front desk, no user/role/system administration.
* **guest** — self-service only (own profile and bookings).

---

## 2. The public site (no sign-in)

**Open** `http://127.0.0.1:8000/`.

* Point out the hero section, then the booking search card.
* Say: the marketing site is fully public — no authentication section of the
  app is exposed here.

**Click "Explore rooms"** or navigate to `/accommodation`.

* You should see the six seeded categories, each with its image, capacity and
  starting price:

| Room type | Slug | From / night |
| --- | --- | ---: |
| Garden Twin | `garden-twin` | $130 |
| Classic Queen | `classic-queen` | $145 |
| Deluxe King | `deluxe-king` | $210 |
| Family Room | `family-room` | $260 |
| Executive Suite | `executive-suite` | $320 |
| Presidential Suite | `presidential-suite` | $640 |

**Click a room card** to open the detail page, e.g.
`/accommodation/deluxe-king`.

* Say: images are served from `public/assets/img/` as root-relative paths, so
  they work offline with no CDN.
* Show the amenities list and the "from" price, then the **Check availability**
  form. Submitting here goes to `public.booking.search` (`POST /booking/search`).

Also mention the public pages `/gallery`, `/about` and `/contact`.

---

## 3. Sign in as administrator + two-factor authentication

1. **Go to** `/login` (or click the sign-in link).
2. **Sign in** as:
   * Email: `benjaminaneefi316@gmail.com`
   * Password: `Password!2345`
   * Click **Continue**.
3. The password step **does not log you in**. It redirects to
   `/two-factor/challenge`.
* Because `TWO_FACTOR_EXPOSE_CODE=true` in `backend/.env`, the challenge page
  shows a yellow **"Development mode."** panel with the 6-digit code.
* Say: in production this line is absent and the code arrives by e-mail
  (the mailer is set by environment). The flag is a local convenience only.
* Type the code into the form and submit.

**You are now signed in as Benjamin** and land on the staff dashboard.

Presenter narration:

* Step 1 verified the password (Argon2id, salt + pepper).
* Step 2 issued a one-time code. Tests prove a correct password alone does
  **not** authenticate, and that a code is single-use.

---

## 4. Dashboard and front desk

* On `/dashboard`, call out the KPIs (occupancy, revenue, pending bookings) and
  the room-type occupancy breakdown with images. All figures are computed with
  aggregate queries.
* Open `/front-desk`. This is the operations board:
  * Today's arrivals with a **Check in** action where the booking is ready.
  * Today's departures with a **Check out** action.
  * In-house guests.
  * "Rooms needing attention" with a status dropdown.

You can demonstrate an action here if you wish: click **Check in** on a ready
arrival or change a room's status. Every action is recorded in the audit log.

---

## 5. Create a booking as the guest (and confirm it as staff)

This shows the guest self-service portal and the staff back-office.

### 5a. As the guest

1. Click your name → **Sign out** (or the logout form).
2. Sign in as the guest:
   * Email `guest@azurebay.test`
   * Password `Password!2345`
   * Read the 2FA code from the screen and submit.
3. The guest lands in the guest area:
   `/guest/dashboard`, `/guest/bookings`, `/guest/bookings/create`.
4. Open `/guest/bookings/create`, choose a **Room type**, set arrival and
   departure dates, then click **Submit booking request**.

   * The request is created through `BookingService` with status **Pending**.
   * The availability check is done **server-side**, so the client cannot force
     a room that is already taken.

5. The new booking has an auto-generated reference (e.g. `AB-2026-0009`) and
   appears in **My bookings**.

### 5b. As the receptionist / admin

1. Sign out, then sign in as the admin (or `reception@azurebay.test`).
2. Open `/bookings`, find the new reference, and open it.
3. Point out the **Confirm** button. A Pending booking can be confirmed by
   staff. The detail page also shows rooms, payments and stays.

Presenter point: the same `BookingService` is used by the web UI, the API and
the seeders, and the double-booking rule lives there — not in the view.

---

## 6. Role-based access control (RBAC)

The security boundary is **server-side** via named gates
(`config/abilities.php`), enforced in routes, controllers and the API. Hiding a
menu item is cosmetic.

1. While signed in as the admin, note the sidebar sections: **Users**,
   **Archived users**, **Roles**, **Reports**, **Settings**, **Restore points**,
   **Backups**, **Audit logs**.

2. Sign out and sign in as the receptionist (`reception@azurebay.test`).
   * The dashboard and front desk are still available.
   * The **Administration** and **System** headings are gone from the sidebar.
   * Directly open `/users`. You get the styled **403 Forbidden** page.
   * Do the same for `/restore-points` → 403.

   Presenter line: hiding the menu is not the security boundary; the route
   `can:manage-users` middleware is. The same is covered by
   `RbacAuthorizationTest`.

3. Sign out and sign in as the guest (`guest@azurebay.test`). Try `/dashboard`.

   * The guest is **forbidden (403)** from the staff dashboard and only has the
     guest area.

---

## 7. Configuration restore point + restore

> This is the headline feature. The restore reverts **configuration only** —
   never guests, bookings or payments.

### 7a. Capture a restore point

1. Sign in as the admin and open **Settings** (`/settings`).
   Note the badge **Snapshot-safe** on whitelisted values. Only whitelisted
   settings (see `config/restore-points.php`) can ever be captured.
2. Open **Restore points** → **Capture restore point**
   (`/restore-points/create`).
3. Give it a name (e.g. `Approved baseline`), optionally a description, and
   **tick "Also include role assignments"** so we can also demonstrate a role
   rollback. Click **Capture**.
4. On the restore point detail page, note the item count, the schema version,
   the captured-by, the checksum, and the list of captured values.

### 7b. Change configuration and a role

1. Go to **Settings** and change `hotel_name` (for example to
   `Changed Name Hotel`), then **Save settings**. Point out the on-screen
   reminder that every change is written to the audit trail.
   * You can verify the public name changed by opening the public site again.
2. Go to **Users**, open the account you want to change (e.g. Sofia, or a
   dedicated test user), uncheck their current role and check **Admin**, then
   click **Update roles**.
   * Mention the business rule: the **last active administrator** cannot be
     demoted or archived, so you cannot lock yourself out. (If you try to change
     the only admin, the action is refused with a readable 422 error.)
3. Create another guest and another booking while the configuration is changed,
   so the audience can see them survive the restore.

### 7c. Restore the restore point

1. Go to **Restore points** → open the snapshot → **Preview changes** to see
   current value → captured value for each item.
2. On the restore point page, type the snapshot name exactly to confirm and
   click **Restore configuration**.
3. Navigate back to **Settings** — `hotel_name` is the captured value again.
   Open the user you edited — their role is back to what it was.
   * The restore is transactional and tamper-checked (it verifies the
     snapshot's SHA-256 checksum first).
4. The crucial point: go back to **Bookings** and **Guests** — everything
   created before, during and after the change is still there. A restore can
   **never** roll back guests, bookings, stays or payments.

This exact scenario is asserted in
`backend/tests/Feature/ConfigurationRestoreTest.php`, including that the
restore is written to the audit trail as `restore_point.restore` (success).

---

## 8. Show a backup running (local mode)

1. Open **/backups**.
2. Point out the badge showing the driver and the note:
   *"Local simulation mode is active: no AWS credentials are configured..."*
   The default driver is `local` (`config/backup.php`, `BACKUP_DRIVER`).
3. Click **Run backup now**.

When the run succeeds you get a record in the table with:

* a backup **reference** and filename,
* driver **LOCAL**,
* a size,
* verification status **Verified**,
* and a "success" audit entry.

Click the reference to open the backup detail page. Point out:

* driver and destination,
* **encrypted** — the archive is gzipped and then encrypted with
  AES-256-GCM using the application key,
* a **SHA-256 checksum**,
* the verification message, and a **Re-verify archive** button.

Say: the same pipeline (`dump → gzip → AES-256-GCM → store → verify → audit`)
is proven by the automated `BackupPipelineTest` against local storage, and the
`local` mode is what lets this be demonstrated without an AWS account. The S3
driver and IAM policy are documented in `docs/AWS_DEPLOYMENT.md` (there is no
live AWS deployment).

> **Presenter note.** For a MySQL backup the pipeline shells out to
> `mysqldump`. If that binary is not on your PATH, set `MYSQLDUMP_BINARY` in
> `backend\.env` to the full path (the portable server is at
> `C:\Users\CHARMIES\tools\mariadb\bin\`). A failed run is recorded and shown
> with the reason, rather than crashing. Backups are also scheduled: `php artisan schedule:list`
> shows `backup:run` (daily 02:30) and `backup:prune` (weekly), defined in
> `routes/console.php`. This demo triggers one manually from the UI.

---

## 9. The audit log

Open `/audit-logs`.

* Call out the seeded history (logins, 2FA, booking, payment, settings,
  backup, restore point, archiving, denied access).
* Use the filters: **Action contains** (e.g. `login`), **Event type**
  (`auth`, `security`, `data`, `system`, `admin`), **Outcome**, and the date
  range.
* Open any entry to see the full record: action, event type, outcome, actor,
  IP address, subject and the before/after JSON.

Point out the security behaviour:

* Sensitive values (passwords, salts, tokens, OTPs, keys) are **redacted**
  before being written.
* This is where you verify the demo actions you just performed — the login,
  the 2FA verification, the settings change, the role change, the restore and
  the backup all appear here.

---

## 10. (Optional) The Flutter mobile app

The backend exposes a REST API (`backend/routes/api.php`) consumed by the
Flutter client. If you have Flutter available:

```powershell
cd ..\mobile
flutter analyze
flutter test
```

Ten widget/unit tests pass and analysis is clean. The app talks to the API at
`http://10.0.2.2:8000/api` (the Android emulator's alias for the host). Running
it on a real device needs the Android SDK or Xcode toolchain, which is not
required for the marker demo.

---

## 11. Wrap-up talking points

* **Security first:** 2FA on web and API, Argon2id + per-user salt + pepper,
  AES-256-GCM for sensitive guest fields, server-side RBAC gates.
* **Correctness:** the double-booking rule and last-admin protection are
  enforced in services, with tests.
* **The restore point is configuration-only** by design — it can never roll
  back guests, bookings or payments.
* **Backups are encrypted** before leaving the server, support a local
  no-AWS simulation and a real S3 driver, and are scheduled (`backup:run`
  nightly, `backup:prune` weekly — see `php artisan schedule:list`).
* **Everything is auditable** with a redacted, filterable audit trail.
* **43 backend feature tests + 5 unit tests (48 total)** and **10 Flutter
  tests** pass, plus `flutter analyze` is clean.

---

## Quick reference

| What | URL |
| --- | --- |
| Public home | `/` |
| Accommodation | `/accommodation` |
| Sign in | `/login` |
| 2FA challenge | `/two-factor/challenge` |
| Staff dashboard | `/dashboard` |
| Front desk | `/front-desk` |
| Bookings | `/bookings` |
| Guests | `/guests` |
| Payments | `/payments` |
| Guest area | `/guest/dashboard` |
| Users / roles | `/users`, `/roles` |
| Settings | `/settings` |
| Restore points | `/restore-points` |
| Backups | `/backups` |
| Audit logs | `/audit-logs` |
| API health | `/api/health` |
