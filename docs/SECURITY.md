# Security

This document describes the security controls implemented in the Secure Hotel
Management System, where each one lives in the code, and the honest limits of what
is and is not covered.

The design goal is simple: **a stolen database dump, or a stolen backup file, should
not be enough to sign in as anyone or read a guest's private data.**

---

## 1. At a glance

| Control | Where | Summary |
| --- | --- | --- |
| Password hashing | `App\Services\PasswordService`, `config/hashing.php` | Argon2id + per-user salt + application pepper |
| Two-factor authentication | `App\Services\AuthService`, `App\Services\OtpService` | Emailed one-time code on login |
| Role-based access control | `config/abilities.php`, `AppServiceProvider` gates | Enforced server-side on web **and** API |
| Field encryption | `App\Support\Encryption\FieldEncryptor` | AES-256-GCM for sensitive guest fields |
| Session hardening | `config/session.php`, `bootstrap/app.php` | Encrypted, http-only, same-site cookies |
| CSRF protection | Laravel web middleware | Web forms only (the API is token-based) |
| API authentication | Laravel Sanctum | Bearer tokens, scoped lifetimes |
| Rate limiting | `AppServiceProvider` limiters | Login, 2FA, OTP resend and API throttles |
| Account lifecycle | `App\Http\Middleware\EnsureAccountIsActive` | Archiving/deactivating takes effect immediately |
| Last-admin protection | `App\Services\RoleService` | The final active admin cannot be demoted or archived |
| Audit trail | `App\Services\AuditLogger` | Every sensitive action, with secret redaction |
| Encrypted backups | `App\Services\BackupService` | Encrypted dumps, least-privilege S3 upload |

---

## 2. Password storage

Passwords are **never** stored or hashed anywhere except `PasswordService`. The
construction is:

```
stored = Hash::make( sha256( APP_PASSWORD_PEPPER . "\0" . user_salt . "\0" . plaintext ) )
```

* a **different random salt per user**, stored in `users.password_salt`
  (a salt is not secret; it makes pre-computation attacks useless);
* an **application pepper** in the `APP_PASSWORD_PEPPER` environment variable
  that is *never written to the database*;
* Laravel's **Argon2id** hash on top.

Full rationale, parameters and rotation instructions are in
[`PASSWORD_SALT_PEPPER.md`](PASSWORD_SALT_PEPPER.md).

`config/hashing.php` sets `argon2id` as the default driver, so even a missing
`HASH_DRIVER` cannot silently fall back to bcrypt. Verification is constant-time
and a valid login can transparently re-hash with a newer pepper
(`APP_PASSWORD_PEPPER_PREVIOUS`).

Passwords must be at least 12 characters with mixed case, digits and symbols
(`AppServiceProvider::registerPasswordRules`). An optional breach check
(`PASSWORD_CHECK_BREACHED=true`) uses the HaveIBeenPwned range API; it is off by
default so the project works offline.

---

## 3. Two-factor authentication

Both the web application and the JSON API require an emailed one-time code:

1. The password is checked (step 1). If it is correct the user is **not** signed in
   yet — only a short-lived "pending 2FA" marker is set.
2. A single-use, hashed, five-minute code is emailed. The user submits it (step 2)
   and only then is the session/token established.

Codes are hashed at rest, limited to `OTP_MAX_ATTEMPTS` attempts, cooldown-limited
for resends, and the challenge expires after
`TWO_FACTOR_CHALLENGE_MINUTES` (default 10). All of this is enforced by
`OtpService`/`AuthService`, not by the UI.

> **Development flag:** `TWO_FACTOR_EXPOSE_CODE=true` makes the backend return/show
> the code so the flow can be demonstrated without a mailbox. It is honoured **only**
> when `APP_ENV` is `local` or `testing`. It must be `false`/absent in production.
> See [`TWO_FACTOR_AUTHENTICATION.md`](TWO_FACTOR_AUTHENTICATION.md).

---

## 4. Role-based access control (RBAC)

The single source of truth is the matrix in `config/abilities.php`:

* `admin` — full access;
* `receptionist` — day-to-day front-desk operations;
* `guest` — self-service (own bookings) only.

At boot, `AppServiceProvider` turns every ability into a Laravel **gate**. Because it
is a gate, it is enforced in the same way from `can:` route middleware, controllers,
Blade `@can` directives, and the JSON API. **Hiding a button in the UI changes
nothing** — the server refuses the action regardless.

Two important rules:

* A user whose status is not `active` holds **no** ability at all (the gate returns
  `false` before checking roles).
* An active administrator passes `Gate::after` for any non-restricted ability.

Every important route group is wrapped in `can:` middleware in `routes/web.php` and
`routes/api.php`.

---

## 5. Sensitive-data encryption

Guest fields such as phone number, national ID and address are encrypted with
**AES-256-GCM** before they are written to MySQL (`FieldEncryptor`). The envelope is:

```
"gcm1:" . base64( nonce[12] . tag[16] . ciphertext )
```

* a **fresh 96-bit nonce** is generated for every encryption;
* the 128-bit **authentication tag** means tampering is detected and decryption fails
  safely (returning `null`, never corrupted data);
* the **column name is passed as Additional Authenticated Data**, so a ciphertext
  cannot be moved from one column to another;
* the key (`APP_ENCRYPTION_KEY`) lives **outside** the database — in `.env` locally
  and in AWS Secrets Manager / SSM in production.

