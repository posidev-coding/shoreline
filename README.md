# Shoreline Doors

The website, back office, client portal, and QuickBooks Online integration for Shoreline Doors (shorelinedoors.com).

- [PLAN.md](PLAN.md): scope, data model, and milestones
- [CLAUDE.md](CLAUDE.md): conventions and commands
- [.claude/cloud/README.md](.claude/cloud/README.md): the Claude Code cloud environment
- [docs/laravel-cloud.md](docs/laravel-cloud.md): setting up Laravel Cloud environments

## Local setup (Mac)

1. In DBngin, start Postgres and create the `shoreline` role (password `shoreline`) and the `shoreline` and `shoreline_testing` databases.
2. `composer setup`, then `php artisan migrate:fresh --seed` for the site copy, demo jobs and reviews, and a local `/admin` login (`admin@example.com` / `password`). The first sign-in asks you to set up an authenticator app.
3. `npx playwright install chromium` once, for the browser tests.
4. Open `http://shoreline.test` in Herd, and set `APP_URL` to match in `.env`.
5. `composer test` runs the whole suite.
