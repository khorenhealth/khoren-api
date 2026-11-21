# Deploy with GitHub Actions → AWS EC2

On every push to `main` (and via **Actions → Deploy to EC2 → Run workflow**), GitHub:

1. Runs Pest tests
2. Packages the app (no `vendor` / `.env`)
3. Uploads `s3://khoren-api-deploy-292759875127/releases/khoren-api-latest.tar.gz`
4. Runs `/usr/local/bin/khoren-deploy.sh` on EC2 `i-08689345c593bc513` via SSM

The instance keeps its existing `.env`, runs `composer install --no-dev`, `php artisan migrate --force`, and reloads PHP-FPM/Nginx.

## One-time GitHub secrets

Repo → **Settings → Secrets and variables → Actions** → add:

| Secret | Value |
|---|---|
| `AWS_ACCESS_KEY_ID` | Access key for IAM user `khoren-gha-deploy` |
| `AWS_SECRET_ACCESS_KEY` | Matching secret |

Optional: create a GitHub **Environment** named `production` (the workflow references it). You can add a required reviewer there later.

### Create / rotate the access key

```bash
aws iam create-access-key --user-name khoren-gha-deploy
```

Store the secret only in GitHub. Delete old keys after rotating:

```bash
aws iam list-access-keys --user-name khoren-gha-deploy
aws iam delete-access-key --user-name khoren-gha-deploy --access-key-id AKIA...
```

> **Why access keys?** This AWS project’s SCP blocks creating a GitHub OIDC identity provider (`iam:ListOpenIDConnectProviders` / OIDC setup). Scoped IAM user keys are the workable alternative. Prefer rotating keys periodically.

## First deploy checklist

1. Commit and push the workflow (and app changes) to `main`.
2. Confirm secrets are set.
3. Watch **Actions** until Deploy succeeds.
4. Hit the API: `http://13.49.212.179/` (update if the instance public IP changes).
5. Set production env on the instance (Google OAuth, `APP_URL`, etc.) if not already in `/var/www/khoren-api/.env` — the pipeline does **not** overwrite `.env`.

## Manual deploy from your machine

```bash
# package + upload (Git Bash / WSL)
tar -czf khoren-api-latest.tar.gz --exclude='.git' --exclude='vendor' --exclude='node_modules' --exclude='.env' .
aws s3 cp khoren-api-latest.tar.gz s3://khoren-api-deploy-292759875127/releases/khoren-api-latest.tar.gz

aws ssm send-command \
  --instance-ids i-08689345c593bc513 \
  --document-name AWS-RunShellScript \
  --parameters 'commands=["/usr/local/bin/khoren-deploy.sh"]'
```

## Related AWS resources

| Resource | Name / ID |
|---|---|
| Region | `eu-north-1` |
| EC2 | `i-08689345c593bc513` (`khoren-api`) |
| Deploy bucket | `khoren-api-deploy-292759875127` |
| RDS | `khoren-postgres` |
| IAM deploy user | `khoren-gha-deploy` |
| Instance deploy script | `/usr/local/bin/khoren-deploy.sh` |
