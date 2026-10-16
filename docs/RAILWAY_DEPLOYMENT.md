# Deploying to Railway (step-by-step)

A concrete walkthrough for putting the **Secure Hotel Management System** Laravel
backend (`backend/`) online on [Railway](https://railway.app), with a managed MySQL
database, a real HTTPS URL, and the application switched out of development
("testing") mode.

> **Why not Vercel?** Vercel only runs static sites and JavaScript/Node serverless
> functions. This project is a PHP 8.4 / Laravel application that needs a persistent
> **MySQL** connection, database-backed sessions/cache/queue and a scheduler — none of
> which Vercel provides. Railway runs the project's own `Dockerfile`, so it is the right
> fit. (See `AWS_DEPLOYMENT.md` §0 for the same reasoning.)

> ⚠️ **Read this first.** The Docker image, entrypoint and `railway.json` in this repo
> were **written and reviewed but never built on the development machine** (Docker is not
> installed there). Everything below is the standard Railway flow, but the **first
> Railway build is the first real test of the Dockerfile**. §9 lists the most likely
> failure points and how to read them.

The whole flow has three parts:

1. **Get the code onto GitHub** (Railway deploys from a Git repository).
2. **Create the Railway project**, add MySQL, set the variables, deploy.
3. **Switch off testing mode** (production secrets, real mailer, HTTPS, demo password).

---

## 0. What you need

| Thing | Why | Cost |
| --- | --- | --- |
| A **GitHub account** | Railway builds from a repo | free |
| A **Railway account** | hosting | Railway's trial/Starter credit; MySQL + web service both consume it |
| A **mail provider** (Gmail app password, Brevo, Mailgun, SES…) | to actually email the 2FA codes | free tiers exist |
| ~10 minutes | | |

You do **not** need Docker installed locally — Railway builds the image in the cloud.

---

## 1. Put the code on GitHub

The project folder is not a Git repository yet, so create one and push it.

### 1a. Initialise and commit (run from the project root)

```powershell
cd "C:\Users\CHARMIES\Desktop\project\hotel-management"

# The portable toolchain is not on PATH, so call git with its full path
& "C:\Users\CHARMIES\tools\git\cmd\git.exe" init
& "C:\Users\CHARMIES\tools\git\cmd\git.exe" add -A
& "C:\Users\CHARMIES\tools\git\cmd\git.exe" commit -m "Secure Hotel Management System: Laravel backend, Flutter app, docs"
```

> Check what is about to be committed **before** you commit: `git status --short`.
> `.env`, `backend/vendor/`, `mobile/build/` and `data/` are already excluded by
> `.gitignore` — **never** commit `backend/.env`, it holds your local secrets.

### 1b. Create the GitHub repo and push

Create an **empty** repository on GitHub (no README, no .gitignore — the project already
has one). Then, using the URL GitHub shows you:

```powershell
& "C:\Users\CHARMIES\tools\git\cmd\git.exe" branch -M main
& "C:\Users\CHARMIES\tools\git\cmd\git.exe" remote add origin https://github.com/<your-username>/hotel-management.git
& "C:\Users\CHARMIES\tools\git\cmd\git.exe" push -u origin main
```

GitHub will ask for credentials. Use a **Personal Access Token** as the password
(GitHub → Settings → Developer settings → Personal access tokens → Fine-grained, give it
`Contents: Read and write` on the new repo).

---

## 2. Create the Railway project

1. Sign in at <https://railway.app> and choose **New Project**.
2. Choose **Deploy from GitHub repo**, authorise Railway, and pick
   `hotel-management`.
3. Railway creates a service. Open it and go to **Settings**:
   - **Root Directory** → set to `backend`
     *(critical — `railway.json`, `Dockerfile` and `composer.json` all live in `backend/`)*.
   - **Build** → Railway should detect `railway.json` and use the **Dockerfile** builder.
   - Leave the start command as-is; `railway.json` sets `apache2-foreground` and the
     container's entrypoint binds Apache to Railway's `$PORT`.
4. Do **not** deploy yet — the app needs its variables first.

### Add MySQL

1. In the project, click **New → Database → Add MySQL**.
2. Railway provisions a MySQL service and exposes variables such as `MYSQLHOST`,
   `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`.
3. Note the MySQL service's **name** (e.g. `MySQL`). You will reference it as
   `${{MySQL.MYSQLHOST}}` in step 3. If your service is named differently, adjust every
   reference accordingly.

---

## 3. Set the application variables

Open your **web service → Variables → Raw Editor** and paste the block below. Replace the
three `<...>` secrets first (see step 4 on how to generate them).

```dotenv
APP_NAME="Azure Bay Hotel"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://REPLACE-WITH-YOUR-DOMAIN.up.railway.app

# --- secrets: generate with `php artisan security:generate-secrets` (step 4) ---
APP_KEY=<base64-app-key>
APP_PASSWORD_PEPPER=<64-hex-char-pepper>
APP_ENCRYPTION_KEY=<base64-32-byte-key>

HASH_DRIVER=argon2id
ARGON_MEMORY=65536
ARGON_THREADS=1
ARGON_TIME=4
ARGON_VERIFY=true

# --- database: reference the MySQL service so values stay in sync ---
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

# --- sessions / cache / queue (database-backed; survives redeploys) ---
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

# --- mail: MUST be a real mailer, or 2FA codes never arrive ---
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=benjaminaneefi316@gmail.com
MAIL_PASSWORD=<gmail-app-password>
MAIL_FROM_ADDRESS="benjaminaneefi316@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"

# --- 2FA must not be exposed in production ---
TWO_FACTOR_EXPOSE_CODE=false

# --- backups (start with `local`, move to `s3` later) ---
BACKUP_ENABLED=true
BACKUP_DRIVER=local
BACKUP_RETENTION_DAYS=30
BACKUP_ALERT_EMAIL=benjaminaneefi316@gmail.com

# --- container boot behaviour ---
RUN_MIGRATIONS=true
RUN_SEEDERS=true
CACHE_APP_CONFIG=true
```

Then set `APP_URL` to the real domain:

1. **Settings → Networking → Public Networking → Generate Domain**.
2. Copy the generated `https://….up.railway.app` URL.
3. Paste it back into `APP_URL` (Railway redeploys automatically on variable change).

> `SESSION_SECURE_COOKIE=true` requires HTTPS — the generated Railway domain provides it.
> If you later attach a custom domain, update `APP_URL` to match.

> **Backups note.** `BACKUP_DRIVER=local` simulates backups on the container's disk (no
> AWS needed) and is enough to demonstrate the feature. For durable backups set
> `BACKUP_DRIVER=s3` plus `AWS_*` variables — see `BACKUP_RESTORE.md` and
> `AWS_DEPLOYMENT.md` §2a.

