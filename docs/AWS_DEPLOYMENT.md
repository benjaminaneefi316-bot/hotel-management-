# AWS & Production Deployment

Deployment guide for the **Secure Hotel Management System** Laravel backend
(`backend/`). It covers Railway, Render and a generic VPS/nginx host, the full
environment-variable reference, the least-privilege S3 IAM policy for backups, S3
bucket hardening, migrations/scheduler/queues and a production hardening checklist.

Every statement about the application comes from files in this repository. Steps that
are standard AWS/ops practice (not configured by this repo) are labelled **[guidance]**.

---

## 0. Context

- The backend is a single Laravel application that serves **both** the web UI and the
  JSON API. The browser uses session cookies; the mobile app uses Sanctum bearer tokens.
- It is **stateful**: it needs a **MySQL/MariaDB database**, persistent sessions/cache
  and a scheduler. Vercel was rejected for exactly this reason (it cannot run PHP with a
  persistent MySQL connection, cron/cron-equivalent workers, or long-lived processes),
  so a Railway/Render-style **PHP host + managed MySQL** (or a VPS) is used instead.

### Files already in the repo that drive deployment

| File | What it is |
| --- | --- |
| `backend/Dockerfile` | Production image: `php:8.4-apache`, document root `/var/www/html/public`, installs `pdo_mysql mbstring sodium zip opcache password` + Argon2, runs `composer install --no-dev`. |
| `backend/docker/entrypoint.sh` | Binds Apache to `$PORT`, creates writable dirs, optionally runs migrations (`RUN_MIGRATIONS=true`) and caches config/routes/views (`CACHE_APP_CONFIG`, default `true`). |
| `backend/Procfile` | Buildpack hosts (Render/Heroku/Dokku): `web: vendor/bin/heroku-php-apache2 public/`. |
| `backend/railway.json` | Railway: `DOCKERFILE` builder, `startCommand: apache2-foreground`, healthcheck `/up`. |
| `render.yaml` (repo root) | Render Blueprint: one web service + a managed database `hotel-db`. |

> The deployment files above are described in the repo as **scaffolding that was written
> but not executed** during development. Treat them as a starting point and verify on the
> platform.

---

## 1. Hosting options

### Option A — Railway

The repo ships `backend/railway.json`, which selects the Dockerfile builder and health
check.

1. **Create a project** and connect the Git repository.
2. Set the service **root directory to `backend/`** (so `Dockerfile` and `railway.json`
   resolve). Railway reads `railway.json` and builds with the Dockerfile.
2. Add a **managed MySQL** database (Railway has a MySQL template/plugin). Reference its
   variables from the app service, mapping them onto `DB_*`. Railway provides its own
   variable names; map them explicitly (e.g. `DB_HOST=${{MySQL.MYSQLHOST}}`) — **[guidance]**.
3. Set the application env vars from §2 (Railway → Variables). Mark the secrets
   (`APP_KEY`, `APP_PASSWORD_PEPPER`, `APP_ENCRYPTION_KEY`, `DB_PASSWORD`, mail/AWS
   credentials) as secret.
4. First deploy: set `RUN_MIGRATIONS=true` once so the entrypoint runs
   `php artisan migrate --force`, then set it back to `false`.
5. Add a **scheduler** and a **worker** (see §5). Railway does not run cron for you by
   default **[guidance]**.

Railway uses the container's `$PORT`, which the entrypoint already binds Apache to.

### Render

A `render.yaml` Blueprint already exists at the repo root. It creates:

- a **web service** built from `backend/Dockerfile` (`healthcheckPath: /up`), and
- a managed database named `hotel-db` (`databaseName: hotel_management`, user `hotel_app`).

> ⚠️ **Caveat to verify.** `render.yaml` sets `DB_CONNECTION=mysql`, but Render's
> first-party managed databases created by a Blueprint are **PostgreSQL**. To use MySQL
> you should either point `DB_*` at an **external managed MySQL** (e.g. AWS RDS for MySQL,
> PlanetScale, or a MySQL add-on) or switch the blueprint to PostgreSQL and change
> `DB_CONNECTION`. This mismatch was not resolved in the repo; confirm the database engine
> you actually provision.

Deploy steps (**[guidance]** for the platform side):

