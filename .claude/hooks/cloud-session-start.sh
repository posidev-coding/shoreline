#!/bin/bash
# SessionStart hook: readies a Claude Code cloud session (no-op on a local machine).
# Starts Postgres and installs dependencies, since the cloud environment's cache
# keeps files but not running services. Always exits 0 so a hiccup never blocks a session.

[ "${CLAUDE_CODE_REMOTE:-}" = "true" ] || exit 0

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}" || exit 0
LOG=/tmp/shoreline-session-start.log
# The version whose Chromium the setup script caches. Keep in step with setup-script.sh.
PLAYWRIGHT_VERSION=1.64.1
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo -n"

composer_ok=1
npm_ok=1
migrate_ok=1

{
  $SUDO service postgresql start || $SUDO pg_ctlcluster 16 main start
  for _ in $(seq 1 20); do pg_isready -q && break; sleep 1; done

  # The GitHub proxy refuses zipball downloads, and a git source install clones every
  # package's full history (and can't install dist-only packages such as phpstan/phpstan).
  # So seed Composer's dist cache from the locked commits and install from it. Anything
  # the prefetch missed fails the dist install; the retry installs it from git source,
  # which the setup script sets as the global preference.
  if [ -f composer.json ]; then
    [ -f composer.lock ] && bash .claude/hooks/composer-prefetch.sh composer.lock
    composer install --no-interaction --no-progress --prefer-install=dist \
      || composer install --no-interaction --no-progress \
      || composer_ok=0
  fi

  if [ -f package.json ]; then
    if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi \
      || npm_ok=0
    # Browser tests and `php artisan serve` need compiled assets (public/build isn't committed).
    [ "$npm_ok" = 1 ] && grep -q '"build"' package.json && { npm run build || npm_ok=0; }
    # Match Chromium to the project's pinned Playwright version (no-op when already present).
    # Call the project's binary: if npm failed, bare `npx playwright` falls back to the image's
    # global 1.56.1 and downloads a Chromium build the project can't use.
    if grep -q '"playwright"' package.json && [ -x node_modules/.bin/playwright ]; then
      node_modules/.bin/playwright install chromium
    fi
  fi

  if [ -f artisan ] && [ -f .env.example ] && [ ! -f .env ]; then
    cp .env.example .env && php artisan key:generate --ansi
  fi

  # The cached snapshot holds empty databases. Seed the dev database the first time
  # (local /admin login); after that, just apply new migrations.
  if [ -f artisan ] && [ -f vendor/autoload.php ] && [ -f .env ]; then
    if php artisan migrate:status --no-interaction >/dev/null 2>&1; then
      php artisan migrate --force --no-interaction || migrate_ok=0
    else
      php artisan migrate --force --seed --no-interaction || migrate_ok=0
    fi
  fi
} >"$LOG" 2>&1

pg_state=$(pg_isready -q && echo "up" || echo "DOWN")
warn=""
php -r 'exit(extension_loaded("pdo_pgsql") && extension_loaded("intl") ? 0 : 1);' 2>/dev/null \
  || warn="${warn} WARNING: PHP extensions missing (re-run the environment setup script)."
{ [ "$composer_ok" = 0 ] || { [ -f composer.json ] && [ ! -f vendor/autoload.php ]; }; } \
  && warn="${warn} WARNING: composer install failed."
{ [ "$npm_ok" = 0 ] || { [ -f package.json ] && [ ! -d node_modules ]; }; } \
  && warn="${warn} WARNING: npm install failed."
[ "$migrate_ok" = 0 ] && warn="${warn} WARNING: database migrations failed."
pw=$([ -x node_modules/.bin/playwright ] && node_modules/.bin/playwright --version 2>/dev/null | awk '{print $2}')
[ -n "$pw" ] && [ "$pw" != "$PLAYWRIGHT_VERSION" ] \
  && warn="${warn} WARNING: project Playwright is ${pw}, but the setup script caches ${PLAYWRIGHT_VERSION}'s Chromium."
echo "Cloud session ready: $(php -r 'echo "PHP ".PHP_VERSION;' 2>/dev/null), Node $(node -v 2>/dev/null), Postgres ${pg_state}.${warn} Setup log: ${LOG}"
exit 0
