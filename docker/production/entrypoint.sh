#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "Application startup error: APP_KEY is required." >&2
    exit 1
fi

# This probe boots the whole application, so it fails for every reason boot can
# fail, not just a bad APP_KEY. It used to discard the output and name APP_KEY
# regardless, which sent operators to replace a working secret while the real
# cause — a canonical host that disagrees with APP_URL, say — stayed hidden.
# Report what actually threw.
if ! bootstrap_error="$(php -r '
require "vendor/autoload.php";
try {
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $app->make("encrypter");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
' 2>&1)"; then
    echo "Application startup error: the application could not boot." >&2
    echo "${bootstrap_error:-APP_KEY must be a valid Laravel encryption key.}" >&2
    exit 1
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    case "${APP_DEBUG:-false}" in
        1|true|TRUE|True|yes|YES|Yes|on|ON|On)
            echo "Application startup error: APP_DEBUG must be false in production." >&2
            exit 1
            ;;
    esac

    if [ "${MAIL_MAILER:-}" != "smtp" ]; then
        echo "Application startup error: MAIL_MAILER must be smtp in production." >&2
        exit 1
    fi

    if [ -z "${MAIL_HOST:-}" ] \
        || [ -z "${MAIL_USERNAME:-}" ] \
        || [ -z "${MAIL_PASSWORD:-}" ] \
        || [ -z "${MAIL_FROM_ADDRESS:-}" ] \
        || [ -z "${MAIL_FROM_NAME:-}" ]; then
        echo "Application startup error: SMTP mail requires MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, and MAIL_FROM_NAME." >&2
        exit 1
    fi
fi

mkdir -p \
    /data \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

database_connection="${DB_CONNECTION:-sqlite}"

if [ "$database_connection" = "sqlite" ]; then
    database_path="${DB_DATABASE:-}"

    case "$database_path" in
        /data/*)
            ;;
        *)
            echo "Application startup error: SQLite DB_DATABASE must be an absolute path under /data." >&2
            exit 1
            ;;
    esac

    mkdir -p "$(dirname "$database_path")"
    touch "$database_path"
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan optimize

# A single-container deploy (Dokploy's Application type) has no separate
# scheduler service, so the app container runs one in the background when
# asked. Off by default: the Compose deploy runs its own `scheduler` service,
# and two schedulers would run every task twice. The loop restarts it if it
# ever exits, and it stops with the container.
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    (
        while true; do
            php artisan schedule:work || true
            sleep 5
        done
    ) &
fi

exec "$@"
