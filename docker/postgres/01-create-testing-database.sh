#!/bin/sh
set -eu

if [ -z "${POSTGRES_TEST_DB:-}" ]; then
    exit 0
fi

if ! psql --username "$POSTGRES_USER" --dbname postgres --tuples-only --command "SELECT 1 FROM pg_database WHERE datname = '${POSTGRES_TEST_DB}'" | grep -q 1; then
    createdb --username "$POSTGRES_USER" --owner "$POSTGRES_USER" "$POSTGRES_TEST_DB"
fi
