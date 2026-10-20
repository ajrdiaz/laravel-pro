#!/bin/sh
# Runs every time the container starts, before the web server: prepares the database and
# the caches, then hands over to the image's own entrypoint.
set -eu

cd /app

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty. Create one with:" >&2
    echo "  docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show" >&2
    echo "and put it in .env.production." >&2
    exit 1
fi

# The database lives on the volume. The first start creates the file.
if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
fi

php artisan migrate --force --no-interaction

# The roadmap, lessons, stacks and projects come from files in the repository. The seeders
# update what exists instead of duplicating it, so running them on every start is safe, and
# a new version of the content arrives with the new image.
php artisan db:seed --force --no-interaction

# Config, routes, events and views are cached once, from the environment of this container.
php artisan optimize --no-interaction

exec docker-php-entrypoint "$@"
