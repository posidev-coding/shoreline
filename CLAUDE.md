# Shoreline Doors

Public site, back office, client portal, and QuickBooks Online integration for Shoreline Doors, a garage-door and odd-jobs business in Morehead City, NC. Hosted on Laravel Cloud.

**[PLAN.md](PLAN.md) is the source of truth** for scope, the data model, and the milestones (M0–M8). Work milestone by milestone, and re-read the relevant section before starting a piece of work.

## Stack

Laravel 13 · PHP 8.5 · Filament 5 at `/admin` · Livewire 4 · Tailwind 4 + Vite · Postgres 16 locally and in cloud sessions, Laravel Serverless Postgres in production · Pest 5 with the browser plugin (Playwright) · Larastan level 8 · Pint.

Several packages changed majors recently (Filament 5, Livewire 4, Pest 5, spatie/laravel-activitylog 5). Check the installed version's docs with Boost's `search-docs` before using an API.

## Commands

- `composer test`: every Pest suite (Unit, Feature, Browser), then Larastan, then `pint --test`. It must be green before every commit. `composer test -- --filter=Name` narrows the Pest run.
- `vendor/bin/pint --dirty --format agent` after editing PHP.
- `php artisan serve` in cloud sessions; Herd at `http://shoreline.test` on the Mac.
- `php artisan migrate:fresh --seed` seeds a local super admin: `admin@example.com` / `password`.
- `php artisan app:cloud-smoke --mail=you@example.com` on a Laravel Cloud environment (Commands tab) checks both buckets, the queue, and mail, and reports the Cloud spike items.

## Rules that aren't obvious from the code

- **Ask Taylor before anything outward-facing**: GitHub repo settings, Laravel Cloud resources, DNS, Intuit app settings, filing upstream issues.
- **Money** is `numeric(12,2)` (unit price uses more precision), and arithmetic goes through `brick/money`. **Tax is never computed locally**; QuickBooks computes it.
- Postgres conventions: string-backed PHP enums, `jsonb` (not `json`), a unique index on `lower(email)` with emails stored lowercase, soft deletes on clients, properties, and projects.
- **Files**: the default disk is `private` (client photos, quote uploads, documents, Livewire temp uploads), served only through `temporaryUrl()`. `public_media` holds gallery and site images. On Laravel Cloud each name is an R2 bucket. Never set per-file `visibility`: R2 rejects ACLs.
- **Mail** is always queued and carries links, not attachments.
- **Queue jobs** run on a Cloud Flex managed queue: finish in under 90 s, and keep delays and backoff at or under 900 s.
- **Scheduled tasks** run no more often than hourly, so the environment can scale to zero.
- `users.role` (`super_admin | owner | client`) is not mass-assignable. Only super admins and owners get into `/admin`.
- Tests run against the `shoreline_testing` Postgres database, which `phpunit.xml` forces. Don't switch tests to SQLite.

## Claude Code cloud sessions

See [.claude/cloud/README.md](.claude/cloud/README.md). The SessionStart hook starts Postgres and installs Composer and npm dependencies; its status line warns when something is missing.

- **Composer can't download dist zips from GitHub here** (the proxy returns 403). The hook seeds Composer's cache with `.claude/hooks/composer-prefetch.sh` and installs from it. When you add or update a package, run `composer require --no-install …` or `composer update --no-install …`, then `bash .claude/hooks/composer-prefetch.sh && composer install --prefer-install=dist`. A plain `composer require` falls back to git source for that package, which works but is slow. It can't install a dist-only package like `phpstan/phpstan` at all.
- Never add `preferred-install` to `composer.json`: project config overrides the session's global setting.
- **Browser tests leave a `playwright run-server` process behind on Linux** (pest-plugin-browser stops the `sh` wrapper, not `node`). Don't pipe test output into `tail` or `grep`, because the orphan holds the pipe open and the command hangs. Redirect to a file instead, and clean up with `pkill -f '[p]laywright run-server'`.
- `gh pr` and `gh issue` are blocked (GraphQL). Use `gh api`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
