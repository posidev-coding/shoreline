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

  if [ -f composer.json ]; then
    composer install --no-interaction --prefer-dist --no-progress
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
echo "Cloud session ready: $(php -r 'echo "PHP ".PHP_VERSION;' 2>/dev/null), Node $(node -v 2>/dev/null), Postgres ${pg_state}. Setup log: ${LOG}"
exit 0
