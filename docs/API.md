# Hotel Management REST API

The REST API consumed by the Flutter mobile application. It is declared in
`backend/routes/api.php` and implemented by the controllers in
`backend/app/Http/Controllers/Api/`.

## Overview

- **Base URL:** the routes are all prefixed with `/api` (for example
  `http://10.0.2.2:8000/api`). The mobile client compiles this in with
  `--dart-define=API_BASE_URL=...`; the default is
  `http://10.0.2.2:8000/api` (`10.0.2.2` is how the Android emulator reaches the
  host machine's `127.0.0.1`).
- **JSON only.** Every endpoint returns JSON. The client always sends
  `Accept: application/json`, and sends `Content-Type: application/json` on
  requests with a body.
- **Authentication:** bearer tokens issued by Laravel Sanctum. Send
  `Authorization: Bearer <token>` on authenticated requests.
- **Errors:** error bodies carry a `message` and, for many failures, a
  machine-readable `reason` (see [Error responses](#error-responses)).

## Authentication

Authentication is a two-step flow. The user is **not** authenticated after
step 1 — step 1 only proves the password. A short-lived, single-purpose
"challenge" token stands in for the pending-2FA session.

### Step 1 — `POST /auth/login`

Verifies the email/username and password.

Request body:

| Field | Rules |
| --- | --- |
| `email` | required, string, max 190 (matched against `email` **or** `username`) |
| `password` | required, string |

There are three possible outcomes:

1. **2FA disabled** — the account is signed in immediately:

   ```json
   {
     "status": "authenticated",
     "token": "<30-day bearer token>",
     "user": { }
   }
   ```

2. **2FA required** — a challenge token is returned; the user must complete
   step 2:

   ```json
   {
     "status": "two_factor_required",
     "challenge_token": "<short-lived token>",
     "expires_in": 600,
     "email": "guest@example.com"
   }
   ```

   The challenge token is created with the `2fa:challenge` ability and expires
   after 10 minutes (`expires_in` is 600 seconds). `email` is the account's
   e-mail address.

3. **Failure** — see [Error responses](#error-responses). Bad credentials
   return `401` with reason `invalid_credentials`; an account that is not active
   returns `403` with reason `account_inactive`. If the OTP cannot be issued
   (resend cooldown or hourly ceiling) the response is `429` with reason
   `cooldown` or `hourly_limit`.

### Step 2 — `POST /auth/two-factor`

Exchanges the emailed one-time code for a bearer token.

Request body:

| Field | Rules |
| --- | --- |
| `challenge_token` | required, string (the value from step 1) |
| `code` | required, string, max 12 |

Success (`200`):

```json
{
  "status": "authenticated",
  "token": "<30-day bearer token>",
  "user": { }
}
```

The final bearer token is created with the `*` ability and expires after 30
days. On success the challenge token is deleted (single use).

If the challenge token is missing/expired/revoked, or its user is no longer
active, the response is `401` with reason `challenge_expired`. If the code is
wrong, expired, used, or attempt-capped, the response is `422` with a `reason`
of `invalid`, `expired`, `no_code` or `too_many_attempts`.

### Resend — `POST /auth/resend`

Asks the server to email a fresh code for an existing challenge.

Request body:

| Field | Rules |
| --- | --- |
| `challenge_token` | required, string |

Success:

```json
{ "status": "sent" }
```

An expired/unknown challenge returns `401` `challenge_expired`. If the account
has 2FA disabled, it returns `400` with reason `2fa_disabled`. A resend that is
too soon or over the hourly ceiling returns `429` with reason `cooldown` or
`hourly_limit`.

### Development-only field: `dev_code`

When the application environment is `local` or `testing` **and**
`TWO_FACTOR_EXPOSE_CODE=true`, the issued OTP is added to the responses of
`POST /auth/login` and `POST /auth/resend` under the key `dev_code`, purely so
the flow can be demonstrated without a real mailbox. It is not returned by
`POST /auth/two-factor`.

> **Never set `TWO_FACTOR_EXPOSE_CODE=true` in production.** It is a local/demo
> convenience only. The code is otherwise never stored in plaintext and never
> logged.

## Endpoints

All authenticated endpoints pass through the `auth:sanctum` and `active`
middleware. Additional `can:` middleware restricts some routes by role.

| Method | Path | Auth | Additional ability | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/health` | public | — | Liveness probe |
| GET | `/room-types` | public | — | Active room-type catalogue |
| GET | `/availability` | public | — | Rooms free for a date window |
| POST | `/auth/login` | public | — | Step 1: verify password |
| POST | `/auth/two-factor` | public | — | Step 2: exchange OTP for a token |
| POST | `/auth/resend` | public | — | Resend a fresh OTP |
| GET | `/auth/me` | bearer | — | Current user profile |
| POST | `/auth/logout` | bearer | — | Revoke the current token |
| PUT | `/auth/password` | bearer | — | Change own password |
| GET | `/bookings` | bearer | — | List bookings (paginated) |
| POST | `/bookings` | bearer | — (guests need `book-own-room`) | Create a booking |
| GET | `/bookings/{booking}` | bearer | — | Show one booking |
| POST | `/bookings/{booking}/cancel` | bearer | — | Cancel a booking |
| POST | `/bookings/{booking}/check-in` | bearer | `check-in-out` | Check a guest in |
| POST | `/stays/{stay}/check-out` | bearer | `check-in-out` | Check a guest out |
| GET | `/dashboard/summary` | bearer | `access-staff-area` | Staff KPI summary |
| GET | `/rooms` | bearer | `access-staff-area` | Full room list |
| POST | `/payments` | bearer | `access-staff-area`, `manage-payments` | Record a payment |

Roles per ability (from `config/abilities.php`): `check-in-out`,
`access-staff-area` and `manage-payments` are held by `admin` and
`receptionist`; `book-own-room` is held by `guest`. An administrator passes any
ability.

### `GET /health`

Public. No parameters.

```json
{
  "status": "ok",
  "app": "Hotel Management",
  "time": "2026-10-15T09:00:00+00:00"
}
```

`app` is `config('app.name')`; `time` is the current time in ISO-8601.

### `GET /room-types`

Public. Returns every **active** room type, ordered by `base_price` ascending.

```json
{
  "data": [
    {
      "id": 1,
      "code": "DLX",
      "name": "Deluxe Room",
      "slug": "deluxe-room",
      "description": "Spacious room with a city view.",
      "bed_type": "king",
      "capacity": 2,
      "base_price": 180,
      "amenities": ["Wi-Fi", "Air conditioning"],
      "image_url": "/storage/room-types/deluxe.jpg"
    }
  ]
}
```

All values above are illustrative; the field names are the contract.

### `GET /availability`

Public. Query parameters:

| Field | Rules |
| --- | --- |
| `check_in_date` | required, date, on/after today |
| `check_out_date` | required, date, after `check_in_date` |
| `room_type_id` | nullable, integer, must exist in `room_types` |

Returns an array of the same room payload used by `GET /rooms`:

```json
{
  "data": [
    {
      "id": 7,
      "room_number": "305",
      "floor": 3,
      "status": "available",
      "status_label": "Available",
      "effective_rate": 180,
      "room_type": {
        "id": 1,
        "name": "Deluxe Room",
        "capacity": 2,
        "bed_type": "king",
        "image_url": "/storage/room-types/deluxe.jpg"
      }
    }
  ]
}
```

`room_type` is `null` if the room has no type. A business-rule problem (for
example `check_out_date` not after `check_in_date` after date parsing) returns
`422` with a `message` but no `reason`.

### `GET /auth/me`

Authenticated. No parameters.

```json
{
  "user": {
    "id": 5,
    "name": "Ada Lovelace",
    "username": "ada",
    "email": "ada@example.com",
    "phone": "+1 555 0100",
    "status": "active",
    "two_factor_enabled": true,
    "roles": ["receptionist"],
    "is_staff": true,
    "guest_profile_id": null,
    "last_login_at": "2026-10-14T21:03:11+00:00"
  }
}
```

`status` is a `UserStatus` value (`active`, `inactive` or `archived`);
`guest_profile_id` and `last_login_at` may be `null`.

### `POST /auth/logout`

Authenticated. No parameters. Deletes the token used for the request.

```json
{ "status": "signed_out" }
```

### `PUT /auth/password`

Authenticated. Request body:

| Field | Rules |
| --- | --- |
| `current_password` | required, string |
| `password` | required, string, confirmed (`password_confirmation`), default password policy |

The default policy is at least 12 characters with letters, mixed case, numbers
and symbols (breached-password checking is opt-in via `PASSWORD_CHECK_BREACHED`).
On success every **other** token for the account is revoked.

```json
{ "status": "password_changed" }
```

A wrong current password returns `422` with reason `invalid_password`.

### `GET /bookings`

Authenticated. Staff (admin/receptionist) see every booking; a guest sees only
their own.

Query parameters:

| Field | Rules |
| --- | --- |
| `status` | optional; filters on the booking status |
| `per_page` | optional; clamped to between 1 and 50 (default 15) |

This endpoint is **paginated** (see [Pagination](#pagination)). Response shape:

```json
{
  "data": [ { "id": 42, "reference": "BK-2026-00042", "status": "confirmed", "...": "..." } ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "last_page": 3,
    "total": 33
  }
}
```

Each element of `data` is a booking payload (see
[`GET /bookings/{booking}`](#get-bookingsbooking)).

### `POST /bookings`

Authenticated. The accepted body depends on the caller.

**Staff** (admin or receptionist):

| Field | Rules |
| --- | --- |
| `guest_id` | required, integer, exists in `guests` |
| `check_in_date` | required, date |
| `check_out_date` | required, date, after `check_in_date` |
| `room_ids` | required, array, min 1 |
| `room_ids.*` | integer, exists in `rooms` |
| `adults` | nullable, integer, 1–20 |
| `children` | nullable, integer, 0–20 |
| `special_requests` | nullable, string, max 2000 |
| `status` | nullable; `pending` or `confirmed` only |

**Guest** (requires the `book-own-room` ability — a guest role). The server
assigns the first free room of the requested type; the booking is created as
`pending`.

| Field | Rules |
| --- | --- |
| `room_type_id` | required, integer, exists in `room_types` |
| `check_in_date` | required, date, on/after today |
| `check_out_date` | required, date, after `check_in_date` |
| `adults` | nullable, integer, 1–10 |
| `children` | nullable, integer, 0–10 |
| `special_requests` | nullable, string, max 1000 |

Response `201`:

```json
{ "data": { "id": 42, "reference": "BK-2026-00042" } }
```

(`data` is the full booking payload.) Errors: `422` `no_guest_profile` when a
guest account has no linked guest profile; `422` (no `reason`) when availability
cannot be evaluated; `409` `no_availability` when no room is free; `409`
`booking_conflict` when the booking service rejects the reservation.

### `GET /bookings/{booking}`

Authenticated. `{booking}` is the booking id. A guest may only view their own
booking, otherwise the request is rejected with `403` `forbidden`.

The response uses the full booking payload. This is the canonical example
(values are illustrative):

```json
{
  "data": {
    "id": 42,
    "reference": "BK-2026-00042",
    "status": "confirmed",
    "status_label": "Confirmed",
    "check_in_date": "2026-10-20",
    "check_out_date": "2026-10-23",
    "nights": 3,
    "adults": 2,
    "children": 0,
    "total_amount": 540,
    "paid_amount": 200,
    "balance": 340,
    "special_requests": null,
    "can_cancel": true,
    "can_check_in": false,
    "guest": {
      "id": 11,
      "first_name": "Ada",
      "last_name": "Lovelace",
      "full_name": "Ada Lovelace",
      "email": "ada@example.com",
      "phone": "+1 555 0100",
      "nationality": "GB",
      "city": "London",
      "country": "United Kingdom"
    },
    "rooms": [
      {
        "id": 7,
        "room_number": "305",
        "room_type": "Deluxe Room",
        "rate": 180
      }
    ],
    "payments": [
      {
        "id": 91,
        "booking_id": 42,
        "amount": 200,
        "currency": "USD",
        "method": "card",
        "method_label": "Card (tokenised)",
        "status": "paid",
        "status_label": "Paid",
        "provider": null,
        "provider_reference": null,
        "paid_at": "2026-10-01T09:20:00+00:00",
        "created_at": "2026-10-01T09:20:00+00:00"
      }
    ],
    "created_at": "2026-10-01T09:12:00+00:00"
  }
}
```

`status` is a `BookingStatus` value (`pending`, `confirmed`, `checked_in`,
`checked_out`, `cancelled`, `no_show`). `guest` may be `null`. `paid_amount`
and `balance` are computed from the booking's payments. The payload does not
include the booking's stays.

### `POST /bookings/{booking}/cancel`

Authenticated. A guest may only cancel their own booking (`403` `forbidden`
otherwise). Optional body:

| Field | Rules |
| --- | --- |
| `reason` | optional, string |

Response:

```json
{ "status": "cancelled", "reference": "BK-2026-00042" }
```

Returns `409` `not_cancellable` when the booking is past the point of
cancellation, or `409` `cancel_failed` when the booking service rejects it.

### `POST /bookings/{booking}/check-in`

Requires the `check-in-out` ability (admin/receptionist). Optional body:

| Field | Rules |
| --- | --- |
| `room_id` | optional, integer (read via `$request->integer('room_id')`) |

Response `201` with the stay payload:

```json
{
  "data": {
    "id": 88,
    "booking_id": 42,
    "room_id": 7,
    "room_number": "305",
    "status": "active",
    "status_label": "In house",
    "checked_in_at": "2026-10-20T14:05:00+00:00",
    "checked_out_at": null,
    "actual_nights": null
  }
}
```

A business-rule failure returns `409` with reason `check_in_failed`.

### `POST /stays/{stay}/check-out`

Requires the `check-in-out` ability. `{stay}` is the stay (check-in) id. No body.

Response `200` with the same stay payload shape as check-in (now with
`status`/`checked_out_at`/`actual_nights` populated). A business-rule failure
returns `409` with reason `check_out_failed`.

### `GET /dashboard/summary`

Requires the `access-staff-area` ability (admin/receptionist). No parameters.

```json
{
  "date": "2026-10-15",
  "kpi": {
    "arrivals_today": 4,
    "departures_today": 2,
    "in_house": 18,
    "occupancy_percent": 62.5,
    "revenue_month": 18450.0,
    "pending_bookings": 3
  },
  "rooms": [
    { "status": "available", "label": "Available", "count": 20 },
    { "status": "occupied", "label": "Occupied", "count": 18 }
  ]
}
```

- `arrivals_today`: bookings checking in today with status `pending` or `confirmed`.
- `departures_today`: bookings checking out today with status `checked_in`.
- `in_house`: bookings with status `checked_in`.
- `occupancy_percent`: occupied rooms today / active rooms × 100, one decimal.
- `revenue_month`: sum of `paid` payments since the start of the month.
- `pending_bookings`: bookings with status `pending`.
- `rooms`: one entry per room status, with `status`, `label` and `count`.

### `GET /rooms`

Requires the `access-staff-area` ability. Query parameters:

| Field | Rules |
| --- | --- |
| `status` | optional; filters on room status |
| `room_type_id` | optional; filters on room type |

Not paginated — returns all matching rooms, ordered by `room_number`, using the
same room payload shown for `GET /availability`:

```json
{ "data": [ { "id": 7, "room_number": "305", "status": "occupied", "status_label": "Occupied", "effective_rate": 180, "room_type": { } } ] }
```

Room `status` is a `RoomStatus` value: `available`, `occupied`, `cleaning`,
`maintenance`, `out_of_service`.

### `POST /payments`

Requires `access-staff-area` **and** `manage-payments` (admin/receptionist). No
card data is ever accepted or stored — only a method, an amount and an optional
tokenised provider reference.

Request body:

| Field | Rules |
| --- | --- |
| `booking_id` | required, integer, exists in `bookings` |
| `amount` | required, numeric, between 0.01 and 1000000 |
| `currency` | nullable, string, exactly 3 characters (defaults to `USD`) |
| `method` | required; one of the `PaymentMethod` values |
| `status` | nullable; `paid` or `pending` (defaults to `paid`) |
| `provider` | nullable, string, max 60 |
| `provider_reference` | nullable, string, max 120 |
| `notes` | nullable, string, max 1000 |

`method` is one of `cash`, `card`, `bank_transfer`, `mobile_money`. A `paid`
payment gets `paid_at` set to now; a `pending` payment does not. The `notes`
field is stored but is **not** returned in the payment payload.

Response `201`:

```json
{
  "data": {
    "id": 91,
    "booking_id": 42,
    "amount": 200,
    "currency": "USD",
    "method": "card",
    "method_label": "Card (tokenised)",
    "status": "paid",
    "status_label": "Paid",
    "provider": null,
    "provider_reference": null,
    "paid_at": "2026-10-15T10:00:00+00:00",
    "created_at": "2026-10-15T10:00:00+00:00"
  }
}
```

## Error responses

Controller-level failures use a shared helper that returns:

```json
{ "message": "Human readable text.", "reason": "machine_readable_code" }
```

`reason` is present only when the controller supplies it. A generic 4xx/5xx
without a `reason` still carries `message`.

| HTTP | `reason` | Where | When |
| --- | --- | --- | --- |
| 401 | `invalid_credentials` | `POST /auth/login` | Unknown account or wrong password |
| 403 | `account_inactive` | `POST /auth/login` | Account is not active |
| 401 | `challenge_expired` | `/auth/two-factor`, `/auth/resend` | Challenge token missing, wrong ability, expired, or its user is no longer active |
| 422 | `invalid` | `POST /auth/two-factor` | The submitted code is not correct |
| 422 | `expired` | `POST /auth/two-factor` | The code has expired |
| 422 | `no_code` | `POST /auth/two-factor` | No active code found |
| 422 | `too_many_attempts` | `POST /auth/two-factor` | Attempt cap reached for the code |
| 429 | `cooldown` | `POST /auth/login`, `/auth/resend` | A code was sent too recently |
| 429 | `hourly_limit` | `POST /auth/login`, `/auth/resend` | Too many codes requested in an hour |
| 400 | `2fa_disabled` | `POST /auth/resend` | Account has 2FA disabled |
| 422 | `invalid_password` | `PUT /auth/password` | Current password incorrect |
| 403 | `forbidden` | `GET /bookings/{id}`, `POST /bookings/{id}/cancel` | Guest acting on another guest's booking |
| 422 | `no_guest_profile` | `POST /bookings` (guest) | Account is not linked to a guest profile |
| 409 | `no_availability` | `POST /bookings` (guest) | No room of that type is free |
| 409 | `booking_conflict` | `POST /bookings` | Booking service rejected the reservation |
| 409 | `not_cancellable` | `POST /bookings/{id}/cancel` | Booking can no longer be cancelled |
| 409 | `cancel_failed` | `POST /bookings/{id}/cancel` | Booking service rejected the cancellation |
| 409 | `check_in_failed` | `POST /bookings/{id}/check-in` | Check-in rejected by a business rule |
| 409 | `check_out_failed` | `POST /stays/{id}/check-out` | Check-out rejected by a business rule |

Other framework-level responses:

- **`401`** unauthenticated (missing/invalid bearer token):
  `{"message": "Unauthenticated."}` (no `reason`).
- **`403`** forbidden by a `can:` route middleware or a policy:
  `{"message": "You are not allowed to perform this action."}` (no `reason`).
- **`403`** non-active account: the `active` middleware
  (`EnsureAccountIsActive`) refuses an authenticated account whose status is not
  `active`, with `{"message": "This account has been archived."}` or
  `{"message": "This account has been deactivated."}` (no `reason`). The
  middleware tears down the web session only when one exists, so stateless
  bearer requests are handled safely; this is covered by
  `ApiTest::test_an_archived_account_cannot_use_an_existing_api_token`.
- **`422`** validation failure (Laravel default):
  `{"message": "...", "errors": { "field": ["..."] }}` (no `reason`).
- An uncaught `BusinessRuleException` is rendered by `bootstrap/app.php` as
  `422` with `{"message": "...", "errors": {"business_rule": ["..."]}}`.

## Rate limiting

Three named rate limiters are attached explicitly to the authentication routes
in `routes/api.php`:

| Route | Limiter | Limit |
| --- | --- | --- |
| `POST /auth/login` | `throttle:login` | 5 per minute per email+IP, plus 20 per minute per IP |
| `POST /auth/two-factor` | `throttle:two-factor` | 10 per minute, keyed by the SHA-256 of the challenge token (or the IP) |
| `POST /auth/resend` | `throttle:otp-resend` | 2 per minute, keyed the same way |

The login limit count comes from `LOGIN_MAX_ATTEMPTS` (default 5); the other
limits are fixed.

In addition, `AppServiceProvider::registerRateLimiters()` registers a named
`api` limiter (60 per minute, keyed by the authenticated user id, or the IP for
unauthenticated requests) and `bootstrap/app.php` calls `throttleApi()`, so it
is applied to the whole `/api` route group. Every API route is therefore also
capped at 60 requests per minute, on top of the stricter per-route auth limits
above. (The backend test suite exercises the API with this limiter active and
passes.)

## Pagination

`GET /bookings` is the only paginated endpoint. It uses Laravel's
length-aware pagination and returns:

```json
{
  "data": [ ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "last_page": 1,
    "total": 0
  }
}
```

The page size is `per_page` taken from the query string, clamped between 1 and
50, defaulting to 15. A guest account with no linked guest profile is short-
circuited to an empty `data` array with `current_page` 1, `per_page` 15,
`last_page` 1 and `total` 0.

## cURL examples

Public health check:

```bash
curl -s http://127.0.0.1:8000/api/health \
  -H "Accept: application/json"
```

Public room-type catalogue:

```bash
curl -s http://127.0.0.1:8000/api/room-types \
  -H "Accept: application/json"
```

Step 1 — login:

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"ada@example.com","password":"correct horse battery staple"}'
```

Step 2 — exchange the code for a bearer token (take `challenge_token` from the
step-1 response, and `code` from the email or the dev-only `dev_code`):

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/two-factor \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"challenge_token":"<challenge token>","code":"123456"}'
```

Authenticated booking list (use the `token` returned by step 2):

```bash
curl -s "http://127.0.0.1:8000/api/bookings?per_page=15" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```
