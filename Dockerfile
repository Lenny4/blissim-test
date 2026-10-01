FROM php:8.3-cli-alpine

# pdo_pgsql : stockage des commentaires / intl : Twig & Form / zip : Composer
RUN apk add --no-cache icu-dev libzip-dev libpq-dev git unzip \
    && docker-php-ext-install pdo_pgsql intl zip opcache

# Xdebug (débogage pas à pas, couverture de code), désactivé par défaut
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

# Les sources sont montées en volume (cf. docker-compose.yml). Au démarrage :
# installation des dépendances, import du catalogue FakeStore (un échec, ex. API
# injoignable, n'empêche pas le site de démarrer), puis serveur PHP intégré.
CMD ["sh", "-c", "composer install --no-interaction --prefer-dist && (php bin/console app:import-products || true) && php -S 0.0.0.0:8000 -t public"]
