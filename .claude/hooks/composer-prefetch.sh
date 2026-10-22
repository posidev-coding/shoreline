#!/bin/bash
# Seeds Composer's dist cache from composer.lock without GitHub's archive endpoints.
#
# In cloud sessions the GitHub proxy returns 403 for zipball/codeload downloads, and a
# git source install clones every package's full history (about 7 GB and half an hour for
# this app). So fetch only each locked commit (depth 1), `git archive` it into a zip, which
# is what GitHub's zipball is, and store it where Composer looks for that dist URL:
# <cache-files-dir>/<package>/<sha1(dist url)>.zip. `composer install --prefer-install=dist`
# then installs from the cache. A package this can't seed makes that install fail, and the
# caller falls back to a source install for what's left.
#
# Usage: composer-prefetch.sh [composer.lock]
set -uo pipefail

lock="${1:-composer.lock}"
[ -f "$lock" ] || exit 0
files_dir="$(composer config --global cache-files-dir 2>/dev/null)" || exit 0
WORK="$(mktemp -d)"
export WORK
trap 'rm -rf "$WORK"' EXIT

# One line per package: git url, commit, package name, cache file. Dist-only packages
# (phpstan/phpstan has no git source) use the repo their GitHub zipball URL names, so they
# install too, which a source install can't do. Packages with a published checksum are
# skipped, since a locally built zip can't match it.
php -r '
  $lock = json_decode(file_get_contents($argv[1]), true);
  foreach (array_merge($lock["packages"] ?? [], $lock["packages-dev"] ?? []) as $p) {
    $src = $p["source"] ?? []; $dist = $p["dist"] ?? [];
    if (($dist["type"] ?? "") !== "zip" || empty($dist["url"]) || empty($dist["reference"]) || !empty($dist["shasum"])) continue;
    $url = ($src["type"] ?? "") === "git" ? $src["url"] : null;
    if ($url === null && preg_match("{^https://api\.github\.com/repos/([^/]+/[^/]+)/zipball/}", $dist["url"], $m)) $url = "https://github.com/$m[1].git";
    if ($url === null) continue;
    echo $url, " ", $dist["reference"], " ", $p["name"], " ", $argv[2], "/", $p["name"], "/", sha1($dist["url"]), ".zip\n";
  }
' "$lock" "$files_dir" | xargs -r -P 8 -n 4 bash -c '
  url=$1 ref=$2 name=$3 zip=$4
  [ -s "$zip" ] && exit 0
  repo="$WORK/${name//\//--}.git"
  git init -q --bare "$repo" || exit 0
  for attempt in 1 2 3; do
    git -C "$repo" fetch -q --depth=1 "$url" "$ref" 2>/dev/null && break
    [ "$attempt" = 3 ] && { echo "composer-prefetch: could not fetch $name" >&2; rm -rf "$repo"; exit 0; }
    sleep $((attempt * 2))
  done
  mkdir -p "$(dirname "$zip")" \
    && git -C "$repo" archive --format=zip --prefix="${name//\//-}/" -o "$zip.part" "$ref" \
    && mv "$zip.part" "$zip" \
    || { rm -f "$zip.part"; echo "composer-prefetch: could not archive $name" >&2; }
  rm -rf "$repo"
' _

exit 0
