#!/bin/sh
set -eu

# This is the development image, and it ships no application source: compose.yaml
# bind-mounts the project onto /var/www/html. A host that builds the repository's
# root `Dockerfile` therefore starts a container whose working directory is empty,
# and the first symptom is an unhelpful `sha256sum: composer.lock` from the line
# below, repeated for as long as the host keeps restarting the container. Say what
# is actually wrong instead. Production builds `Dockerfile.production`.
if [ ! -f composer.json ] || [ ! -f composer.lock ]; then
    echo "Development container error: no application source in $(pwd)." >&2
    echo "This image expects the project bind-mounted there; see compose.yaml." >&2
    echo "Deploying? Build Dockerfile.production, not this Dockerfile. On Railway" >&2
    echo "set the service variable RAILWAY_DOCKERFILE_PATH=Dockerfile.production." >&2
    exit 1
fi

copper_eagle_composer_hash="$(sha256sum composer.lock | cut -d ' ' -f 1)"
copper_eagle_composer_stamp="vendor/.copper-eagle-composer-lock.sha256"

if [ ! -f vendor/autoload.php ] \
    || [ ! -f "$copper_eagle_composer_stamp" ] \
    || [ "$(sed -n '1p' "$copper_eagle_composer_stamp")" != "$copper_eagle_composer_hash" ]; then
    composer install --no-interaction --prefer-dist
    printf '%s\n' "$copper_eagle_composer_hash" > "$copper_eagle_composer_stamp"
fi

copper_eagle_package_hash="$(sha256sum package-lock.json | cut -d ' ' -f 1)"
copper_eagle_package_stamp="node_modules/.copper-eagle-package-lock.sha256"

if [ ! -x node_modules/.bin/vite ] \
    || [ ! -f "$copper_eagle_package_stamp" ] \
    || [ "$(sed -n '1p' "$copper_eagle_package_stamp")" != "$copper_eagle_package_hash" ]; then
    npm ci
    printf '%s\n' "$copper_eagle_package_hash" > "$copper_eagle_package_stamp"
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p database storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
touch database/database.sqlite

php artisan storage:link --quiet

if grep -q '^APP_KEY=$' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force

exec "$@"
