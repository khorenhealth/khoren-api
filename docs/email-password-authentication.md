# Email / password authentication

Companion to Google OAuth. Same Sanctum token shape: `{ "token": "...", "user": { ... } }`.

Interactive OpenAPI: [`/docs/api`](/docs/api).

## Sign up

```http
POST /api/sign-up
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "youremail@gmail.com",
  "password": "Secret1!",
  "password_confirmation": "Secret1!",
  "terms": true,
  "name": "Optional Name"
}
```

| Field | Rules |
|---|---|
| `email` | required, email, unique |
| `password` | min 8, upper + lower, number, symbol; must match `password_confirmation` |
| `password_confirmation` | required with password |
| `terms` | must be accepted (`true` / `1` / `"yes"` / `"on"`) |
| `name` | optional; defaults to the email local-part |

**201** — `{ email, expires_at, resend_available_at }`  
No API token yet. A 6-digit code is emailed to `email`. It expires 10 minutes after it is sent. `resend_available_at` is 60 seconds after send.

**422** — validation errors (e.g. `"Password doesn't match!"` on `password`)

Rate limit: 10 / minute (shared with sign-in).

## Verify email

```http
POST /api/email/verify
```

```json
{
  "email": "youremail@gmail.com",
  "code": "012345"
}
```

**200** — `{ token, user }` with `email_verified_at` set  
**422** — missing fields, or the code is incorrect, expired, or no longer valid after 5 wrong attempts

## Resend code

```http
POST /api/email/resend
```

```json
{
  "email": "youremail@gmail.com"
}
```

**200** — `{ email, expires_at, resend_available_at }` when a new code is sent  
**200** — `{ message }` and no email when the address is unknown or already verified  
**429** — `{ message, resend_available_at }` while the countdown has not finished

## Change email

Only for an unverified account. The password is required so someone who only knows the address cannot move the account to their inbox.

```http
POST /api/email/change
```

```json
{
  "email": "youremail@gmail.com",
  "password": "Secret1!",
  "new_email": "other@example.com"
}
```

**200** — `{ email, expires_at, resend_available_at }` for the new address, and a new code is emailed there  
**422** — wrong password, `new_email` already taken, or the account is already verified

Verify, resend, and change are limited to 10 requests per minute per email address and IP.

## Sign in

```http
POST /api/sign-in
```

```json
{
  "email": "youremail@gmail.com",
  "password": "Secret1!"
}
```

**200** — `{ token, user }` when the email is verified  
**403** — `{ message, email }` when the password is correct but the email is not verified  
**422** — invalid credentials (including a wrong password on an unverified account, and Google-only accounts with no password)

## Sign out

```http
POST /api/logout
Authorization: Bearer {token}
```

**204** — current token revoked  
**401** — missing / invalid token

## Frontend mapping (from your form)

| UI | API |
|---|---|
| Email | `email` |
| Create Password | `password` |
| Confirm Password | `password_confirmation` |
| Checklist (8+, upper, number, special) | server also requires a **lowercase** letter |
| I Agree… | `terms: true` |
| Sign Up | `POST /api/sign-up` |
| Six code boxes | `code` on `POST /api/email/verify` |
| Verify | `POST /api/email/verify` |
| Didn't get a code? countdown | `resend_available_at`, then `POST /api/email/resend` |
| Change email | `POST /api/email/change` with the account `password` and `new_email` |
| Sign in link | `POST /api/sign-in` |
