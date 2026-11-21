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

**201** — `{ token, user }`  
**422** — validation errors (e.g. `"Password doesn't match!"` on `password`)

Rate limit: 10 / minute.

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

**200** — `{ token, user }`  
**422** — invalid credentials (including Google-only accounts with no password)

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
| Sign in link | `POST /api/sign-in` |