libsodium's AES-256-GCM is used when the CPU supports it, with a transparent OpenSSL
fallback; the envelope is identical either way.

---

## 6. Sessions, CSRF and the API

* Sessions are stored in the database, **encrypted** (`SESSION_ENCRYPT=true`), and
  use http-only, same-site cookies. In production set
  `SESSION_SECURE_COOKIE=true` so cookies are only sent over HTTPS.
* Web forms are protected by Laravel's **CSRF** token middleware; forms include
  `@csrf` and state-changing verbs use `@method`.
* The JSON API does **not** use cookies: it authenticates with **Sanctum bearer
  tokens**. Step 1 returns a short-lived challenge token; step 2 exchanges the OTP
  for a longer-lived `mobile` token. Tokens are revocable (`POST /api/auth/logout`).
* Business-rule violations (double booking, last-admin protection, …) are returned
  as HTTP **422** with a readable message; authorisation failures as **403**;
  missing/expired credentials as **401**.

---

## 7. Rate limiting

Registered in `AppServiceProvider`, keyed on a stable identity so one attacker cannot
lock out unrelated accounts:

| Limiter | Key | Limit |
| --- | --- | --- |
| `login` | email + IP | `LOGIN_MAX_ATTEMPTS` per minute (default 5) |
| `login` (secondary) | IP | 20 per minute |
| `two-factor` | pending-2FA user / challenge token / IP | 10 per minute |
| `otp-resend` | same as above | 2 per minute (plus the hourly cap in `OtpService`) |
| `api` | authenticated user id, else IP | 60 per minute |

Exceeding the login limit is written to the audit trail as a failed security event.

---

## 7a. Input validation and output escaping

Every write endpoint validates through a dedicated **Form Request** (see
`app/Http/Requests/`), so validation rules live in one place per resource and apply
to both the web UI and the API. Blade auto-escapes output (`{{ }}`), and the audit
logger strips secret-bearing keys before persisting anything.

---

## 8. Account lifecycle and the last-admin rule

Users have a status of `active`, `inactive` or `archived`:

* **Archiving** copies the account into the dedicated `archived_users` table
  (`UserArchiveService`); the original row is deactivated.
* `EnsureAccountIsActive` runs on **every** authenticated request. If the signed-in
  user is no longer active, they are logged out, the session is invalidated, and the
  request is refused — so revoking access does not wait for the session to expire.
* A signed-in user with a pending 2FA challenge is re-checked too
  (`EnsurePendingTwoFactorChallenge`); if the account is no longer active the
  challenge is discarded.
* **Last-admin protection** (`RoleService`) refuses any change that would leave the
  system without an active administrator — you cannot demote or archive the final
  admin.

---

## 9. Auditing

`AuditLogger` records logins, 2FA events, role changes, archiving, setting changes,
backups, restores and access denials, together with the actor, IP, user agent and a
before/after snapshot. Secrets are **redacted** by key name (`password`, `token`,
`otp`, `pepper`, `secret`, …) before anything is stored, and the logger never throws
into the request it is describing. Administrators can browse the trail at
`/audit-logs`.

---

## 10. Secrets management

* No secret is committed. `.env` is git-ignored; `.env.example` contains only
  placeholders.
* `php artisan security:generate-secrets --write` generates `APP_PASSWORD_PEPPER`
  and `APP_ENCRYPTION_KEY` and writes them to `.env`.
* In production, inject secrets as environment variables from AWS Secrets Manager /
  SSM Parameter Store (or the platform's secret store) rather than files.
* Rotating the pepper is supported: move the old value into
  `APP_PASSWORD_PEPPER_PREVIOUS` so existing passwords keep verifying until each user
  has signed in again.

---

## 11. Backups

Backups (`BackupService`) are **encrypted** dumps uploaded to a **private** S3
bucket using a least-privilege IAM policy, with versioning and a retention policy.
A local simulation mode (`BACKUP_DRIVER=local`) exercises the whole pipeline without
any AWS account. Backups are covered in
[`BACKUP_RESTORE.md`](BACKUP_RESTORE.md) and [`AWS_DEPLOYMENT.md`](AWS_DEPLOYMENT.md).

---

## 11a. Transport security

TLS is expected to be terminated by the host or reverse proxy (HTTPS in front of the
app). Set `APP_URL` to the `https://` URL and `SESSION_SECURE_COOKIE=true` so
cookies and the `Secure` attribute line up. HSTS and other transport headers should
be added at the proxy/load-balancer or CDN.

---

## 11b. Threat model — what this protects against, and what it does not

**In scope / mitigated**

* Offline cracking of a stolen `users` dump (pepper is not in the database).
* Password brute force and credential stuffing (Argon2id, rate limits, 2FA).
* Stolen backups leaking data (backups are encrypted).
* Leaked guest PII from a database dump (fields are encrypted, key is external).
* Privilege escalation through the UI (RBAC is server-side).
* Retaining access after an account is revoked (immediate session invalidation).

**Out of scope (state honestly)**

* No hardware security keys / WebAuthn — the second factor is an emailed code.
* No network-layer protections (WAF, DDoS scrubbing) — those belong to the host.
* The development convenience `TWO_FACTOR_EXPOSE_CODE` must be off in production;
  leaving it on would defeat the second factor.
* Automated tests are not a penetration test. See `TESTING.md` for what is covered.

---

## 12. Reporting a problem

This is an academic project. If you find a security issue, open an issue describing
the steps to reproduce; do not include real personal data.