---

## 4. Generate the three secrets

Run this **locally** (with your portable PHP) and copy the output into the Railway
variables. Never commit these.

```powershell
cd "C:\Users\CHARMIES\Desktop\project\hotel-management\backend"
& "C:\Users\CHARMIES\tools\php84\php.exe" artisan security:generate-secrets
& "C:\Users\CHARMIES\tools\php84\php.exe" artisan key:generate --show
```

- `security:generate-secrets` prints **APP_PASSWORD_PEPPER** and **APP_ENCRYPTION_KEY**.
- `key:generate --show` prints the **APP_KEY** (`base64:…`).

Keep these values safe: `APP_ENCRYPTION_KEY` decrypts every encrypted guest field and
every backup archive, and `APP_PASSWORD_PEPPER` is required to verify passwords. Losing
them means losing access to that data.

---

## 5. Deploy and watch the first build

Railway deploys automatically once the variables are set (or click **Deploy**).

1. Open **Deployments → View Logs**.
2. You should see the Docker build, then the entrypoint output:
   `Running migrations...` → `Seeding demo data...` → config/route/view caching.
3. When the log settles, open `https://<your-domain>/up` — Artisan's health route returns
   an empty **200 OK** page. `https://<your-domain>/` should render the public hotel site.

### Verify the deploy

| Check | URL | Expected |
| --- | --- | --- |
| Liveness | `/up` | `200` (blank page) |
| API health | `/api/health` | `{"status":"ok",…}` |
| Public site | `/` | hotel home page with images |
| Login | `/login` | the sign-in form |

---

## 6. Take it out of "testing" mode

The development conveniences are all switched off by the variables above. This is the
checklist that proves it:

- [x] `APP_ENV=production` — no development error pages.
- [x] `APP_DEBUG=false` — stack traces and env values are never shown to users.
- [x] `TWO_FACTOR_EXPOSE_CODE=false` — the OTP is **emailed**, not printed on screen.
- [x] `MAIL_MAILER=smtp` (not `log`) — codes reach a real inbox.
- [x] `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` — encrypted, HTTPS-only cookies.
- [x] `APP_URL` is the real `https://…` URL.
- [x] Real `APP_KEY`, `APP_PASSWORD_PEPPER`, `APP_ENCRYPTION_KEY` — not the local ones.

**The one thing the deploy cannot do for you:** `RUN_SEEDERS=true` seeds the demo
dataset, which includes the super-admin account with the project's demo password
(`Password!2345`). **Sign in and change that password immediately:**

