# Laravel Cloud environments

How to set up the Laravel Cloud **staging** environment. Production (M8) follows the same steps, plus the domain. Everything here happens in the Laravel Cloud dashboard. It's outward-facing, so Taylor does it, or approves it. Cloud docs: https://laravel.com/cloud/docs/llms.txt

## 1. Application and environment

- **Plan:** Growth. Monitoring is Cloud logs only, and Starter keeps logs for just 1 day. Growth also adds HTTP basic auth for staging and WAF rate limiting.
- **Application:** create it from the GitHub repo `posidev-coding/shoreline`, in a US East region.
- **Environment:** `staging`, on the `main` branch. PHP **8.5** (`composer.json` requires `^8.5`) and Node 22.
- **Security:** turn on HTTP basic auth, so bots don't wake the environment or find the site before launch.

## 2. Build and deploy commands

Build commands:

```sh
composer install --no-dev
npm ci --audit false
npm run build
php artisan optimize
```

`composer install` also republishes Filament's assets (through the `filament:upgrade` script), which is why `public/css/filament` and the similar folders aren't committed.

Deploy commands:

```sh
php artisan migrate --force
```

Don't add `storage:link`, `queue:restart`, or `optimize:clear`. Cloud either handles them or discards what they do.

## 3. Resources (infrastructure canvas)

| Resource | Settings |
|---|---|
| **Database** | Laravel Serverless Postgres, in the same region. Cloud injects `DB_*` |
| **Bucket 1** | Laravel Object Storage, visibility **Private**, disk name **`private`**, **default disk** on. Jurisdiction United States |
| **Bucket 2** | Laravel Object Storage, visibility **Public**, disk name **`public_media`**. Jurisdiction United States |
| **Managed queue** | Standard (not FIFO), **Flex**, **1 GiB**, autoscaling 0 to 3 workers. Cloud sets `QUEUE_CONNECTION=cloud` |
| **Scheduler** | Turn the Scheduler toggle on for the App cluster |
| **Cache** | None. Cache and sessions use the database |

The disk names must match exactly. Laravel replaces the `private` and `public_media` disks in `config/filesystems.php` with the buckets attached under those names.

Redeploy after you attach resources, because their environment variables only reach a new deployment.

## 4. Environment variables

Cloud injects the database, bucket, and queue variables. Add these:

```ini
APP_NAME="Shoreline Doors"
CACHE_STORE=database
SESSION_DRIVER=database
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.mx.cloudflare.net
MAIL_PORT=465
MAIL_USERNAME=api_token
MAIL_FROM_ADDRESS="hello@shorelinedoors.com"
MAIL_FROM_NAME="${APP_NAME}"
```

`MAIL_PASSWORD` is a Cloudflare API token with Email Sending permission. Store it in Cloud's **Secrets Manager**, not as a plain variable. Cloudflare only sends from a domain that's onboarded to Email Sending, so `shorelinedoors.com` has to be on Cloudflare first.

Don't set `DB_*`, `AWS_*`, `FILESYSTEM_DISK`, or `QUEUE_CONNECTION` by hand: custom variables override the injected ones.

## 5. Check that M0 is done

In the environment's **Commands** tab, run:

```sh
php artisan app:cloud-smoke --mail=you@example.com
```

It exits non-zero unless every required row passes:

| Row | Proves |
|---|---|
| Storage: private | A file lands in the private R2 bucket, reads back, and is served by a temporary URL |
| Storage: public_media | The same for the public bucket, through its public URL |
| Queue | The managed queue woke a worker and ran a job |
| Mail | That worker sent mail through Cloudflare SMTP on port 465 (then check the inbox) |

The INFO rows answer PLAN.md's Cloud spike: whether imagick reads HEIC, whether `pg_trgm` is available, and whether outbound 465 is open. Copy them into PLAN.md's "Unverified" section.

Staging confirmed in M0 that Cloud passes the public bucket's URL through to `public_media`, so don't set `AWS_URL`, despite what Cloud's object storage docs say. That advice applies to apps that read the generic `AWS_*` variables.

## 6. After the first M1 deploy

Run these once per environment, in the **Commands** tab.

1. Seed the starting site copy (services, towns, FAQs, and the about, hurricane, privacy, and terms pages):

   ```sh
   php artisan db:seed --class=ContentSeeder --force
   ```

   It only adds what’s missing and never overwrites an edit, but it would bring back a service or town the owner deleted, so it isn’t a deploy command. It seeds no reviews or jobs: those must be real.

2. Create the first admin:

   ```sh
   php artisan app:create-admin you@example.com --name="Your Name"
   ```

   Add `--owner` for the owner's account; without it the account is a super admin. The command emails a link to choose a password (it expires in 60 minutes, and the queue sends it). The first sign-in then requires setting up an authenticator app.

3. Fill in **Settings → Business details** in `/admin`. The dashboard's launch checklist lists what's still a placeholder, including the legal pages marked DRAFT.

4. Check that the edge caches content pages. Run this twice, a few seconds apart:

   ```sh
   curl -sI -u user:password https://<staging-host>/services | grep -iE 'cache-control|cf-cache-status|set-cookie'
   ```

   Expect `cache-control: public, s-maxage=600, stale-while-revalidate=86400`, no `set-cookie`, and `cf-cache-status: HIT` the second time. Nothing local can prove the HIT: if it says `DYNAMIC` or `BYPASS`, staging's basic auth or a Cloud setting is the cause, and it needs working out before production.

Once caching works, an edit in `/admin` can take up to 10 minutes to show on the site.

## Not yet covered

- **Livewire temporary uploads to S3, and the WAF letting Intuit's webhook POSTs through.** These are the plan's other Cloud spike items, and they're checked in M2 and M5, when those features exist.
