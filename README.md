# Khoren API

Laravel API for Khoren. Served locally by [Laravel Herd](https://herd.laravel.com/) (typically `https://khoren-api.test`).

## Features

- **Google authentication** — Socialite OAuth + Sanctum API tokens

## API documentation (Scramble)

Interactive OpenAPI docs (local environment only by default):

- UI: [`/docs/api`](https://khoren-api.test/docs/api)
- Spec: [`/docs/api.json`](https://khoren-api.test/docs/api.json)

Narrative notes: [docs/google-authentication.md](docs/google-authentication.md)

## Requirements

- PHP 8.3+
- Composer
- Database configured in `.env` (see `.env.example`)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Set Google OAuth credentials if you use sign-in with Google:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

## Tests

```bash
php artisan test --compact
```

## Deploy (CI/CD)

Push to `main` deploys to the existing AWS EC2 via GitHub Actions (S3 + SSM).

Setup and secrets: [docs/deploy-github-actions.md](docs/deploy-github-actions.md)
