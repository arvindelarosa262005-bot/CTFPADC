#!/bin/sh
set -e

# Determine DB connection
if [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
else
    export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
fi

# If using sqlite, ensure directory and file exist with correct permissions
if [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p /var/www/html/database
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        touch /var/www/html/database/database.sqlite
    fi
    chown -R www-data:www-data /var/www/html/database
fi

# Ensure storage directories exist with write permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Run database migrations and seed challenges
echo "Running migrations..."
php artisan migrate --force --isolated || true

echo "Seeding challenges..."
php artisan db:seed --class=SourceTrailChallengeSeeder --force || true

# Seed default admin if no admin exists
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (\App\Models\User::where('role', 'admin')->count() === 0) { \App\Models\User::create(['username' => 'padcadmin', 'email' => 'admin@example.com', 'password' => 'Admin12345!', 'role' => 'admin', 'is_active' => true]); echo \"Default admin created.\n\"; }" || true

echo "Starting web server..."
exec "$@"
