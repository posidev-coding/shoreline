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
| **App cluster** | A current Flex size, not a legacy one, with Scale to Zero on. Current Flex sizes wake in under 500 ms; legacy ones take 5 to 20 s, and the edge doesn't cache pages to hide that |
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

> **Before onboarding a domain to Email Sending, authenticate the mail it already sends.** Onboarding adds a DMARC record on the root domain, `_dmarc` with `v=DMARC1; p=reject;`, which tells Gmail, Yahoo, and Outlook to refuse any mail from the domain that doesn't pass SPF or DKIM. The owner's mailbox is on Google Workspace, so the domain needs both of these first:
>
> - **SPF**, a TXT record on `@`: `v=spf1 include:_spf.google.com ~all`. Leave Cloudflare out of it: Email Sending sends from `cf-bounce.shorelinedoors.com`, which has its own SPF record.
> - **DKIM** from Google: in admin.google.com, **Apps → Google Workspace → Gmail → Authenticate email**, generate a key, add it as a TXT record named `google._domainkey`, then select **Start authentication**.
>
> Afterwards, send from the mailbox to a Gmail address and check **Show original**: SPF, DKIM, and DMARC should all say PASS. Without this, the owner's own mail bounces with `550 5.7.26 Unauthenticated email from shorelinedoors.com is not accepted due to domain's DMARC policy`. That happened in October 2026: onboarding in M0 published the reject policy while the domain had neither record, and his mail to Gmail bounced for about five days until the SPF record went in.

From M2, the quote form uses Cloudflare Turnstile. In the Cloudflare dashboard, add a Turnstile widget (Managed mode) whose hostnames are the environment's domains, then set:

```ini
TURNSTILE_SITE_KEY=<the widget's site key>
```

Put `TURNSTILE_SECRET_KEY` in the Secrets Manager. Without both, the form still works, guarded only by its honeypot and hourly limit, and the dashboard's launch checklist says the check is off.

From M5, QuickBooks. In the Intuit Developer app, list `https://<the environment's domain>/admin/quickbooks/callback` under the keys' **Redirect URIs** (staging uses the Development keys and the sandbox; production waits for Intuit's questionnaire), then set:

```ini
QUICKBOOKS_CLIENT_ID=<the keys' client ID>
QUICKBOOKS_ENVIRONMENT=sandbox
```

Put `QUICKBOOKS_CLIENT_SECRET` in the Secrets Manager, and redeploy. Then a super admin opens **Settings → QuickBooks** in `/admin` and connects. The page shows the exact redirect URI the app sends, which must match the one in the Intuit app. Development keys connect only to a sandbox company owned by the same Intuit developer account (**My Hub → Sandboxes** on developer.intuit.com); signing in as anyone else ends with Intuit's "There is no sandbox companies found for the user".

On a sandbox connection, run `php artisan app:quickbooks-sandbox-test` from the Commands tab to answer PLAN.md's open QuickBooks questions.

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

The INFO rows answer PLAN.md's Cloud spike: whether imagick decodes HEIC (it decodes a sample, since listing the format proves nothing), whether `pg_trgm` is available, and whether outbound 465 is open. Copy them into PLAN.md's "Unverified" section.

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

4. Check that a sleeping environment answers quickly. Send it no traffic for longer than its sleep timeout, so it scales to zero, then run:

   ```sh
   curl -s -o /dev/null -w '%{http_code} %{time_starttransfer}s\n' -u user:password https://<staging-host>/services
   ```

   Expect `200` in about a second. Five seconds or more means the App cluster runs a legacy Flex size, so switch it to a current one.

Laravel Cloud's edge caches only static files, not pages (`cf-cache-status: DYNAMIC` on a page is expected), so an edit in `/admin` shows on the site right away.

## 7. After the M2 deploy

1. Run `php artisan app:cloud-smoke` again. The Imagick row should say **decoded a HEIC photo**. If it says **can’t decode HEIC**, Cloud's libheif lacks its HEVC decoder: iPhone photos still arrive, but stay HEIC (Safari shows them, other browsers download them). Tell Claude the output.
2. Create the owner's account with `php artisan app:create-admin <email> --owner`. Until one exists, new-lead alerts go to the super admins.
3. From an iPhone, send a real quote request with two or three photos, at least one taken by the camera (HEIC). Then check:
   - the owner's alert email arrives, and replying to it goes to the address you entered;
   - the confirmation email arrives at that address;
   - in `/admin`, under **Quote requests**, the photos show as thumbnails;
   - under **Where they came from**, the IP address is your phone's public address, not a Cloudflare one. If it's Cloudflare's, the hourly limit counts many visitors as one.

## Not yet covered

- **The WAF letting Intuit's webhook POSTs through.** It's the plan's other Cloud spike item, checked in M5, when webhooks exist.
