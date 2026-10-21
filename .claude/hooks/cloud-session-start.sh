#!/bin/bash
# SessionStart hook: readies a Claude Code cloud session (no-op on a local machine).
# Starts Postgres and installs dependencies, since the cloud environment's cache
# keeps files but not running services. Always exits 0 so a hiccup never blocks a session.

[ "${CLAUDE_CODE_REMOTE:-}" = "true" ] || exit 0

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}" || exit 0
LOG=/tmp/shoreline-session-start.log
SUDO=""
[ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo -n"

{
  $SUDO service postgresql start || $SUDO pg_ctlcluster 16 main start
  for _ in $(seq 1 20); do pg_isready -q && break; sleep 1; done

  # No --prefer-dist: the setup script sets preferred-install=source globally,
  # because the GitHub proxy refuses zipball downloads in cloud sessions.
  if [ -f composer.json ]; then
    composer install --no-interaction --no-progress
  fi

  if [ -f package.json ]; then
    if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
    # Match Chromium to the project's pinned Playwright version (no-op when already present).
    if grep -q '"playwright"' package.json; then npx playwright install chromium; fi
  fi

  if [ -f artisan ] && [ -f .env.example ] && [ ! -f .env ]; then
    cp .env.example .env && php artisan key:generate --ansi
  fi
} >"$LOG" 2>&1

pg_state=$(pg_isready -q && echo "up" || echo "DOWN")
warn=""
php -r 'exit(extension_loaded("pdo_pgsql") && extension_loaded("intl") ? 0 : 1);' 2>/dev/null \
  || warn="${warn} WARNING: PHP extensions missing (re-run the environment setup script)."
[ -f composer.json ] && [ ! -f vendor/autoload.php ] && warn="${warn} WARNING: composer install failed."
[ -f package.json ] && [ ! -d node_modules ] && warn="${warn} WARNING: npm install failed."
echo "Cloud session ready: $(php -r 'echo "PHP ".PHP_VERSION;' 2>/dev/null), Node $(node -v 2>/dev/null), Postgres ${pg_state}.${warn} Setup log: ${LOG}"
exit 0