1. Log in at `/login` as `benjaminaneefi316@gmail.com`.
2. Read the 2FA code from the email (this confirms your mailer works).
3. Go to **Profile → Change password** and set a strong, unique password.

For a real launch you would also remove/rename the other demo accounts
(`UserSeeder`) and the 60 fictional guests (`GuestSeeder`).

To stop seeding on future boots, set `RUN_SEEDERS=false` once the database is
populated (the `demo:seed` command is guarded and would skip anyway, but turning it off is
cleaner).

---

## 7. Point the mobile app at the hosted API

The Flutter app takes the API base URL at build time:

```powershell
cd "C:\Users\CHARMIES\Desktop\project\hotel-management\mobile"
& "C:\Users\CHARMIES\tools\flutter\bin\flutter.bat" run ^
  --dart-define=API_BASE_URL=https://<your-domain>/api
```

- **Physical phone on the same Wi-Fi** as the hosted site: just use the public
  `https://<your-domain>/api` — no special networking needed.
- **Android emulator:** `http://10.0.2.2:8000/api` while running `php artisan serve` locally.
- **Local network (no hosting):** `php artisan serve --host=0.0.0.0 --port=8000` then
  `--dart-define=API_BASE_URL=http://<your-PC-LAN-IP>:8000/api`.

See `docs/API.md` for the endpoint contract.

---

## 8. Scheduler and backups on Railway

The app registers its schedule in `routes/console.php`: `backup:run` (daily 02:30),
`backup:prune` (weekly), `otp:prune` (hourly). A container host does not run cron for
you, so create a **second service** from the same repo (same Root Directory `backend`):

1. **New → GitHub Repo** → same repository.
2. **Settings → Root Directory** = `backend`.
3. **Settings → Deploy → Custom Start Command** =
   `php artisan schedule:work` (a long-running process that fires the schedule).
4. Set `RUN_MIGRATIONS=false` and `CACHE_APP_CONFIG=false` on this service (the web
   service already migrates), and copy the same `DB_*` / secret variables.

Alternatively use a Railway **Cron Job** service with the schedule `* * * * *` and the
command `php artisan schedule:run`.

Backups write to the container's local disk when `BACKUP_DRIVER=local` — that disk is
**ephemeral**, so a real deployment should use `BACKUP_DRIVER=s3` (or a Railway volume
mounted at `backend/storage/app`). See `BACKUP_RESTORE.md`.

---

## 9. Troubleshooting the first build

| Symptom in the logs | Likely cause | Fix |
| --- | --- | --- |
| `Dockerfile not found` / build uses Nixpacks | Root Directory not set to `backend` | Set **Root Directory = `backend`** in service settings |
| `Unable to prepare route … Uses Closure` | A route closure was added that cannot be cached | Routes must use controller actions (the shipped `/api/health` already does) |
| `SQLSTATE[HY000] [2002] Connection refused` | `DB_*` not mapped, or MySQL not ready | Confirm `${{MySQL.MYSQLHOST}}` references and that the MySQL service is running |
| `Class "…" not found` / missing extension | PHP extension not in the image | All needed extensions are installed in `backend/Dockerfile` (`pdo_mysql`, `sodium`, `zip`, `opcache`, `password`+Argon2) |
| 2FA email never arrives | `MAIL_MAILER=log` or wrong SMTP creds | Use a real mailer; check the deploy logs for the mail error |
| `APP_PASSWORD_PEPPER` too short error | Pepper missing/&lt;16 chars | Re-run `security:generate-secrets` and paste the value |
| 500 on first load | `APP_KEY` missing | Set `APP_KEY` from `php artisan key:generate --show` |
| Login loop on the site | `SESSION_SECURE_COOKIE=true` over plain HTTP | Use the HTTPS Railway domain, or set the cookie flag to `false` temporarily |

After changing variables, Railway redeploys. You can also open a shell on the running
service (Railway → service → ⋯ → **Shell**) and run
`php artisan optimize:clear` if cached config ever looks stale.

---

## 10. Honest status of this deployment path

- The code, Dockerfile, entrypoint, `railway.json` and env templates are complete and
  consistent, and the production caches (`config:cache`, `route:cache`, `view:cache`) were
  **verified locally** to succeed against this codebase.
- The **container build itself has not been executed** on the development machine (no
  Docker installed), and no Railway account was used to perform the steps above — so treat
  §5–§9 as the standard flow to follow and verify on your first deploy.
- The 50-test backend suite (`php artisan test`) passes; local backups were verified
  end-to-end against MariaDB with `BACKUP_DRIVER=local`.
