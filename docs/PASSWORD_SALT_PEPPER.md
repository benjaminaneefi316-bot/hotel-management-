# Password Salt & Pepper

How this application stores passwords. The single source of truth for the construction is
`app/Services/PasswordService.php`; the database columns are defined in
`database/migrations/0001_01_01_000000_create_users_table.php`; the hashing parameters in
`config/hashing.php`; the secrets in `config/security.php`.

---

## 1. The construction

```
pre  = sha256( APP_PASSWORD_PEPPER . "\0" . user_salt . "\0" . plaintext )
stored = Hash::make( pre )          # Laravel's default hasher, Argon2id
```

- `APP_PASSWORD_PEPPER` — a **secret** application-wide pepper, from the environment,
  **never stored in the database**.
- `user_salt` — a **per-user random salt**, stored in the `users.password_salt` column.
- `Hash::make(...)` — Laravel's `Hash` facade, which applies **Argon2id** *and adds its own
  per-hash random salt inside the encoded digest*.

So there are three independent layers:

1. the application **pepper** (secret, not in the DB),
2. the explicit **per-user salt** (in the DB),
3. the hashing library's **internal random salt** (inside the encoded digest).

The fixed-length SHA-256 pre-hash also keeps the value handed to the hasher well under
bcrypt's 72-byte input limit, so the construction is safe even if the driver is changed.

### Code path (`PasswordService`)

| Method | Behaviour |
| --- | --- |
| `newSalt()` | `base64(rstrip(strtr(random_bytes(32), '+/', '-_')))` — URL-safe, 32 random bytes, no padding. |
| `hash($plaintext)` | Generates a **new salt** and returns `{ hash: Hash::make(preHash(currentPepper(), salt, $plaintext)), salt }`. |
| `check($plaintext, $hash, $salt)` | Tries the **current** pepper first (`stale = false`), then each **previous** pepper (`stale = true` if one matches). Returns `{ok, stale}`. |
| `verify(...)` | Boolean wrapper around `check()`. |
| `rehash($plaintext, $salt)` | Re-hashes with the **current** pepper, **keeping the same salt**. |
| `needsRehash($hash)` | `Hash::needsRehash(...)` — true when the stored digest's algorithm/cost is outdated. |
| `currentPepper()` | Reads `config('security.pepper')` (i.e. `APP_PASSWORD_PEPPER`); **throws** if shorter than 16 characters. |
| `previousPeppers()` | Reads `config('security.previous_peppers')` (i.e. `APP_PASSWORD_PEPPER_PREVIOUS`, comma-separated). |
| `preHash($pepper, $salt, $plaintext)` | `hash('sha256', $pepper."\0".$salt."\0".$plaintext)`. |

`User::setPassword()` calls `PasswordService::hash()` and assigns both `password` and
`password_salt`. `User::verifyPassword()` calls `PasswordService::check()` with the stored
`password` + `password_salt`. Both columns are in `User::$hidden`, so they never appear in
arrays/JSON.

---

## 2. Where each value lives

