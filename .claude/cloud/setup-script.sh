#!/bin/bash
# Shoreline Doors: Claude Code cloud environment setup script.
#
# Paste this whole file into the "Setup script" field of the `Shoreline`
# environment at claude.ai/code. It runs once as root on Ubuntu 24.04 before
# Claude starts, and the resulting filesystem is cached (~7 days, or until this
# script or the network allowlist changes). Keep it under ~5 minutes. It exits
# non-zero only when PHP 8.5 or a required extension is missing, since a session
# without them can't run the app; every other failure is logged as a WARN.
#
# Services don't survive the cache, so .claude/hooks/cloud-session-start.sh
# starts Postgres and installs project dependencies on every session.

set -uo pipefail
export DEBIAN_FRONTEND=noninteractive

log() { echo "[shoreline-setup] $*"; }

# --- PHP 8.5 (image ships 8.3; Pest 5 / activitylog v5 / sitemap 8 need 8.4+) ---
# Check extensions, not just the binary: an optional package (pcov) pulls in
# php8.5-cli on its own, so a failed core install still leaves `php8.5` on PATH.
PHP_EXTS="pdo_pgsql pgsql mbstring dom xml curl zip intl bcmath gd pdo_sqlite"
PHP_PKGS="php8.5-cli php8.5-common php8.5-readline php8.5-pgsql php8.5-sqlite3 php8.5-mbstring
  php8.5-xml php8.5-curl php8.5-zip php8.5-intl php8.5-bcmath php8.5-gd"
php_ok() {
  command -v php8.5 >/dev/null 2>&1 || return 1
  for e in $PHP_EXTS; do php8.5 -r "exit(extension_loaded('$e') ? 0 : 1);" || return 1; done
}
APT="apt-get -o Acquire::Retries=5 -o DPkg::Lock::Timeout=300"

# ppa.launchpadcontent.net answers about half of all requests with a 503, and a
# single .deb that runs out of apt's retries fails the whole install. So fetch
# the .debs into apt's cache first: in parallel, curl retrying each file, and
# renamed into place only when complete. apt then installs from the cache and
# downloads (with its own retries) only what curl missed.
prefetch_debs() {
  $APT install -y --no-install-recommends --print-uris "$@" 2>/dev/null \
    | awk -F"'" '/^.http/ { split($3, f, " "); print $2, "/var/cache/apt/archives/" f[1] }' \
    | xargs -r -P 8 -n 2 sh -c 'curl -fsSL --retry 20 --retry-delay 2 --retry-all-errors -o "$1.part" "$0" \
        && mv "$1.part" "$1" || { rm -f "$1.part"; echo "[shoreline-setup] WARN: prefetch failed: $0"; }'
}
apt_install() {
  local pass
  prefetch_debs "$@"
  for pass in 1 2 3; do
    $APT install -y --no-install-recommends "$@" && return 0
    log "apt install pass ${pass} failed; retrying in 15s"
    sleep 15
  done
  return 1
}

if ! php_ok; then
  log "Installing PHP 8.5 from ppa:ondrej/php"
  codename="$(. /etc/os-release && echo "$VERSION_CODENAME")"
  curl -fsSL --retry 5 --retry-all-errors \
    "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C" \
    | gpg --dearmor --yes -o /usr/share/keyrings/ondrej-php.gpg
  echo "deb [signed-by=/usr/share/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu ${codename} main" \
    > /etc/apt/sources.list.d/ondrej-php.list
  # `apt-get update` only warns when an index 503s, so check the PPA's index actually loaded.
  for pass in 1 2 3 4 5; do
    $APT update -y && apt-cache show php8.5-cli >/dev/null 2>&1 && break
    log "apt update pass ${pass} didn't load the PPA index; retrying in 10s"
    sleep 10
  done
  # shellcheck disable=SC2086 # word-split the package list on purpose
  apt_install $PHP_PKGS || log "ERROR: PHP 8.5 core install failed"
  # Optional extensions go after the core, so apt satisfies their phpapi-* dependency
  # with php8.5-cli instead of pulling in php8.5-phpdbg. Never block the session on them.
  if php_ok; then
    for pkg in php8.5-imagick php8.5-pcov; do
      { apt-cache show "$pkg" >/dev/null 2>&1 && apt_install "$pkg"; } || log "WARN: $pkg unavailable"
    done
  fi
fi
php_ok || { log "FATAL: PHP 8.5 or a required extension is missing (need: ${PHP_EXTS})"; exit 1; }

if command -v php8.5 >/dev/null 2>&1; then
  update-alternatives --set php /usr/bin/php8.5 >/dev/null 2>&1 || true
  # The image's own PHP may sit earlier on PATH; make `php` mean 8.5 everywhere.
  ln -sf /usr/bin/php8.5 /usr/local/bin/php
fi

# --- Composer (preinstalled; just bring it current) ---
composer self-update --2 >/dev/null 2>&1 || log "WARN: composer self-update skipped"
# Cloud sessions' GitHub proxy 403s zipball downloads (api.github.com, codeload)
# for repos outside the session, so dist installs fail; public git clones work.
composer config --global preferred-install source

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
# Pinned so cache rebuilds don't drift: 1.64.1 ships Chromium build 1248. Bump it together
# with package.json's "playwright" and PLAYWRIGHT_VERSION in .claude/hooks/cloud-session-start.sh.
PLAYWRIGHT_VERSION=1.64.1
export PLAYWRIGHT_BROWSERS_PATH=/opt/ms-playwright
mkdir -p "$PLAYWRIGHT_BROWSERS_PATH"
log "Installing Playwright ${PLAYWRIGHT_VERSION} Chromium + system deps"
npx -y "playwright@${PLAYWRIGHT_VERSION}" install --with-deps chromium || log "WARN: Playwright install failed (check network allowlist)"
chmod -R a+rwX "$PLAYWRIGHT_BROWSERS_PATH" || true

log "Done: $(php -v 2>/dev/null | head -1)"
exit 0
