#!/usr/bin/env bash
# Builds the image, starts it the way the server would (plain HTTP, a fresh volume) and
# checks what a deployment has to get right: it starts, it is healthy, the registration is
# closed, the headers are there, someone can sign in, and the data survives replacing the
# container.
#
#   docker/smoke-test.sh            # builds, then tests
#   SKIP_BUILD=1 docker/smoke-test.sh   # tests the image that is already built
set -euo pipefail

# Git Bash on Windows would rewrite arguments that look like paths (/data/...).
export MSYS_NO_PATHCONV=1

IMAGE=${IMAGE:-ruta-laravel:smoke}
NAME=ruta-smoke
VOLUME=ruta-smoke-data
EMAIL=smoke@example.com
PASSWORD='Smoke-Passw0rd-2026!'
KEY="base64:$(head -c 32 /dev/urandom | base64)"
failures=0

# Docker picks a free port on the loopback (the machine may be running other projects), so
# the address is known only once a container is up.
BASE=

# Relative on purpose: with path conversion off, a native Windows curl cannot write to /tmp.
JAR=ruta-smoke-cookies.$$

pass() { printf '  ok   %s\n' "$1"; }
fail() { printf '  FAIL %s\n' "$1"; failures=$((failures + 1)); }
check() {
    local description=$1
    shift

    if "$@" >/dev/null 2>&1; then pass "$description"; else fail "$description"; fi
}

status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
header() { curl -sI "$1" | tr -d '\r' | grep -i "^$2:" | head -1 || true; }
sql() { docker exec "$NAME" php -r "echo (new PDO('sqlite:/data/database.sqlite'))->query('$1')->fetchColumn();"; }

cleanup() {
    rm -f "$JAR"
    docker rm -f "$NAME" >/dev/null 2>&1 || true
    docker volume rm "$VOLUME" >/dev/null 2>&1 || true
}
trap cleanup EXIT

start() {
    docker run -d --name "$NAME" -p 127.0.0.1::80 -v "${VOLUME}:/data" \
        -e APP_KEY="$KEY" \
        -e SERVER_NAME=:80 \
        -e REGISTRATION_ENABLED=false \
        "$IMAGE" >/dev/null

    BASE="http://127.0.0.1:$(docker port "$NAME" 80/tcp | head -1 | sed 's/.*://')"

    for _ in $(seq 1 60); do
        if [ "$(docker inspect -f '{{.State.Health.Status}}' "$NAME")" = healthy ]; then
            return 0
        fi

        sleep 2
    done

    return 1
}

# Nothing after a container that did not start means anything: say why and stop.
require_start() {
    local before=$failures

    check "$1" start

    if [ "$failures" -gt "$before" ]; then
        echo
        docker logs "$NAME" 2>&1 | tail -40
        exit 1
    fi
}

# Signs in with the form's own request (the XSRF cookie, then the JSON login) and prints
# the status of the dashboard: 200 when it worked, a redirect when it did not.
dashboard_after_login() {
    local token
    rm -f "$JAR"

    curl -s -c "$JAR" -o /dev/null "$BASE/login"
    token=$(awk '$6 == "XSRF-TOKEN" { print $7 }' "$JAR" | node -e 'process.stdout.write(decodeURIComponent(require("fs").readFileSync(0, "utf8").trim()))')

    curl -s -b "$JAR" -c "$JAR" -o /dev/null \
        -H "X-XSRF-TOKEN: ${token}" -H 'Accept: application/json' -H 'Content-Type: application/json' \
        -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" "$BASE/login"

    status -b "$JAR" "$BASE/dashboard"
}

if [ -z "${SKIP_BUILD:-}" ]; then
    echo '== Building the image'
    docker build -t "$IMAGE" .
fi

cleanup

echo '== Starting a container'
docker volume create "$VOLUME" >/dev/null
require_start 'starts and reports itself healthy'
echo "   (answering at $BASE)"