| Value | Location | Secret? | Rotatable? |
| --- | --- | --- | --- |
| Argon2id digest (+ library salt) | `users.password` | no (it's a hash) | on password change |
| Per-user salt | `users.password_salt` (`string(64)`) | no (a salt need not be secret) | regenerated on password change |
| Pepper | `APP_PASSWORD_PEPPER` (env → `config/security.pepper`) | **yes** — never in the DB | yes, with `APP_PASSWORD_PEPPER_PREVIOUS` |
| Previous peppers | `APP_PASSWORD_PEPPER_PREVIOUS` (comma-separated) | yes | during a pepper rotation window |

> Never print real secrets. Use `<your-pepper>` and `<your-encryption-key>` in any shared
> configuration.

---

## 3. Argon2id parameters

From `config/hashing.php` (values read from the environment where noted):

| Parameter | Env var | Default | Notes |
| --- | --- | --- | --- |
| Driver | `HASH_DRIVER` | **`argon2id`** | Hard default — even if `HASH_DRIVER` is unset, Argon2id is used (no silent bcrypt fallback). |
| Memory | `ARGON_MEMORY` | **65536** KiB (64 MiB) | Code comment: 64 MiB / 4 iterations meets the OWASP "recommended" profile. |
| Threads | `ARGON_THREADS` | `1` | |
| Time (iterations) | `ARGON_TIME` | `4` | |
| Rehash on login | — | `true` (`config/hashing.php`) | Laravel rehashes when algorithm/cost changes. |

Bcrypt options (`BCRYPT_ROUNDS=12`, etc.) exist but are only used if the driver is changed
away from `argon2id`.

> Note: the working `.env` contains `ARGON_VERIFY=true`, but `config/hashing.php` does **not**
> read an `ARGON_VERIFY` key. It has no effect on hashing.

---

## 3a. Password policy

`AppServiceProvider::registerPasswordRules()` sets the default rule used by password
forms and the API `changePassword`:

- minimum **12 characters**, with letters, **mixed case**, numbers and symbols;
- no hard maximum below 64 characters; input is never silently truncated;
- optional breached-password check when `PASSWORD_CHECK_BREACHED=true` (calls the
  HaveIBeenPwned range API; **off by default** so the project works offline).

---

## 4. Why a per-user salt *and* an application pepper?

| Layer | Protects against | Why it is needed |
| --- | --- | --- |
| Per-user **salt** | Rainbow tables / identical hashes | Two users with the same password get different digests, so an attacker cannot amortise pre-computation. A salt is **not secret** — storing it in the row is standard and safe. |
| Application **pepper** | Offline cracking of a **stolen database dump** | The pepper is not in the database. An attacker who exfiltrates only the DB (columns `password` + `password_salt`) cannot compute candidate digests at all — they must also obtain the environment secret. This is the key protection the assignment asks for. |
| Argon2id internal salt | Rainbow tables at the KDF layer | Standard hasher behaviour.

### Trade-offs (state honestly)

- **A single application-wide pepper is a single point of failure.** If the pepper leaks,
  the extra layer collapses and the per-user salts + Argon2id cost are all that remain.
  Keep it in a secret store, not in the database or repo.
- **Pepper rotation needs a transition window.** Because the pepper is mixed into every
  digest, rotating it would invalidate existing passwords unless the old value is kept in
  `APP_PASSWORD_PEPPER_PREVIOUS` until every user has logged in again.
- **The pre-hash is not a KDF by itself.** It only binds pepper+salt+plaintext and caps the
  length; the real work factor is Argon2id.
- **Per-user salts are not independently rotatable.** There is no salt-rotation mechanism;
  changing a password (or the login upgrade path) simply generates a new salt.
- **Losing the pepper locks everyone out.** Treat `APP_PASSWORD_PEPPER` and
  `APP_ENCRYPTION_KEY` as critical, backed-up secrets.

---

## 4a. Generating the secrets — `security:generate-secrets`

The real command is `app/Console/Commands/GenerateSecuritySecrets.php`
(signature `security:generate-secrets {--write}`). It:

1. Generates `APP_PASSWORD_PEPPER = bin2hex(random_bytes(32))` (64 hex chars).
2. Generates `APP_ENCRYPTION_KEY = base64_encode(random_bytes(32))` (the AES-256-GCM key
   for field **and** backup encryption).
3. Prints both to the console.
4. With `--write`, replaces the matching lines in `.env` (or appends them) — it does **not**
   print them elsewhere and does **not** touch the database.
5. Prints the rotation reminder: move the old pepper into `APP_PASSWORD_PEPPER_PREVIOUS`.

```bash
php artisan security:generate-secrets            # print the values
php artisan security:generate-secrets --write    # also write them to .env
```

It does **not** generate a per-user salt — those are produced at password-set time by
`PasswordService::newSalt()`.

---

## 5. Verification and upgrade flow

`AuthService::attemptPassword()` → `User::verifyPassword()` → `PasswordService::check()`:

```
1. Try  preHash(currentPepper, salt, plaintext)  -> Hash::check(..., stored)
   success  => ok=true,  stale=false
2. Try each previous pepper (APP_PASSWORD_PEPPER_PREVIOUS)
   match    -> ok=true,  stale=true
3. otherwise invalid
```

After a successful password check, `AuthService` upgrades the stored hash when:

- the password verified with an **old pepper** (`stale === true`), or
- `Hash::needsRehash()` reports the cost/algorithm is outdated.

In that case `$user->setPassword($plaintext)` is called and the user is saved quietly, and
the upgrade is audited (`auth.password.rehash`).

> Note: the login upgrade path uses `User::setPassword()`, which generates a **new salt**.
> `PasswordService::rehash()` (which keeps the same salt) exists but is not the path used
> on login.

---

## 5a. Pepper rotation (env vars the code supports)

1. Set a new `APP_PASSWORD_PEPPER=<new-pepper>`.
2. Move the old value into `APP_PASSWORD_PEPPER_PREVIOUS=<old-pepper>`
   (comma-separate multiple values).
3. Existing users keep signing in: `PasswordService::check()` falls back to the previous
   pepper(s) and reports `stale = true`, so their digest is transparently re-hashed with the
   new pepper on that login.
4. Once every user has signed in, clear `APP_PASSWORD_PEPPER_PREVIOUS`.

If you *need* to force everyone to log in again instead, simply drop the previous value —
but that locks out every account whose digest still uses the old pepper.

---

## 6. Things NOT verified / caveats

- There is **no salt-rotation mechanism** and no command to re-salt existing users besides
  changing/resetting their password (which the code does generate a fresh salt for).
- The command generates and prints secrets; it does **not** rotate existing password
  digests, and it does not update a live secret store — you must still place the values in
  AWS Secrets Manager / SSM or your platform's secret store yourself.
- `ARGON_VERIFY` (present in `.env`) is not read by `config/hashing.php`.
- `APP_PASSWORD_PEPPER_PREVIOUS` is a comma-separated string; each entry is trimmed and
  empties are filtered out.
- The construction described here matches the comment in the `users` migration, which
  states `password = Hash::make(PEPPER + password_salt + plaintext)`; the actual
  implementation uses the SHA-256 pre-hash with NUL separators described in §1.
