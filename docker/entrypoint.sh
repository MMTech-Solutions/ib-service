#!/usr/bin/env bash
set -euo pipefail

cd /var/www

ensure_env_file() {
    if [ -f /var/www/.env ]; then
        return 0
    fi

    if [ -f /var/www/.env.docker.example ]; then
        cp /var/www/.env.docker.example /var/www/.env
        echo "Created /var/www/.env from .env.docker.example"
        return 0
    fi

    echo "ERROR: /var/www/.env not found. Copy .env.docker.example to .env on the host and recreate the container."
    exit 1
}

ensure_env_file

wait_for_postgres() {
    local host="${DB_HOST:-postgres}"
    local port="${DB_PORT:-5432}"
    local user="${DB_USERNAME:-postgres}"
    local db="${DB_DATABASE:-postgres}"

    echo "Waiting for PostgreSQL at ${host}:${port}..."
    until pg_isready -h "$host" -p "$port" -U "$user" -d "$db" >/dev/null 2>&1; do
        sleep 2
    done
    echo "PostgreSQL is ready."
}

wait_for_postgres

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ missing; running composer install (first boot)..."
    composer install --prefer-dist --no-interaction
fi

php artisan migrate --force --no-interaction

case "${MMTECH_SERVICE:-}" in
    ib)
        echo "Service ib: migrations applied; no automatic seeders."
        echo "Optional: php artisan db:seed --class=LocalRbacSnapshotSeeder"
        ;;
    *)
        echo "MMTECH_SERVICE not set or unknown (${MMTECH_SERVICE:-}); skipping service-specific init."
        ;;
esac

exec /usr/bin/supervisord -c /etc/supervisord.conf
