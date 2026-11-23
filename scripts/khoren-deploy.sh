#!/bin/bash
set -euo pipefail
export HOME=/root
export COMPOSER_HOME=/root/.composer
export COMPOSER_ALLOW_SUPERUSER=1
APP_DIR=/var/www/khoren-api
BUCKET=khoren-api-deploy-292759875127
KEY=releases/khoren-api-latest.tar.gz
STAGE=/tmp/khoren-release
STAMP=$(date -u +%Y%m%d%H%M%S)
SECRET_ARN="arn:aws:secretsmanager:eu-north-1:292759875127:secret:rds!db-6292b0dc-dc4b-4540-a216-98b4ce50347f-1K2iAq"

aws s3 cp "s3://${BUCKET}/${KEY}" /tmp/khoren-api-latest.tar.gz
rm -rf "$STAGE"
mkdir -p "$STAGE"
tar -xzf /tmp/khoren-api-latest.tar.gz -C "$STAGE"

if [ -f "$APP_DIR/.env" ]; then
  cp "$APP_DIR/.env" /tmp/khoren.env.bak
fi

mkdir -p "$APP_DIR"
rsync -a --delete \
  --exclude='.env' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='bootstrap/cache/*.php' \
  "$STAGE/" "$APP_DIR/" || true

if [ -f /tmp/khoren.env.bak ]; then
  cp /tmp/khoren.env.bak "$APP_DIR/.env"
fi

# Refresh DB password from Secrets Manager (file-based to avoid $ mangling)
aws secretsmanager get-secret-value \
  --secret-id "$SECRET_ARN" \
  --query SecretString \
  --output text > /tmp/rds-secret.json

php <<'PHP'
$j = json_decode(file_get_contents('/tmp/rds-secret.json'), true);
$password = $j['password'];
$envPath = '/var/www/khoren-api/.env';
$lines = file($envPath, FILE_IGNORE_NEW_LINES);
$updates = [
    'DB_PASSWORD' => $password,
    'DB_SSLMODE' => 'require',
    'SESSION_DRIVER' => 'file',
    'CACHE_STORE' => 'database',
    'QUEUE_CONNECTION' => 'database',
];
$format = function (string $key, string $value): string {
    if (preg_match('/[\s#\'"\\\\$]/', $value)) {
        return $key . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
    return $key . '=' . $value;
};
$seen = [];
foreach ($lines as $i => $line) {
    foreach ($updates as $key => $value) {
        if (str_starts_with($line, $key . '=')) {
            $lines[$i] = $format($key, $value);
            $seen[$key] = true;
        }
    }
}
foreach ($updates as $key => $value) {
    if (! isset($seen[$key])) {
        $lines[] = $format($key, $value);
    }
}
file_put_contents($envPath, implode("\n", $lines) . "\n");
PHP
rm -f /tmp/rds-secret.json

mkdir -p "$APP_DIR/storage/framework/"{cache,sessions,views} "$APP_DIR/storage/logs" "$APP_DIR/bootstrap/cache"
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache || true
chown -R apache:apache "$APP_DIR"
systemctl reload php-fpm || systemctl reload php8.3-fpm || systemctl reload php8.4-fpm || systemctl reload php8.5-fpm || true
systemctl reload nginx || true
echo "DEPLOY_OK $STAMP"
