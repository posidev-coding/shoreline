# Claude Code cloud environment: `Shoreline`

How the `Shoreline` environment at claude.ai/code is set up. Cloud sessions run on a fresh Ubuntu 24.04 VM. The image ships PHP 8.3, Node 22, and Postgres 16 (installed but stopped).

Setup happens in two places:

| Where | What it does | When it runs |
|---|---|---|
| **Setup script**, pasted from [`setup-script.sh`](setup-script.sh) into the environment | Installs PHP 8.5, updates Composer, creates the Postgres role and databases, and installs Playwright Chromium with its system libraries | Once per cache build. The result is cached for about 7 days, or until the script or the network list changes |
| **SessionStart hook**, [`../hooks/cloud-session-start.sh`](../hooks/cloud-session-start.sh) wired in [`../settings.json`](../settings.json) | Starts Postgres, runs `composer install` and `npm ci`, matches Playwright Chromium to the project's version, and creates `.env` | Every cloud session start and resume. It does nothing locally because it checks `CLAUDE_CODE_REMOTE` |

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

`.env.example` should default to these values, so `cp .env.example .env` works in both places. `phpunit.xml` should set `DB_DATABASE=shoreline_testing` with `force="true"`.

## Gotchas in cloud sessions

- **`gh pr` and `gh issue` return 403.** The GitHub proxy blocks GraphQL. Use `gh api` (REST) instead, or open the PR from the session's diff view.
- **Services stop when the VM is reclaimed after idling.** The hook restarts Postgres on resume. Restart any dev server yourself (`php artisan serve`).
- **Commands are capped:** foreground commands at 2–10 minutes, background commands about 30 more. Run long test suites in the background.
- **Herd, `shoreline.test`, and `herd share` don't exist in the cloud.** Use `php artisan serve`, and leave OAuth and webhook tunnels to the local Mac.
- **Postgres version:** the cloud image runs 16, while Laravel Cloud production defaults to newer. Avoid features newer than Postgres 16.

## Check it's working

In a new cloud session, ask Claude to run:

```bash
php -v && composer -V && node -v && pg_isready && psql "postgresql://shoreline:shoreline@127.0.0.1/shoreline" -c "select version()" && ls /opt/ms-playwright
```
