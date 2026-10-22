# Claude Code cloud environment: `Shoreline`

How the `Shoreline` environment at claude.ai/code is set up. Cloud sessions run on a fresh Ubuntu 24.04 VM. The image ships PHP 8.3, Node 22, and Postgres 16 (installed but stopped).

Setup happens in two places:

| Where | What it does | When it runs |
|---|---|---|
| **Setup script**, pasted from [`setup-script.sh`](setup-script.sh) into the environment | Installs PHP 8.5 and its extensions (with retries), updates Composer and sets it to install from git source, creates the Postgres role and databases, and installs Playwright 1.64.1's Chromium with its system libraries. Fails the cache build if PHP 8.5 or a required extension is missing | Once per cache build. The result is cached for about 7 days, or until the script or the network list changes |
| **SessionStart hook**, [`../hooks/cloud-session-start.sh`](../hooks/cloud-session-start.sh) wired in [`../settings.json`](../settings.json) | Starts Postgres; seeds Composer's dist cache from `composer.lock` ([`../hooks/composer-prefetch.sh`](../hooks/composer-prefetch.sh)) and installs from it; runs `npm ci` and `npm run build`; matches Playwright Chromium to the project's version; creates `.env`; migrates the dev database, seeding it the first time. Its status line warns when PHP extensions are missing, Composer, npm, or migrations failed, or the project's Playwright isn't the version the setup script cached. From a cold VM it takes about 45 seconds | Every cloud session start and resume. It does nothing locally because it checks `CLAUDE_CODE_REMOTE` |

## Environment settings (claude.ai/code → environment selector → `Shoreline` → edit)

**Network access:** set it to **Custom**, check **"Also include default list of common package managers"**, and add these allowed domains:

```text
ppa.launchpadcontent.net
keyserver.ubuntu.com
getcomposer.org
cdn.playwright.dev
*.playwright.dev
playwright.download.prss.microsoft.com
playwright.azureedge.net
boost.laravel.com
laravel.com
filamentphp.com
livewire.laravel.com
challenges.cloudflare.com
developer.intuit.com
static.developer.intuit.com
developers.cloudflare.com
```

The default list already covers Packagist, the npm registry, GitHub, and the Ubuntu archives. The extra domains are for:
- PHP 8.5: the ondrej PPA and its signing key
- Composer self-update
- Playwright browser downloads
- Laravel Boost docs search
- Cloudflare Turnstile in browser tests
- Package and vendor docs

Add these later, if a cloud session ever needs to call the QuickBooks sandbox live (M5), rather than using `Http::fake`:

```text
sandbox-quickbooks.api.intuit.com
oauth.platform.intuit.com
appcenter.intuit.com
```

**Environment variables:** use `.env` format. None of these are secret, and anyone using the environment can read them.

```text
COMPOSER_ALLOW_SUPERUSER=1
COMPOSER_NO_INTERACTION=1
PLAYWRIGHT_BROWSERS_PATH=/opt/ms-playwright
```

`DB_*` is deliberately left out. Real environment variables override `.env`, and they'd also beat `phpunit.xml`'s `<env>` entries unless those have `force="true"`. That could point tests at the dev database.

**Setup script:** paste the entire contents of [`setup-script.sh`](setup-script.sh).

## Database

| | Local Mac (DBngin) | Cloud session |
|---|---|---|
| Host / port | `127.0.0.1:5432` | `127.0.0.1:5432` |
| Role | create `shoreline` / password `shoreline` | created by the setup script |
| Databases | `shoreline`, `shoreline_testing` | same, created by the setup script |

`.env.example` defaults to these values, so `cp .env.example .env` works in both places. `phpunit.xml` forces `DB_CONNECTION=pgsql` and `DB_DATABASE=shoreline_testing` with `force="true"`, so a real environment variable can't point tests at the dev database.

## Gotchas in cloud sessions

