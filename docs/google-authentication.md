# Google Authentication

> **Interactive docs:** open [`/docs/api`](/docs/api) (Scramble / OpenAPI). This page is the narrative companion.
>
> Email/password sign-up and sign-in: `POST /api/sign-up`, `POST /api/sign-in`, `POST /api/logout` (same Sanctum token shape as Google).

Stateless Google OAuth via [Laravel Socialite](https://laravel.com/docs/socialite), with API tokens from [Laravel Sanctum](https://laravel.com/docs/sanctum).

## Overview

1. The client opens `GET /auth/google/redirect`.
2. The user signs in with Google.
3. Google returns to `GET /auth/google/callback`.
4. The API creates or reuses a user, issues a Sanctum personal access token, and returns JSON.
5. The client sends that token as `Authorization: Bearer {token}` on protected API routes (for example `GET /api/user`).

The OAuth flow is **stateless** (no session / CSRF state). That suits SPA and mobile clients that complete the redirect themselves or in a browser/webview.

Both Google auth routes are rate-limited to **10 requests per minute** per IP (`throttle:10,1`).

## Setup

### 1. Google Cloud Console

1. Create (or open) a project in [Google Cloud Console](https://console.cloud.google.com/).
2. Enable the **Google Identity** / OAuth client credentials for a **Web application**.
3. Add an authorized redirect URI that matches `GOOGLE_REDIRECT_URI` exactly, for example:
   - Local Herd: `https://khoren-api.test/auth/google/callback`
   - Or whatever you set in `.env` (must match `config/services.php` → `google.redirect`)

### 2. Environment

Copy values into `.env` (see `.env.example`):

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

`APP_URL` should be the public base URL of this API (Herd example: `https://khoren-api.test`).

### 3. Database

Ensure migrations have been run so `users.google_id` exists (nullable, unique):

```bash
php artisan migrate
```

## Endpoints

Base URL examples below use `{APP_URL}` (from your environment).

### Redirect to Google

```http
GET {APP_URL}/auth/google/redirect
```

| | |
|---|---|
| **Name** | `auth.google.redirect` |
| **Auth** | None |
| **Throttle** | 10 / minute |
| **Success** | `302` redirect to Google’s authorization URL |

Open this URL in a browser (or system browser / webview). Do not call it as a typical JSON API request expecting a body.

---

### Google callback

```http
GET {APP_URL}/auth/google/callback?code=...&state=...
```

| | |
|---|---|
| **Name** | `auth.google.callback` |
| **Auth** | None (Google query params from the OAuth redirect) |
| **Throttle** | 10 / minute |
| **Success** | `200` JSON with `token` and `user` |

Google hits this URL after consent. For production SPAs you often point the redirect URI at your frontend, then exchange the code there — this API currently **handles the callback itself** and returns JSON from this route.

#### Success response (`200`)

```json
{
  "token": "1|plainTextSanctumToken...",
  "user": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "email_verified_at": "2026-11-17T12:00:00.000000Z"
  }
}
```

| Field | Description |
|---|---|
| `token` | Sanctum personal access token (`name`: `google`). Store securely; send as Bearer token. |
| `user` | Public user fields (same shape as `UserResource`, **without** a `data` wrapper). |

#### Error responses

| Status | Message | When |
|---|---|---|
| `401` | `Google authentication failed.` | Socialite cannot complete the Google handshake, or Google ID is empty. |
| `422` | `Google did not provide an email address.` | Google user has no email. |
| `409` | `An account with this email already exists.` | Email matches an existing user, but Google did **not** mark the email as verified — account is not linked. |

---

### Current user

```http
GET {APP_URL}/api/user
Authorization: Bearer {token}
Accept: application/json
```

| | |
|---|---|
| **Auth** | Sanctum (`auth:sanctum`) |
| **Success** | `200` |

#### Success response (`200`)

```json
{
  "data": {
    "id": 1,
    "name": "Ada Lovelace",
    "email": "ada@example.com",
    "email_verified_at": "2026-11-17T12:00:00.000000Z"
  }
}
```

Note the top-level `data` wrapper on this endpoint (Eloquent API Resource as the full response).

#### Unauthorized (`401`)

Returned when the Bearer token is missing or invalid.

## Account resolution rules

On a successful Google callback the API resolves the user in this order:

1. **Existing Google account** — `users.google_id` matches Google’s subject ID → sign in (no new user).
2. **Link by verified email** — `users.email` matches, `google_id` is empty, and Google reports the email as verified (`email_verified` / `verified_email`) → set `google_id`, ensure `email_verified_at`, sign in.
3. **Create user** — no match → create a user with `google_id`, name (or email local-part / `"Google user"`), email, optional `email_verified_at`, and `password` = `null`.
4. **Conflict** — email matches an existing account but Google email is **not** verified → `409` (no link, no token).

## Client integration notes

- Persist the Sanctum `token` (secure storage on mobile; memory / httpOnly considerations on web as you prefer).
- Call protected routes with:

  ```http
  Authorization: Bearer {token}
  Accept: application/json
  ```

- The redirect + callback pair is intended for browser-based OAuth. A pure native “Google Sign-In” ID-token flow is **not** implemented; only Socialite’s authorization-code redirect is.
- Keep `GOOGLE_REDIRECT_URI` identical in Google Cloud, `.env`, and the URL the user’s browser actually hits.

## Configuration reference

| Env / config | Purpose |
|---|---|
| `GOOGLE_CLIENT_ID` → `services.google.client_id` | OAuth client ID |
| `GOOGLE_CLIENT_SECRET` → `services.google.client_secret` | OAuth client secret |
| `GOOGLE_REDIRECT_URI` → `services.google.redirect` | Must match Google Console redirect URI |

Routes live in `routes/web.php` (throttled group). The authenticated user route lives in `routes/api.php`.

## Testing

Feature coverage: `tests/Feature/GoogleAuthenticationTest.php`.

```bash
php artisan test --compact tests/Feature/GoogleAuthenticationTest.php
```

Tests use `Socialite::fake('google', ...)` so no real Google credentials are required in CI.