1. Create a new Blueprint on Render pointing at the repository; fill in the `sync: false`
   secrets it lists (`APP_KEY`, `APP_PASSWORD_PEPPER`, `APP_ENCRYPTION_KEY`,
   `MAIL_MAILER`, `MAIL_FROM_ADDRESS`).
2. Provide a real MySQL database and set `DB_*`.
3. Migrations: run `php artisan migrate --force` as a Render **pre-deploy/release
   command**, or set `RUN_MIGRATIONS=true` on the first boot.
4. Add a Render **Cron Job** for the scheduler and a **Background Worker** for the queue
   (§5).

If you prefer the buildpack route, Render can use `backend/Procfile`
(`web: vendor/bin/heroku-php-apache2 public/`), which also serves `public/` as the web root.

### Generic VPS (nginx + PHP-FPM) — [guidance]

This repo does not ship an nginx config; the following is the standard Laravel layout.

- **Document root must be the `public/` directory**, never the project root:

```nginx
server {
    listen 80;
    server_name hotel.example.com;
    root /var/www/hotel-management/backend/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Then, on the server:

```bash
cd /var/www/hotel-management/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
```

Zip/uploading the app directly (rather than a container) means you must also set the
same env vars from §2 in `.env` (or the process environment).

---

## 1a. AWS services used

The application only talks to AWS for **backups** (`BackupService`) and optionally for
mail/queue/cache if you configure those Laravel drivers. The AWS SDK is bundled
(`aws/aws-sdk-php`). The core database should be **Amazon RDS for MySQL/MariaDB**
**[guidance]**; the backup pipeline uploads encrypted dumps to **Amazon S3**.

The S3 client:

- uses region `AWS_DEFAULT_REGION` (default `us-east-1`);
- uses explicit `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` (+ optional
  `AWS_SESSION_TOKEN`) **only if both are set**, otherwise the SDK's default credential
  chain (env vars, shared credentials file or an EC2/ECS **IAM role**).

Prefer an **instance/task IAM role** over long-lived access keys **[guidance]**.

---

## 2. Required environment variables

`backend/.env.example` is a **full project template** (application, database, security and
backup variables); the authoritative names still come from `config/*.php`. Secrets are shown
as placeholders — never commit or print real values.

### Application

| Variable | Example / placeholder | Notes |
| --- | --- | --- |
| `APP_NAME` | `"Azure Bay Hotel"` | Shown in emails/UI. |
| `APP_ENV` | `production` | Must be `production` (see §7). |
| `APP_KEY` | `<base64-app-key>` | Generate with `php artisan key:generate`. |
| `APP_DEBUG` | `false` | Must be `false` in production. |
| `APP_URL` | `https://hotel.example.com` | Use the real `https://` URL. |
| `APP_TIMEZONE` | `UTC` | Read by the backup schedule config. Note `config/app.php` hard-codes the framework timezone to `UTC`. |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | |
| `APP_MAINTENANCE_DRIVER` | `file` | |
| `APP_PREVIOUS_KEYS` | *(empty)* | For `APP_KEY` rotation (Laravel). |

### Security / secrets

| Variable | Default | Notes |
| --- | --- | --- |
| `APP_KEY` | — | Laravel app key (`php artisan key:generate`). |
| `APP_PASSWORD_PEPPER` | *(required)* | Application password pepper, **min 16 chars** (`PasswordService` throws otherwise). Never in the DB. |
| `APP_PASSWORD_PEPPER_PREVIOUS` | *(empty)* | Rotated-out peppers (comma-separated) so old passwords still verify. |
| `APP_ENCRYPTION_KEY` | *(required)* | Base64 32-byte key for AES-256-GCM (guest fields **and** backups). |
| `HASH_DRIVER` | `argon2id` | Default in `config/hashing.php` even if unset. |
| `ARGON_MEMORY` | `65536` | KiB (64 MiB). |
| `ARGON_THREADS` | `1` | |
| `ARGON_TIME` | `4` | Iterations. |
| `OTP_LENGTH` | `6` | Email OTP length. |
| `OTP_EXPIRY_MINUTES` | `5` | |
| `OTP_MAX_ATTEMPTS` | `5` | |
| `OTP_RESEND_COOLDOWN_SECONDS` | `60` | |
| `OTP_MAX_RESENDS_PER_HOUR` | `5` | |
| `LOGIN_MAX_ATTEMPTS` | `5` | Per email+IP per minute. |
| `LOGIN_DECAY_SECONDS` | `60` | |
| `TWO_FACTOR_CHALLENGE_MINUTES` | `10` | How long a password-verified session may sit on the 2FA screen. |
| `TWO_FACTOR_EXPOSE_CODE` | `false` | **Must be `false` in production.** Only honoured when `APP_ENV` is `local`/`testing`. |
| `PASSWORD_CHECK_BREACHED` | `false` | Optional HaveIBeenPwned range check; off for offline/air-gapped. |

> Generate the two required secrets with the real command:
> `php artisan security:generate-secrets` (add `--write` to also write them to `.env`).
> Never commit the output; inject it from your platform's secret store.

### Database

| Variable | Example | Notes |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Use `mysql` (or `mariadb`). |
| `DB_HOST` / `DB_PORT` | `<db-host>` / `3306` | Managed MySQL endpoint. |
| `DB_DATABASE` | `hotel_management` | |
| `DB_USERNAME` / `DB_PASSWORD` | `<db-user>` / `<db-password>` | |
| `DB_URL` | *(optional)* | Alternative single-URL config. |
| `MYSQL_ATTR_SSL_CA` | *(optional)* | Path to a CA bundle for TLS to the DB (`config/database.php`). |
| `DB_QUEUE_*`, `DB_CACHE_*` | *(optional)* | Only if you override the database queue/cache tables. |

### Session, cache, queue

| Variable | Recommended (prod) | Notes |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | Sessions table is migrated. |
| `SESSION_LIFETIME` | `120` | Minutes. |
| `SESSION_ENCRYPT` | `true` | Sessions encrypted at rest. |
| `SESSION_SECURE_COOKIE` | `true` | Cookies only over HTTPS. |
| `SESSION_SAME_SITE` | `lax` | |
| `SESSION_DOMAIN` | `null` | Set to your domain if needed. |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | Use `redis`/`sqs` if you provision them. |

### Mail

| Variable | Notes |
| --- | --- |
| `MAIL_MAILER` | Must **not** be `log` in production (that would not deliver OTP codes). Use `smtp`, `ses`, `postmark`, `resend`, etc. |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_SCHEME` | Provider-specific. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Sender identity. |

The mailer delivers the 2FA codes (`TwoFactorOtpMail`) and backup failure alerts
(`BackupFailedMail`).

### Backups / AWS

| Variable | Default | Notes |
| --- | --- | --- |
| `BACKUP_ENABLED` | `true` | |
| `BACKUP_DRIVER` | `local` | Set `s3` in production. |
| `BACKUP_PATH` | `backups` | Local path under `storage/app`. |
| `BACKUP_RETENTION_DAYS` | `30` | Prune window. |
| `MYSQLDUMP_BINARY` | `mysqldump` | Must exist in the image/host (the Docker image installs `default-mysql-client`). |
| `BACKUP_TIMEOUT_SECONDS` | `900` | |
| `BACKUP_ALERT_EMAIL` | *(empty)* | If empty, no failure email is sent. |
| `AWS_BACKUP_BUCKET` | *(empty)* | Required for `BACKUP_DRIVER=s3`. |
| `AWS_DEFAULT_REGION` | `us-east-1` | Must match the bucket region. |
| `AWS_BACKUP_PREFIX` | `hotel-backups` | Object key prefix. |
| `AWS_BACKUP_SSE` | `AES256` | `AES256` (SSE-S3) or `aws:kms`. |
| `AWS_BACKUP_KMS_KEY_ID` | *(empty)* | Only used when SSE is `aws:kms`. |
| `AWS_BACKUP_STORAGE_CLASS` | `STANDARD_IA` | |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_SESSION_TOKEN` | *(empty)* | Optional; otherwise the default credential chain / IAM role is used. |
| `AWS_BUCKET` | *(empty)* | Separate general-purpose S3 disk (`config/filesystems.php`); **not** the backup bucket. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | Set `true` for S3-compatible endpoints. |

> **Placeholders only.** Never paste real values into docs, tickets or chat — use
> `<your-pepper>`, `<your-encryption-key>`, `<aws-access-key-id>`, etc.

---

## 2a. S3 IAM policy for backups (least privilege)

`BackupService` performs these S3 API calls:

| Operation | SDK call | Why |
| --- | --- | --- |
| `s3:PutObject` | `putObject` | Upload the encrypted archive. |
| `s3:GetObject` | `headObject` (verify size) and `getObject` (re-verify/download) | `HeadObject`/`GetObject` both require `s3:GetObject`. |
| `s3:DeleteObject` | `deleteObject` | Retention/prune. |

The code comments and `config/backup.php` also mention `s3:ListBucket`, but **the code
does not call `ListObjects`**, so `ListBucket` is not strictly required by the current
implementation. It is included below, commented out, if you prefer to grant it.

**[guidance]** Replace `<bucket-name>` (and the prefix) with your real values.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "BackupObjects",
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::<bucket-name>/<backup-prefix>/*"
    }
  ]
}
```

If you need the `ListObjects` capability (it is **not** required by the current code):

```json
{
  "Effect": "Allow",
  "Action": "s3:ListBucket",
  "Resource": "arn:aws:s3:::<bucket-name>",
  "Condition": { "StringLike": { "s3:prefix": ["<backup-prefix>/*"] } }
}
```

### KMS (only when `AWS_BACKUP_SSE=aws:kms`)

The SDK sends `SSEKMSKeyId` when SSE is `aws:kms` and `AWS_BACKUP_KMS_KEY_ID` is set.
Using SSE-KMS then requires the standard KMS data-key permissions **[guidance]**:

```json
{
  "Sid": "KmsForBackups",
  "Effect": "Allow",
  "Action": ["kms:GenerateDataKey", "kms:Decrypt"],
  "Resource": "<kms-key-arn>"
}
```

With SSE-S3 (`AES256`, the default) no KMS permissions are needed — S3 manages the key.
Attach the policy to a dedicated IAM user or, preferably, the instance/container/task
**role** the app runs as. Never embed static keys in the image.

---

## 3. S3 bucket hardening — [guidance]

These are standard S3 controls; the repo's config sets the object-level SSE and storage
class but the **bucket** itself is configured in AWS:

1. **Block Public Access** — enable all four "Block Public Access" settings (ACLs,
   policy, ignore-public-ACLs, restrict-public-buckets). Backups must never be public.
   `config/backup.php` comments reinforce this ("never rely on bucket-level public access").
2. **Default encryption** — set SSE-S3 or SSE-KMS as the bucket default so uploads are
   always encrypted. The app also requests `ServerSideEncryption` explicitly
   (`AWS_BACKUP_SSE`, default `AES256`).
3. **Versioning** — enable bucket versioning. (`config/backup.php` declares
   `AWS_BACKUP_VERSIONING=true`, but the **service does not turn versioning on** —
   it is a bucket setting.)
4. **Lifecycle / retention** — add a lifecycle rule matching your
   `BACKUP_RETENTION_DAYS` (default 30) to expire/transition old objects, and clean up
   non-current versions if versioning is on. The app also prunes via
   `BackupService::prune()`; you have belt-and-braces.
5. **Enforce TLS** — a bucket policy denying `aws:SecureTransport = false`.
6. **Access logging / CloudTrail data events** — optional but recommended for auditing.

---

## 4. Migrations, scheduler, queues, document root

### Migrations

```bash
php artisan migrate --force
```

In the Docker image this can be automated: `RUN_MIGRATIONS=true` makes
`docker/entrypoint.sh` run `php artisan migrate --force` on boot. Use it for the first
deploy (or a release step), then turn it off. Do **not** run `php artisan migrate:fresh`
or `db:seed` in production — the seeders create demo accounts with a known password.

### Web server document root

The document root **must** be `backend/public/`. Both the Dockerfile and the Procfile
already do this (`DocumentRoot /var/www/html/public`,
`heroku-php-apache2 public/`). If you deploy the code directly on a VPS, point nginx at
`.../backend/public` (see §0/§1) and never at the project root.

The health endpoint is `/up` (Laravel's built-in health route; also used by the Railway/
Render health checks).

### Scheduler and cron

As documented in [`BACKUP_RESTORE.md`](BACKUP_RESTORE.md), the repository **does** wire the
schedule: `routes/console.php` registers `backup:run` (daily 02:30) and `backup:prune`
(weekly) and attaches them to `Schedule` (see `php artisan schedule:list`). What is *not*
in the repo is an OS-level cron entry, so on a server you still add the classic cron line
**[guidance]**:

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

For a container host without cron, run a small always-on process instead **[guidance]**:

```bash
php artisan schedule:work
```

### Queues

The default queue connection is `database` (`QUEUE_CONNECTION=database`), with a `jobs`
table created by migration. Run a worker **[guidance]**:

```bash
php artisan queue:work --sleep=3 --tries=3
```

On Render use a Background Worker; on a VPS use Supervisor/systemd; on Railway run a
second service with that start command.

### Production caches (what the entrypoint already does)

The entrypoint runs these unless `CACHE_APP_CONFIG=false`:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After changing env vars on a long-lived host, clear and rebuild:

```bash
php artisan optimize:clear
```

---

## 5. Production hardening checklist

Derived from `config/security.php`, `config/hashing.php`, `config/session.php`,
`config/app.php`, `AppServiceProvider` and `render.yaml`:

- [ ] `APP_ENV=production` and `APP_DEBUG=false` (no stack traces to users).
- [ ] `APP_KEY` set to a freshly generated key (`php artisan key:generate`); store it as
      a secret.
- [ ] `APP_PASSWORD_PEPPER` set to a real secret **≥ 16 characters** — the code throws at
      runtime if it is missing or too short (`PasswordService::currentPepper`).
- [ ] `APP_ENCRYPTION_KEY` set to a valid base64 32-byte key (else field/backup
      encryption fails loudly).
- [ ] `TWO_FACTOR_EXPOSE_CODE=false` (only honoured in `local`/`testing` anyway, but keep
      it off).
- [ ] `MAIL_MAILER` set to a **real** mailer (not `log`) so 2FA codes reach users.
- [ ] `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true`; `APP_URL` is `https://…`.
- [ ] `DB_*` point at a managed MySQL with a strong password (never the demo
      `hotel_app`/known creds).
- [ ] **Do not run the seeders in production** — `UserSeeder` creates known demo accounts
      with a shared, printed password (`SEED_DEMO_PASSWORD`, default `Password!2345`).
      Change/remove any demo accounts.
- [ ] `HASH_DRIVER=argon2id` (the default) and confirm the host actually has Argon2id
      support in PHP (`PASSWORD_ARGON2ID`).
- [ ] Backups: `BACKUP_DRIVER=s3`, `AWS_BACKUP_BUCKET` set, `BACKUP_ALERT_EMAIL` set so
      failures are noticed, and a working `MYSQLDUMP_BINARY` on the host.
- [ ] S3 bucket: Block Public Access on, default encryption on, versioning on, lifecycle
      aligned with `BACKUP_RETENTION_DAYS`.
- [ ] IAM least privilege: only `PutObject`/`GetObject`/`DeleteObject` on the backup
      prefix (plus KMS only if using SSE-KMS).
- [ ] Secrets injected from a secret store; nothing sensitive committed to `.env` in the
      image or repo. `.env` is git-ignored.
- [ ] Scheduler and queue workers running (§4).
- [ ] Consider `PASSWORD_CHECK_BREACHED=true` in a connected production environment
      (network-dependent; off by default).

---

## 6. Things NOT verified / caveats

- `render.yaml` no longer wires a Render-managed database: this app targets
  **MySQL/MariaDB** and Render provisions managed **PostgreSQL**, so the blueprint now
  expects an external MySQL supplied through the `DB_*` env vars (`sync: false`). Confirm
  the engine/provider before relying on the blueprint.
- The Dockerfile, entrypoint, Procfile, `railway.json` and `render.yaml` are **scaffolding
  that was written but not executed** in this repo (the Dockerfile header says so
  explicitly). Treat platform steps above as guidance.
- The backup schedule **is** wired in `routes/console.php` (`backup:run` daily,
  `backup:prune` weekly); only the OS-level cron/platform trigger is left to the operator.
  See `BACKUP_RESTORE.md` §4.
- `backend/.env.example` **is** a full project template (it lists the security/backup
  variables); the variable list here is derived from `config/*.php` and that template.
- IAM/KMS policy JSON and S3 lifecycle settings are standard AWS guidance, not the
  literal contents of any file in this repo.
