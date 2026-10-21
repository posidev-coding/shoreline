#!/bin/bash
# Shoreline Doors: Claude Code cloud environment setup script.
#
# Paste this whole file into the "Setup script" field of the `Shoreline`
# environment at claude.ai/code. It runs once as root on Ubuntu 24.04 before
# Claude starts, and the resulting filesystem is cached (~7 days, or until this
# script or the network allowlist changes). Keep it under ~5 minutes and make
# sure it exits 0, or sessions won't start.
#
# Services don't survive the cache, so .claude/hooks/cloud-session-start.sh
# starts Postgres and installs project dependencies on every session.

set -uo pipefail
export DEBIAN_FRONTEND=noninteractive

log() { echo "[shoreline-setup] $*"; }

# --- PHP 8.5 (image ships 8.3; Pest 5 / activitylog v5 / sitemap 8 need 8.4+) ---
if ! command -v php8.5 >/dev/null 2>&1; then
  log "Installing PHP 8.5 from ppa:ondrej/php"
  codename="$(. /etc/os-release && echo "$VERSION_CODENAME")"
  curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C" \
    | gpg --dearmor --yes -o /usr/share/keyrings/ondrej-php.gpg
  echo "deb [signed-by=/usr/share/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu ${codename} main" \
    > /etc/apt/sources.list.d/ondrej-php.list
  apt-get update -y
  apt-get install -y --no-install-recommends \
    php8.5-cli php8.5-common php8.5-readline php8.5-pgsql php8.5-sqlite3 \
    php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip php8.5-intl \
    php8.5-bcmath php8.5-gd \
    || log "WARN: PHP 8.5 core install failed"
  # Optional extensions: don't block the session if a build isn't published yet.
  apt-get install -y --no-install-recommends php8.5-imagick || log "WARN: php8.5-imagick unavailable"
  apt-get install -y --no-install-recommends php8.5-pcov || log "WARN: php8.5-pcov unavailable"
fi

if command -v php8.5 >/dev/null 2>&1; then
  update-alternatives --set php /usr/bin/php8.5 >/dev/null 2>&1 || true
  # The image's own PHP may sit earlier on PATH; make `php` mean 8.5 everywhere.
  ln -sf /usr/bin/php8.5 /usr/local/bin/php
fi

# --- Composer (preinstalled; just bring it current) ---
composer self-update --2 >/dev/null 2>&1 || log "WARN: composer self-update skipped"

# --- Postgres 16 (preinstalled, not running): create the app role + databases ---
log "Preparing Postgres"
service postgresql start >/dev/null 2>&1 || pg_ctlcluster 16 main start || log "WARN: Postgres did not start"
for _ in $(seq 1 20); do pg_isready -q && break; sleep 1; done
su postgres -c "psql -tAc \"SELECT 1 FROM pg_roles WHERE rolname='shoreline'\"" | grep -q 1 \
  || su postgres -c "psql -c \"CREATE ROLE shoreline LOGIN SUPERUSER PASSWORD 'shoreline'\""
for db in shoreline shoreline_testing; do
  su postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='${db}'\"" | grep -q 1 \
    || su postgres -c "createdb -O shoreline ${db}"
done
# Stop cleanly so the cached snapshot has a consistent data directory.
service postgresql stop >/dev/null 2>&1 || pg_ctlcluster 16 main stop || true

# --- Playwright (Pest 5 browser tests): OS libraries + Chromium in a shared path ---
export PLAYWRIGHT_BROWSERS_PATH=/opt/ms-playwright
mkdir -p "$PLAYWRIGHT_BROWSERS_PATH"
log "Installing Playwright Chromium + system deps"
npx -y playwright@latest install --with-deps chromium || log "WARN: Playwright install failed (check network allowlist)"
chmod -R a+rwX "$PLAYWRIGHT_BROWSERS_PATH" || true

log "Done: $(php -v 2>/dev/null | head -1)"
exit 0
