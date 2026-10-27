#!/bin/sh
set -e

echo "Initializing P4DC container..."

# 1. Setup .env file
if [ ! -s /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Ensure APP_KEY exists in .env
ACTIVE_KEY="${APP_KEY:-base64:uYNo+gOBy7BDz49fHVpAEqbOUrmVIn9ekhZa799NOro=}"
if grep -q "^APP_KEY=" /var/www/html/.env; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${ACTIVE_KEY}|" /var/www/html/.env
else
    echo "APP_KEY=${ACTIVE_KEY}" >> /var/www/html/.env
fi

# Ensure APP_NAME exists
if ! grep -q "^APP_NAME=" /var/www/html/.env; then
    echo 'APP_NAME="P4DC Cyber Range"' >> /var/www/html/.env
fi

# Ensure APP_DEBUG is set
if [ -n "$APP_DEBUG" ]; then
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=${APP_DEBUG}|" /var/www/html/.env || echo "APP_DEBUG=${APP_DEBUG}" >> /var/www/html/.env
else
    # Default to true temporarily so any error is clearly readable
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=true|" /var/www/html/.env || echo "APP_DEBUG=true" >> /var/www/html/.env
fi

# Determine DB connection
if [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    echo "Using PostgreSQL database..."
    export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=${DB_CONNECTION}|" /var/www/html/.env || echo "DB_CONNECTION=${DB_CONNECTION}" >> /var/www/html/.env
else
    echo "Using SQLite database..."
    export DB_CONNECTION="sqlite"
    export DB_DATABASE="/var/www/html/database/database.sqlite"
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|" /var/www/html/.env || echo "DB_CONNECTION=sqlite" >> /var/www/html/.env
    if grep -q "^DB_DATABASE=" /var/www/html/.env; then
        sed -i "s|^DB_DATABASE=.*|DB_DATABASE=/var/www/html/database/database.sqlite|" /var/www/html/.env
    else
        echo "DB_DATABASE=/var/www/html/database/database.sqlite" >> /var/www/html/.env
    fi
    # Disable postgres DB_HOST in .env so fallback to sqlite is clean
    sed -i "s|^DB_HOST=|#DB_HOST=|" /var/www/html/.env 2>/dev/null || true

    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
    chmod 777 /var/www/html/database
    chmod 666 /var/www/html/database/database.sqlite
fi

# Ensure SESSION_DRIVER is file
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=file|" /var/www/html/.env || echo "SESSION_DRIVER=file" >> /var/www/html/.env

# Pass Google OAuth variables into .env if present
if [ -n "$GOOGLE_CLIENT_ID" ]; then
    sed -i "s|^GOOGLE_CLIENT_ID=.*|GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}|" /var/www/html/.env || echo "GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}" >> /var/www/html/.env
fi
if [ -n "$GOOGLE_CLIENT_SECRET" ]; then
    sed -i "s|^GOOGLE_CLIENT_SECRET=.*|GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}|" /var/www/html/.env || echo "GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}" >> /var/www/html/.env
fi
if [ -n "$GOOGLE_REDIRECT_URI" ]; then
    sed -i "s|^GOOGLE_REDIRECT_URI=.*|GOOGLE_REDIRECT_URI=${GOOGLE_REDIRECT_URI}|" /var/www/html/.env || echo "GOOGLE_REDIRECT_URI=${GOOGLE_REDIRECT_URI}" >> /var/www/html/.env
elif [ -n "$RENDER_EXTERNAL_URL" ]; then
    sed -i "s|^GOOGLE_REDIRECT_URI=.*|GOOGLE_REDIRECT_URI=${RENDER_EXTERNAL_URL}/auth/google/callback|" /var/www/html/.env || echo "GOOGLE_REDIRECT_URI=${RENDER_EXTERNAL_URL}/auth/google/callback" >> /var/www/html/.env
fi

chmod 644 /var/www/html/.env

# Configure Apache listening port if Render specifies PORT
LISTEN_PORT="${PORT:-80}"
if [ "$LISTEN_PORT" != "80" ]; then
    echo "Configuring Apache to listen on port ${LISTEN_PORT}..."
    sed -i "s/80/${LISTEN_PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf 2>/dev/null || true
fi

# 2. Ensure storage and cache directories exist with full permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# 3. Clear existing cached configs so fresh .env is picked up
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 4. Run database migrations
echo "Running migrations..."
php artisan migrate --force || true

# 5. Seed challenges and prepare evidence files
echo "Seeding challenges..."
php artisan db:seed --class=SourceTrailChallengeSeeder --force || true
php artisan db:seed --class=CtfChallengesSeeder --force || true
php scripts/generate_gateway_evidence.php || true
php artisan storage:link --force || true

# 6. Seed default admin if no admin exists
echo "Checking default admin..."
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (\App\Models\User::where('role', 'admin')->count() === 0) { \App\Models\User::create(['username' => 'padcadmin', 'email' => 'admin@example.com', 'password' => 'Admin12345!', 'role' => 'admin', 'is_active' => true]); echo \"Default admin created.\n\"; } else { echo \"Admin already exists.\n\"; }" || true

# 7. Final ownership and permission check
chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache || true
chmod -R 777 /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache || true

echo "Starting Apache on port ${LISTEN_PORT}..."
exec "$@"