- **`gh pr` and `gh issue` return 403.** The GitHub proxy blocks GraphQL. Use `gh api` (REST) instead, or open the PR from the session's diff view.
- **Composer can't download zips from GitHub.** The GitHub proxy returns 403 for `api.github.com/repos/…/zipball` and `codeload.github.com` on any repo outside the session, which is every package. Composer then fails with "Could not authenticate against github.com" and doesn't fall back to git. Installing from git source instead doesn't work either: it cloned 7 GB of history in over 9 minutes for this app's 220 packages, and it can't install `phpstan/phpstan` at all, because that package is published dist-only. So the hook runs [`composer-prefetch.sh`](../hooks/composer-prefetch.sh) first. It fetches each locked commit at depth 1 over plain git, `git archive`s it into the zip GitHub would have served, and stores it where Composer's cache looks for that dist URL. Then `composer install --prefer-install=dist` installs everything from the cache: 30 seconds and 78 MB to seed, 8 seconds to install. Anything the prefetch misses falls back to a source install, which is why the setup script still sets `preferred-install source` globally.
  - To add or update a package in a session: `composer require --no-install …` (or `composer update --no-install …`), then `bash .claude/hooks/composer-prefetch.sh && composer install --prefer-install=dist`. A plain `composer require` installs that package from git source.
  - **Never set `preferred-install` in `composer.json`.** Project config overrides the global setting. Laravel's skeleton ships `"preferred-install": "dist"`, and M0 removed it.
  - The prefetch relies on Composer naming dist cache files `<package>/<sha1 of the dist URL>.zip`. If a Composer release changes that, installs fall back to git source and get slow. That's the sign to look here.
  - This affects only cloud sessions. The Mac and Laravel Cloud builds download dist zips normally.
- **The ondrej PPA is flaky.** In October 2026, `ppa.launchpadcontent.net` answered about half of all requests with a 503, at roughly 40 KB/s. A plain `apt-get install` failed the build whenever one `.deb` ran out of retries. So the setup script prefetches the `.deb`s into apt's cache with parallel, retrying `curl`, then installs from the cache. It also fails the build rather than leaving a PHP 8.5 with no extensions (`php -v` alone doesn't catch that). If it still fails, rebuilding usually works; the PPA has no mirror.
- **Playwright is pinned in three places.** `setup-script.sh` and the hook both set `PLAYWRIGHT_VERSION=1.64.1` (Chromium build 1248), and `package.json` pins `"playwright": "1.64.1"` exactly, with no `^`. pest-plugin-browser 5.1 requires at least 1.63.0. Bump all three together, then re-paste the setup script so the cache rebuilds. If they drift, every session start downloads another Chromium, which may lack system libraries, and the hook's status line warns.
- **Browser tests leave a `playwright run-server` process behind.** pest-plugin-browser starts it through `sh -c`, and when the run ends it stops the shell. On Ubuntu `/bin/sh` is dash, which doesn't `exec` into `node`, so `node` keeps running (the Mac's `/bin/sh` is bash, which does). The orphan holds stdout open, so `composer test | tail` never returns. Redirect test output to a file, and clean up with `pkill -f '[p]laywright run-server'`. The fix belongs upstream: the plugin should prefix the command with `exec` or pass it as an array.
- **A restored VM reads its disk lazily.** After the VM is reclaimed and restored, the first read of each file runs at about 80 MB/s (a 442 MB file took 5.4 s, then 0.17 s once read). The first headless Chromium launch used to outlast the browser plugin's 5 s timeout, so the hook reads Chromium's headless build once at session start (about 3 s cold).
- **Services stop when the VM is reclaimed after idling.** The hook restarts Postgres on resume. Restart any dev server yourself (`php artisan serve`).
- **Commands are capped:** foreground commands at 2–10 minutes, background commands about 30 more. Run long test suites in the background.
- **Herd, `shoreline.test`, and `herd share` don't exist in the cloud.** Use `php artisan serve`, and leave OAuth and webhook tunnels to the local Mac.
- **Postgres version:** the cloud image runs 16, while Laravel Cloud production defaults to newer. Avoid features newer than Postgres 16.

## Check it's working

In a new cloud session, ask Claude to run:

```bash
php -v && composer -V && node -v && pg_isready && psql "postgresql://shoreline:shoreline@127.0.0.1/shoreline" -c "select version()" && ls /opt/ms-playwright && php -r 'foreach (["pdo_pgsql","pgsql","mbstring","dom","xml","curl","zip","intl","bcmath","gd","pdo_sqlite"] as $e) if (!extension_loaded($e)) { fwrite(STDERR, "missing PHP extension: $e\n"); exit(1); } echo "PHP extensions OK\n";' && composer config --global preferred-install
```

The last two commands should print `PHP extensions OK` and `source`.
