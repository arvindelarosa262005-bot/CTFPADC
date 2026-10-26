FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev unzip \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader

COPY --chown=www-data:www-data . .
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN touch .env \
    && composer dump-autoload --no-dev --no-interaction --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public/challenges storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache public/storage

EXPOSE 80
CMD ["apache2-foreground"]
