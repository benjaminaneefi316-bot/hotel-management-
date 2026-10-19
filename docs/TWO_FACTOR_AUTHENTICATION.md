# Two-Factor Authentication (authenticator app, with email fallback)

The second factor comes from an **authenticator app (TOTP)** — Google Authenticator,
Authy, 1Password, anything that speaks TOTP — as soon as an account has enrolled. Until
an account enrolls, the code is **emailed** exactly as it always was, so nobody can be
locked out halfway through setting up.

Both channels are served by the same step-2 challenge; the login response says which one
applies via a `channel` field (`app` or `email`). See
[§9 Authenticator-app enrolment](#9-authenticator-app-enrolment-totp).

Source files:

- `app/Services/AuthService.php` — step 1 (password) and step 2 (code), and which
  channel serves that code.
- `app/Services/TotpService.php` — TOTP secret, QR payload, verification window,
  recovery codes.
- `app/Services/OtpService.php` — issue/verify/expire/rate-limit the emailed code.
- `app/Http/Controllers/Auth/TwoFactorSetupController.php` — enrol / confirm / remove.
- `app/Http/Controllers/Auth/LoginController.php` — web step 1.
- `app/Http/Controllers/Auth/TwoFactorController.php` — web step 2 + resend.
- `app/Http/Middleware/EnsurePendingTwoFactorChallenge.php` — guards the challenge pages.
- `app/Http/Controllers/Api/ApiAuthController.php` — API step 1/step 2/resend.
- `app/Providers/AppServiceProvider.php` — named rate limiters.
- `config/security.php` — OTP + login tuning, plus `two_factor.totp_window` and
  `two_factor.issuer`.
- `app/Mail/TwoFactorOtpMail.php` + `resources/views/emails/two-factor-otp` — the email.
- `resources/views/auth/two-factor-setup.blade.php` — QR, setup key and confirm screen.

> **Core rule.** A correct password does **not** sign the user in. After step 1 the user
> is still a guest; only a *pending 2FA* marker is set. `Auth::login()` is called **only**
> after a valid code.

---

## 1. Web flow

### Step 1 — password

Route: `POST /login` (`login.attempt`, middleware `throttle:login`), handled by
`LoginController::login`.

1. `LoginRequest` validates `email` (required) and `password` (required).
2. `AuthService::attemptPassword($email, $password, $ip)`:
   - Looks up the user by `email` **or** `username`.
   - Unknown account and wrong password produce the **same** generic result
     (`invalid_credentials`) so the endpoint cannot be used to enumerate accounts.
   - Non-active accounts are refused **before** the password is examined
     (`account_inactive`).
   - A wrong password burns a dummy hash (`Hash::make('timing-equalisation')`) to blunt
     timing-based enumeration.
   - On success, if the stored digest is stale (old pepper) or needs a rehash (cost/
     algorithm change), the password is transparently re-hashed and saved quietly.
3. `AuthService::startChallenge($user, $ip)` decide the next step:
   - If the account has **2FA disabled**, the user is signed in immediately
     (`Auth::login`, session regenerated) and redirected home.
   - Otherwise an OTP is emailed and only the pending markers are stored.
4. Pending markers written to the session (`config/security.php`):
   - `auth.2fa.user_id` (`pending_2fa_session_key`)
   - `auth.2fa.started_at` (`pending_2fa_started_key`)
   - optionally `two_factor.dev_code` (dev exposure only).
   The session id is regenerated and the user is redirected to `two-factor.challenge`.

### The challenge page

Routes (middleware group `pending-2fa`):

| Method | Route | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/two-factor/challenge` | `two-factor.challenge` | `pending-2fa` |
| POST | `/two-factor/challenge` | `two-factor.verify` | `pending-2fa`, `throttle:two-factor` |
| POST | `/two-factor/resend` | `two-factor.resend` | `pending-2fa`, `throttle:otp-resend` |

`EnsurePendingTwoFactorChallenge` allows these pages for a guest session **only** when:

- both pending session keys are present; and
- the challenge is younger than `TWO_FACTOR_CHALLENGE_MINUTES` (default **10** minutes);
- the referenced user still exists and is **active**.

Otherwise it clears the markers and redirects to login with an explanatory error.

### Step 2 — code

`TwoFactorController::verify`:

1. Resolves the user from the pending session id.
2. `AuthService::verifyChallenge($user, $code)` → `OtpService::verify(...)`.
3. On success: `Auth::login($user)`, regenerate the session, clear the pending markers
   (and the dev-code flash), then redirect intended.
4. On failure: return with an error message from
   `AuthService::reasonMessage($reason)`.

`TwoFactorController::show` pulls the one-shot `two_factor.dev_code` flash (if any) and
passes it to the `auth.two-factor` view.

2FA success/failure is audited (`auth.2fa.failed`, `auth.2fa.verified`,
`auth.login.success`).

---

## 2. API flow

Routes in `routes/api.php`:

| Method | Route | Middleware |
| --- | --- | --- |
| POST | `/api/auth/login` | `throttle:login` |
| POST | `/api/auth/two-factor` | `throttle:two-factor` |
| POST | `/api/auth/resend` | `throttle:otp-resend` |

The API is **stateless**, so instead of a session marker it issues a short-lived
**challenge token**.

### Step 1 — `POST /api/auth/login`

`ApiAuthController::login`:

1. Validates `email` and `password`; calls `AuthService::attemptPassword`.
2. Failure → `401` (`invalid_credentials`) or `403` (`account_inactive`).
3. If 2FA is **disabled**: returns `status: authenticated` with a long-lived token.
4. Otherwise returns:

```json
{
  "status": "two_factor_required",
  "challenge_token": "<plaintext sanctum token>",
  "expires_in": 600,
  "email": "<user email>"
}
```

The challenge token is a Sanctum token named `2fa-challenge`, able to
`2fa:challenge`, expiring after **10 minutes** (`now()->addMinutes(10)`).

### Step 2 — `POST /api/auth/two-factor`

Validates `challenge_token` + `code` (max 12 chars). `resolveChallengeToken()` enforces:

- the token exists and `can('2fa:challenge')`;
- it is not past `expires_at` (expired tokens are deleted);
- the owning user still exists and is **active**.

Then `verifyChallenge` runs. On success the challenge token is **deleted** and a
`mobile` token (abilities `['*']`, valid **30 days**) is returned with the user payload.
On failure → `422` with a reason.

### Resend — `POST /api/auth/resend`

Validates the `challenge_token`, re-issues via `startChallenge`, and returns
`{ "status": "sent" }` (or `400 2fa_disabled`, or `429` when rate-limited/cooldown).

Authenticated endpoints (`/api/auth/me`, `/api/auth/logout`, `/api/auth/password`,
bookings, front desk, …) sit behind `auth:sanctum` + `active`.

---

## 3. Code generation, storage, expiry and rate limiting

All from `OtpService` + `config/security.php`.

| Property | Value / behaviour |
| --- | --- |
| Purpose | `login_2fa` (`security.otp.purpose`) |
| Length | `OTP_LENGTH`, default **6** digits |
| Generation | `random_int()` then `str_pad` to length — cryptographically random |
| At rest | `code_hash = Hash::make($code)`, column `email_otps.code_hash`, **hidden** from arrays/JSON |
| Plaintext | Never stored, never logged |
| Expiry | `OTP_EXPIRY_MINUTES`, default **5** minutes (`expires_at`) |
| Attempts | `OTP_MAX_ATTEMPTS`, default **5**; **every** attempt (valid or not) is counted |
| Single use | On success `consumed_at = now()` |
| New code | Issuing invalidates all previous **unconsumed** codes for the purpose (in a transaction) |
| Resend cooldown | `OTP_RESEND_COOLDOWN_SECONDS`, default **60** s |
| Hourly cap | `OTP_MAX_RESENDS_PER_HOUR`, default **5** |

### Verification (`OtpService::verify`)

1. Find the latest **unconsumed** code for the purpose → else `no_code`.
2. Expired → `expired`.
3. No attempts left → `too_many_attempts`.
4. Increment `attempts`, then `Hash::check(trim($code), $code_hash)`.
5. Wrong → `invalid`; right → mark consumed (`verified`).

### Rate limiters (`AppServiceProvider::registerRateLimiters`)

| Limiter | Key | Limit |
| --- | --- | --- |
| `login` | `email + IP` | `LOGIN_MAX_ATTEMPTS` per minute (default **5**), with a custom response that writes an audit entry |
| `login` (secondary) | `IP` | **20** per minute |
| `two-factor` | pending-2FA user id (web) / `challenge_token` (API) / IP fallback | **10** per minute, key hashed with SHA-256 |
| `otp-resend` | same key as above | **2** per minute |
| `api` | authenticated user id, else IP | **60** per minute |

### Housekeeping methods not currently wired

`OtpService::purgeExpired()` (deletes codes expired/consumed more than a day ago) **is
wired**: `routes/console.php` registers an `otp:prune` command that calls it and schedules
it hourly (verify with `php artisan schedule:list`). `OtpService::hasActiveCode()` is used
by the issue flow to decide whether a fresh code is needed.

---

## 4. Email delivery

`OtpService::issue()` sends `App\Mail\TwoFactorOtpMail` to the user's email **after** the
code row is committed:

- Subject: `Your verification code - <APP_NAME>`.
- View: `emails.two-factor-otp`, receives `name`, `code`, `expiresInMinutes`.
- Sent synchronously via `Mail::to($user->email)->send(...)` using the configured
  `MAIL_MAILER`.

| Environment | Effect |
| --- | --- |
| `MAIL_MAILER=log` (the dev default) | The email (and code) is written to `storage/logs/laravel.log`. No real inbox. |
| `MAIL_MAILER=smtp` / `ses` / `postmark` / `resend` / … | Real delivery. `config/mail.php` supports these transports. |

In production you must configure a real mailer, or users cannot complete step 2.

---

## 5. The `TWO_FACTOR_EXPOSE_CODE` dev flag

`AuthService::devCodeExposed()` returns true only when **both**:

```php
app()->environment('local', 'testing') && (bool) env('TWO_FACTOR_EXPOSE_CODE', false)
```

When true, the issued code is returned/flashed as `dev_code` (web: a
`two_factor.dev_code` session flash; API: a `dev_code` field) so the flow can be
demonstrated without a mailbox.

- The code is still hashed at rest and **never logged**.
- Because of the environment check, setting it in production has no effect — but it must
  be `false` in production regardless (belt and braces).
- Leaving it on in a `local`/`testing` environment is fine; turning it on anywhere reachable
  by real users would defeat the second factor.

---

## 6. Resend behaviour and cooldowns

**Web** — `TwoFactorController::resend`:

1. Resolves the pending user; otherwise redirects to login.
2. Calls `startChallenge` → `OtpService::issue`.
3. If `dev_code` is exposed, flashes it again.
4. Returns `back()` with a status/error message.

**API** — `ApiAuthController::resend`: same logic, returns `sent` / `400` / `429`.

`OtpService::issue` enforces, in order:

1. **Resend cooldown** — if the most recent code is still unconsumed and was sent less than
   `OTP_RESEND_COOLDOWN_SECONDS` (default 60 s) ago → `cooldown` with `retry_after`
   (`OTP_MAX_RESENDS_PER_HOUR`, default 5 per hour) → `hourly_limit` with
   `retry_after = 3600`.
2. **Hourly ceiling** — counts codes with `last_sent_at >= now()-1h`; if
   `>= OTP_MAX_RESENDS_PER_HOUR` (default 5) → `hourly_limit`.

Additionally the `otp-resend` HTTP limiter caps resend requests at **2 per minute**, and
`reasonMessage()` maps each failure to a friendly message.

---

## 7. Enabling / disabling 2FA per user

Per-user preference column: `users.two_factor_enabled` (boolean, migration default
**`true`**).

- It is in `User`'s `$fillable` and cast to boolean.
- Admins toggle it on the **user create/edit forms**:
  - `UserController::store` / `UserController::update` validate
    `two_factor_enabled` as `nullable|boolean` (also `sometimes|boolean` in
    `StoreUserRequest` / `UpdateUserRequest`) and persist it with
    `$request->boolean('two_factor_enabled')`.
  - Route access is `can:manage-users` (**admin only**).
- `AuthService` honours it: when `! $user->two_factor_enabled`, both `startChallenge()`
  and `verifyChallenge()` short-circuit to success (`2fa_disabled`), so a correct password
  signs the user straight in.

Seeded accounts (`database/seeders/UserSeeder.php`):

| Username | Role | `two_factor_enabled` |
| --- | --- | --- |
| `admin` | Admin | true |
| `reception` | Receptionist | true |
| `reception2` | Receptionist | true |
| `guest` | Guest | true |
| `guest2` | Guest | **false** |

> Trade-off: disabling 2FA for an account reduces that account to single-factor
> (password-only) sign-in. Keep it enabled for administrators.

### Break-glass — lost phone or locked out

If someone can no longer read their code, clear the enrolled key from the server. This
is a console command, not an HTTP endpoint, so it needs shell access:

```powershell
# confirm first; add --force to skip the prompt
php artisan two-factor:disable someone@example.com
```

It reports which account it touched and whether a key was removed. The account then
falls back to the emailed code — so while `MAIL_MAILER=log` (no real mail) the very next
step should be re-enrolling an authenticator app from the profile page, see
[§9](#9-authenticator-app-enrolment-totp).

---

## 8. Things NOT verified / caveats

- `OtpService::purgeExpired()` **is** now scheduled (`otp:prune`, hourly) and
  `hasActiveCode()` is used by the issue flow; neither is covered by an automated test.
- `config/sanctum.php` is **not present** in the repo (Sanctum 4 defaults apply); token
  lifetimes are set explicitly in `ApiAuthController` (10 min challenge, 30 days mobile),
  not in config.
- `docs/INSTALLATION.md` must list `guest2` with 2FA **off**, not "on": `UserSeeder.php` is
  authoritative and sets `two_factor` to `false` for that account.
- The 2FA email is sent with `Mail::send()` (not queued) even though the Mailable uses the
  `Queueable` trait; delivery happens within the request.
- **Cross-request replay is not prevented.** `TotpService::verify()` keeps accepted codes
  in an in-memory array, which PHP discards at the end of the request, so a code shown on
  screen could be submitted again later inside its ~60 s window. Fixing this properly
  needs a persisted "last accepted counter" column on `users`. Repeats *within* a single
  request are rejected.
- **Recovery codes are not wired up** and are therefore no longer displayed anywhere.
- **Authenticator enrolment is not covered by the committed phpunit suite** (50 tests);
  it was exercised by an end-to-end script run against a live `php artisan serve`, which
  was deleted afterwards. Re-verify manually after touching the flow.

---

## 9. Authenticator-app enrolment (TOTP)

Libraries: `pragmarx/google2fa` (RFC 6238) and `bacon/bacon-qr-code`, which renders the
QR as **inline SVG** — no `GD`/Imagick required, so the slim production image can still
draw it. The secret never leaves the server except inside that SVG and the setup key.

Columns, from `2026_10_19_000001_add_two_factor_authenticator_to_users_table.php`:

| Column | Type | Notes |
| --- | --- | --- |
| `two_factor_secret` | `text`, nullable | cast `encrypted`, listed in `$hidden`, never serialised |
| `two_factor_confirmed_at` | `timestamp`, nullable | set only after a correct code |

An account uses the authenticator app only when **all three** hold:

```php
$user->usesAuthenticatorApp();
// two_factor_enabled && secret !== null && confirmed_at !== null
```

Any other combination keeps the emailed-code path exactly as it was, so there is no
state in which a half-finished setup blocks a sign-in.

### Routes (authenticated web session; not API endpoints)

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| `GET` | `/profile/two-factor` | `two-factor.setup` | QR + setup key, or the "connected" screen |
| `POST` | `/profile/two-factor` | `two-factor.confirm` | verify a live code, then enrol |
| `DELETE` | `/profile/two-factor` | `two-factor.remove` | drop the key, back to emailed codes |

### The three steps

1. **Start** (`TwoFactorSetupController::create`) — generates a Base32 secret and holds
   it in the *session* (`2fa.pending_secret`) for **15 minutes**. Nothing is written to
   the user row, so closing the tab costs nothing. Re-opening within those 15 minutes
   reuses the same secret, so the QR never silently changes under someone mid-setup.
2. **Confirm** (`::confirm`) — requires exactly six digits, then *reads* (never pulls)
   the pending secret, discards it if it is older than 15 minutes, and verifies against
   a throwaway `User` probe so the real account stays untouched until the code checks
   out. On success it writes `two_factor_secret`, `two_factor_confirmed_at` and
   `two_factor_enabled`, clears the session copy and audits `auth.2fa.enrol.success`. A
   wrong code only audits `auth.2fa.enrol.failed` and leaves the setup intact, so the
   user simply types the next code.
3. **Remove** (`::destroy`) — clears `two_factor_secret` and `two_factor_confirmed_at`
   and audits `auth.2fa.enrol.removed`. `two_factor_enabled` is deliberately left as-is:
   with no key the account falls back to emailed codes on its own.

### Verification rules

- `TOTP_WINDOW` (`two_factor.totp_window`, default `1`) accepts one 30-second step of
  drift either side, i.e. roughly ±1 minute, which mainstream apps need.
- The submitted value must match `^\d{6}$` before it reaches the library.
- Rejects a duplicate code **within one request only** — see §8 for why that is not
  cross-request replay protection.
- The login response for an enrolled account carries `channel: app` and **never** a
  `dev_code`, even when `TWO_FACTOR_EXPOSE_CODE=true`; that flag only opens the emailed
  path.

### Configuration

| Env | Config key | Default | Meaning |
| --- | --- | --- | --- |
| `TOTP_WINDOW` | `two_factor.totp_window` | `1` | steps of clock drift accepted |
| `TOTP_ISSUER` | `two_factor.issuer` | `Azure Bay Hotel` | label shown beside the account in the app |

### Still to build

- **Recovery codes** — see §8; not stored, not accepted, not shown.
- **API enrolment** — the JSON API can *consume* a TOTP code but cannot start enrolment;
  that is web-only at `/profile/two-factor`.
