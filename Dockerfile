# syntax=docker/dockerfile:1

# One image for the whole app: FrankenPHP (PHP plus the Caddy web server, which gets and
# renews HTTPS certificates by itself) serving Laravel. How to run it: docs/deploy.md.

ARG FRANKENPHP_VERSION=1.12.7-php8.4


# --- Stage 1: dependencies and the frontend build ---------------------------------------
#
# The Vite build needs PHP as well as Node, because the Wayfinder plugin asks Laravel to
# generate the typed routes. They are generated with registration on, whatever
# REGISTRATION_ENABLED says at runtime, which is what the login page's link relies on.
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS build

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions @composer

# Node comes from its own image, so nothing is piped from the internet into a shell.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# The dependency files first, so the layers below are reused until they change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction \
    && npm run build \
    && rm -rf node_modules


# --- Stage 2: what runs -----------------------------------------------------------------
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS app

ARG USER=app

# The web server runs as a normal user. It may still bind ports 80 and 443 thanks to one
# capability on the binary. /data is the volume: the SQLite file and the HTTPS certificates.
RUN <<-EOF
	set -eux
	useradd --create-home --shell /usr/sbin/nologin ${USER}
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp
	mkdir -p /data
	chown -R ${USER}:${USER} /data /config
EOF

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

WORKDIR /app

COPY --from=build --chown=${USER}:${USER} /app /app

RUN chmod +x /usr/local/bin/app-entrypoint

# The layout of the image: the database and the logs. Everything else comes from the
# environment file (see docker/production.env.example).
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SERVER_NAME=:80

USER ${USER}

VOLUME /data

EXPOSE 80 443 443/udp

# Asks the app itself (Laravel's /up route) on a port only the container can reach.
HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8081/up') === false ? 1 : 0);"

ENTRYPOINT ["app-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