echo '== What the public sees'
check 'GET /up answers 200' test "$(status "$BASE/up")" = 200
check 'GET /login answers 200' test "$(status "$BASE/login")" = 200
check 'the login page links the manifest' bash -c "curl -s '$BASE/login' | grep -q 'rel=\"manifest\"'"
check 'the login page does not offer to sign up' bash -c "curl -s '$BASE/login' | grep -Eq 'canRegister(&quot;|\")[: ]+false'"
check 'GET /register is not found (registration is closed)' test "$(status "$BASE/register")" = 404
check 'a private page sends a visitor to the login' test "$(status "$BASE/dashboard")" = 302
check 'the manifest is served as JSON' bash -c "curl -sI '$BASE/manifest.json' | grep -qi '^content-type:.*json'"
check 'the service worker is never cached' bash -c "curl -sI '$BASE/sw.js' | grep -qi '^cache-control:.*no-cache'"
check 'the service worker is JavaScript' bash -c "curl -sI '$BASE/sw.js' | grep -qi '^content-type:.*javascript'"
check 'the offline page is served' test "$(status "$BASE/offline.html")" = 200

asset=$(curl -s "$BASE/login" | grep -oE '/build/assets/[A-Za-z0-9_.-]+\.js' | head -1 || true)
check 'built files are cached for a year' bash -c "curl -sI '$BASE$asset' | grep -qi '^cache-control:.*immutable'"
check 'X-Content-Type-Options: nosniff is sent' bash -c "curl -sI '$BASE/login' | grep -qi '^x-content-type-options: nosniff'"
check 'a Referrer-Policy is sent' bash -c "curl -sI '$BASE/login' | grep -qi '^referrer-policy:'"
check 'the server does not announce itself' test -z "$(header "$BASE/login" Server)"
check 'HSTS is not sent over plain HTTP' test -z "$(header "$BASE/login" Strict-Transport-Security)"
check 'only the web port is published (not the health port)' bash -c "! docker port '$NAME' | grep -q 8081"

echo '== Inside the container'
check 'runs as a user that is not root' test "$(docker exec "$NAME" id -u)" != 0
check 'loaded the roadmap into the database' test "$(sql 'select count(*) from modules')" -ge 20
check 'loaded the lessons into the database' test "$(sql 'select count(*) from lessons')" -ge 40
check 'did not create the development user' test "$(sql 'select count(*) from users')" = 0
check 'the configuration is cached' docker exec "$NAME" test -f /app/bootstrap/cache/config.php

echo '== Signing in'
docker exec "$NAME" php artisan tinker --execute="\$u = new App\\Models\\User(['name' => 'Smoke', 'email' => '$EMAIL', 'password' => '$PASSWORD']); \$u->forceFill(['email_verified_at' => now()])->save();" >/dev/null || true
check 'a user can sign in and reach the dashboard' test "$(dashboard_after_login)" = 200

echo '== Backups'
check 'database:backup writes a copy on the volume' docker exec "$NAME" php artisan database:backup --keep=3
check 'the copy is in /data/backups' bash -c "docker exec '$NAME' sh -c 'ls /data/backups/ruta-laravel-*.sqlite' | grep -q sqlite"

echo '== Replacing the container, as an update does'
docker rm -f "$NAME" >/dev/null
require_start 'a new container starts on the same volume'
check 'the user and the session login still work' test "$(dashboard_after_login)" = 200
check 'the backup survived' bash -c "docker exec '$NAME' sh -c 'ls /data/backups/ruta-laravel-*.sqlite' | grep -q sqlite"

# The same steps as "Restaurar una copia" in docs/deploy.md.
echo '== Restoring a backup'
backup=$(docker exec "$NAME" sh -c 'ls /data/backups/ruta-laravel-*.sqlite | tail -1')
docker exec "$NAME" php -r "(new PDO('sqlite:/data/database.sqlite'))->exec('delete from users');"
check 'the user is gone before the restore' test "$(sql 'select count(*) from users')" = 0
docker rm -f "$NAME" >/dev/null
docker run --rm --user root --entrypoint sh -v "${VOLUME}:/data" "$IMAGE" -c \
    "cp '$backup' /data/database.sqlite && chown app:app /data/database.sqlite && rm -f /data/database.sqlite-wal /data/database.sqlite-shm"
require_start 'the app starts on the restored database'
check 'the restored user can sign in' test "$(dashboard_after_login)" = 200

echo
if [ "$failures" -gt 0 ]; then
    echo "${failures} check(s) failed."
    docker logs "$NAME" 2>&1 | tail -30
    exit 1
fi

echo 'All checks passed.'
