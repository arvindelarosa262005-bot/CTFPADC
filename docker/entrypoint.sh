#!/bin/sh
set -e

# Fallback APP_KEY if not configured in environment
if [ -z "$APP_KEY" ]; then
    export APP_KEY="base64:uYNo+gOBy7BDz49fHVpAEqbOUrmVIn9ekhZa799NOro="
fi

if [ -z "$APP_NAME" ]; then
    export APP_NAME="P4DC Cyber Range"
fi

# Determine DB connection
if [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
else
    export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
fi

# If using sqlite, ensure directory and file exist with correct permissions
if [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p /var/www/html/database
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        touch /var/www/html/database/database.sqlite
    fi
    chmod -R 777 /var/www/html/database
fi

# Ensure storage directories exist with full write permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Run database migrations and seed challenges
echo "Running migrations..."
php artisan migrate --force || true

echo "Seeding challenges..."
php artisan db:seed --class=SourceTrailChallengeSeeder --force || true

# Seed default admin if no admin exists
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (\App\Models\User::where('role', 'admin')->count() === 0) { \App\Models\User::create(['username' => 'padcadmin', 'email' => 'admin@example.com', 'password' => 'Admin12345!', 'role' => 'admin', 'is_active' => true]); echo \"Default admin created.\n\"; }" || true

# Re-apply ownership & permissions after migrations in case root created files
chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 777 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache || true

echo "Starting web server..."
exec "$@"
