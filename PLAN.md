# Shoreline Doors: shorelinedoors.com

## Context
Taylor's friend runs **Shoreline Doors**, a garage-door installation and residential odd-jobs business in **Morehead City, NC**. The service area covers three counties: Carteret, Onslow (Swansboro), and Craven (Havelock). He needs:
1. A standout public site that wins local search and turns visitors into quote requests.
2. A back office for clients, properties, and projects.
3. A client portal where customers follow their job.
4. QuickBooks Online integration. QBO is where he already writes estimates and keeps his books, so the app has to work alongside it.

The folder `/Users/taylorcox/Dev/Projects/shoreline` is empty (greenfield, not yet a git repo). The site will be hosted on **Laravel Cloud**.

**Decisions made with Taylor**
- Admin: Filament v5.
- QuickBooks: all four features (two-way customer sync, push estimates/invoices, status via webhooks, money dashboard). QB Payments is on, so the portal gets a real **Pay Now** button.
- Public features: quote request with photos, client portal, gallery, and curated reviews. No online booking and no Google Places API.
- Users: **Super Admin** (Taylor, for support), **Owner**, **Clients** (portal only). No crew role.
- Brand: designed from scratch. Email: Cloudflare Email Service. Monitoring: Laravel Cloud logs only, plus an in-app Sync Issues screen.
- Production scales to zero.
- Deposits: unknown ("sometimes people buy him a case of Busch Lattes"). Supported but never required.

## Stack (versions verified 2026-10-21)
| Layer | Choice |
|---|---|
| Framework | Laravel 13.x (≥ 13.19 is required for Cloud managed queues). PHP 8.5 via Herd locally |
| Admin | Filament 5.10 at `/admin`: brand theme, built-in MFA required for super admin and owner |
| Public site | Blade + Livewire 4 (Alpine comes bundled, so it isn't installed separately) + Tailwind 4 + Vite |
| Portal | Custom Livewire pages at `/portal`, on-brand, passwordless login |
| DB | Postgres: DBngin locally (already installed), Laravel Serverless Postgres in production |
| Files | Laravel Cloud Object Storage (R2) with **two buckets**. `private` is the default disk and holds client photos, quote uploads, documents, and Livewire temp uploads, served via `temporaryUrl`. `public_media` holds gallery and site imagery; `AWS_URL` has to be set manually for it |
| Queue | Cloud Managed Queue (`QUEUE_CONNECTION=cloud`), Flex, 1 GiB memory. Jobs must finish in < 90 s, and delays/backoff can't exceed 900 s |
| Mail | Cloudflare Email Sending over SMTP (`MAIL_SCHEME=smtps`, `smtp.mx.cloudflare.net:465`, user `api_token`), always queued. Emails carry links rather than attachments (5 MiB cap). Cloudflare Email Routing forwards inbound `hello@` to the owner |
| Tests/quality | **Pest 5** + pest-plugin-browser, Larastan 3, Pint, Laravel Boost (dev) |
| Analytics | Cloudflare Web Analytics |

## Roles and access
- `users.role`: `super_admin | owner | client` (a string-backed enum).
- `client_user` pivot with a role (`primary | manager | billing | viewer`). One property-management company can have several logins, and one person can belong to several accounts.
- Filament `canAccessPanel()` admits only super_admin and owner.
- Super-admin-only areas: Users, QuickBooks Integration, Sync Issues, Activity Log.
- Impersonation via `stechstudio/filament-impersonate` (→ owner, or → client with `redirectTo('/portal')` and its banner). Every impersonation is written to the activity log.
- Portal uses a `client` middleware, and policies scope every query through the pivot. ULIDs in portal URLs.

## Data model
Conventions:
- Money is `numeric(12,2)`; unit price uses higher precision; arithmetic goes through `brick/money`.
- Tax is never computed locally.
- String-backed PHP enums, jsonb columns, a unique index on `lower(email)`.
- Soft deletes on clients, properties, and projects.

### Operations
- **clients**: display_name, first/last name, company, email, phone (E.164), billing address, `is_property_manager`, `qb_mode` (`flat` | `sub_customer_per_property`), notes, plus the QB sync columns described below.
- **properties**:
  - client_id, label, address, county, service_area_id, lat/lng
  - type (primary / vacation rental / long-term rental / commercial)
  - encrypted access info (lockbox and gate codes), on-site or rental-manager contact
  - turnover constraints (e.g. "Saturday changeover")
  - optional QB sub-customer id
- **parts**: the catalog of everything he installs or sells, not just doors.
  - Categories: door models, openers, springs (torsion/extension), cables, drums, rollers, tracks, hinges, struts and wind braces, weather seal, keypads, remotes, safety sensors, smart controllers, misc hardware.
  - Fields:
    - manufacturer, model, part/SKU number
    - `specs` jsonb, e.g. spring wire size/inside diameter/length/wind, opener drive type/HP/compatibility
    - supplier_id, cost, price, warranty term
    - `catalog_item_id`, the QB Item it bills as
    - photo and spec-sheet media, active
  - Optional stock tracking: `track_stock`, `on_hand`, `reorder_point`, plus **part_stock_movements** (received / used on project / adjusted / returned).
  - QB inventory items need QBO Plus, so stock lives in the app and QB just bills the item.
- **suppliers**: name, rep contact, account number, website, notes.
- **installed_equipment** (what's installed at a property):
  - property_id, `type` (door / opener / spring set / hardware / keypad / remote / sensor / smart hub / other), `part_id` (nullable)
  - **`parent_id`** for nesting, e.g. springs and opener belong to a door
  - manufacturer, model, serial, specs
  - Door-only fields: size, material, design pressure (+/−) with a photo of the label, impact-rated
  - install date, `installed_by_project_id`, `warranty_expires_at`, `last_service_at`, `maintenance_due_at`
  - status (active / replaced / removed) with `replaced_by_id`
  - Drives salt-air maintenance reminders, warranty lookups ("what spring is on this door?"), and repeat work.
- **leads**:
  - service, details, urgency, contact info (normalized for dedupe), address, photos
  - attribution (utm_*, gclid, landing page), consent flags
  - status (new / contacted / converted / spam)
  - converted client, property, and project ids
- **projects**:
  - client_id, property_id, service_id, title
  - **`stage`**: `estimating → proposal_sent → approved → materials_ordered → scheduled → in_progress → completed → closed`, plus `on_hold`, `lost` (with `lost_reason`), and `cancelled`
  - **`billing_status`**, derived from invoices and payments: `unbilled | invoiced | partial | paid | overdue | void`
  - The kanban board shows `stage` only.
- **visits**:
  - project_id, type (site measure / install / service / warranty), arrival window
  - status (tentative / confirmed / done), client_notified
  - Visits feed the calendar and a signed ICS feed the owner can subscribe to on his phone.
- **project_line_items**:
  - The working draft: `part_id` (optional; picking a part fills description, rate, and QB item), catalog_item_id, description, qty, rate, TAX/NON.
  - **Ordering fields:** `needs_ordering`, supplier_id, `order_status` (to_order / ordered / received), `expected_at`. These drive the `materials_ordered` stage and a "Parts to order" view.
  - When a project is completed:
    - Stocked parts are deducted from stock.
    - The owner is prompted to record what was installed in installed_equipment, pre-filled from the line items.
- **project_tasks** (checklist), **project_updates** (timeline entries with a `visible_to_client` flag), **messages** (one portal thread per project, `last_read_at` per participant).
- **showcases**:
  - public before/after pairs: title, slug, **town only, never the street**, service, story, featured
  - photo-release consent flag; EXIF GPS is stripped
  - one-click "Publish to gallery" from a completed project
- **testimonials**: curated (quote, name, town, rating, source, featured). **review_requests** and **email_preferences** (for unsubscribes).
- **services, service_areas, faqs**: CMS content that drives the public pages.
- **settings** (`spatie/laravel-settings`): name/address/phone (kept consistent with the Google Business Profile), hours, license and insurance, Google review URL, social links.
- Activity log (`spatie/laravel-activitylog` **v5**; its API changed from v4) via `pxlrbt/filament-activity-log`.

### QuickBooks mirrors and sync infrastructure
- Every mirrored row carries `realm_id`, `qb_id` (unique on realm + id), `qb_sync_token`, `qb_last_updated_at`, `qb_payload` (jsonb).
- Two-way entities also carry `last_synced_hash` and `sync_state` (`clean | dirty | pending | conflict | error`).
- **catalog_items**: QB Items with type, parent, default taxable, IncomeAccountRef, active. Includes generic items (Labor, Materials, Trip charge, Haul-away). Pull-only in v1.
- **estimates** + **estimate_lines**: a snapshot of what was pushed, mirrored back from QB.
- **estimate_approvals**: user, typed name, IP, user agent, timestamp, SyncToken, total, terms version.
- **invoices**: balance, due date, void flag, `is_deposit`. **payments** + **payment_applications** (payment ↔ invoice amounts).
- **quickbooks_connections**:
  - environment, realm, company name
  - encrypted tokens, `access_expires_at` / `refresh_expires_at` / `refresh_hard_expires_at`
  - CDC cursor, cached preferences (AST on? custom txn numbers? Payments?), `disconnected_at`
  - A different realm is refused unless the super admin runs a "reset mappings" step.
- **quickbooks_outbox**: a transactional outbox for pushes. **quickbooks_webhook_events**: raw events, unique on event `id`.
- **quickbooks_sync_issues**: shown in Filament with Retry and Resolve. **quickbooks_sync_logs**: include Intuit's `intuit_tid` response header for support cases.

## Public website (make it kick ass)
**Brand:** "coastal workwear".
- Colors: deep navy `#0B1F33`, weathered sand `#E8DCC4`, salt white, safety-orange `#FF6B1A`.
- Type: a bold condensed display face (Big Shoulders Display or Barlow Condensed via `@fontsource`, self-hosted) with a clean sans for body text.
- An SVG wordmark (SHORELINE over a garage-door-panel horizon), a favicon set, and static OG images.

**Signature moments**
- **Door-rollup hero.** An SVG/CSS garage door rolls up to reveal the hero. It must not become the LCP element, and it renders static under `prefers-reduced-motion`.
  - Headline: **"Up, down & done right."**
  - Subhead: "Garage door installs, repairs, and the odd jobs around the house, from Morehead City to Emerald Isle."
  - Alternates: "Your garage door, handled." / "Stuck? We'll get it moving."
  - Headline and subhead are editable in Settings.
- **Before/after drag slider** on Our Work items.
- **Sticky mobile action bar:** Call now (`tel:`) and Get a quote.
- **"Is your door hurricane-ready?" quiz.** It feeds a prefilled quote request. Copy defers to the local building official and mentions DASMA wind-load ratings.

**Pages**
- Home
- Services index plus detail pages: Installation, Repair, Springs & Cables, Openers & Smart Controls, Hurricane/Wind-Rated Doors, Handyman & Home Repairs
- Our Work (filter by service and town)
- Service Areas index plus town pages: Morehead City, Beaufort, Atlantic Beach, Emerald Isle, Newport, Pine Knoll Shores, Cape Carteret, Swansboro, Havelock, Indian Beach. Each shows **unique local content** (that town's jobs and reviews), so the pages don't read as doorway pages.
- About, Reviews, FAQ, Hurricane-Ready, Get a Quote, Contact, Privacy and Terms. Privacy and Terms also serve as the Intuit EULA and privacy URLs, and the privacy policy covers Intuit data.
- All copy is seeded, and the owner edits it in Filament.

**Get a Quote**
- A multi-step Livewire wizard: service → details (size, style, symptoms) → photos (up to 6) → address/contact → review.
- **HEIC is converted in the browser** with `heic2any` before upload, because iOS Safari's own conversion is unreliable.
- Spam protection: Turnstile (`ryangjchandler/laravel-cloudflare-turnstile`), honeypot, and throttling.
- Each submission creates a Lead, emails the owner, raises a Filament database notification, and sends the customer an auto-reply.

**SEO and performance**
- JSON-LD: `HomeAndConstructionBusiness` with areaServed, `Service`, `BreadcrumbList`. FAQ and review schema are nice-to-have only, since Google restricts those rich results.
- The sitemap is served from a **route** built from models, not a file (Cloud's filesystem is ephemeral).
- Canonical URLs, per-page meta, responsive WebP from medialibrary conversions for public media only.
- Target Lighthouse ≥ 95 on mobile.

**Edge caching (offsets scale-to-zero):**
- Content routes (home, services, areas, work, FAQ) use a lean middleware group with no session or cookies.
- They send `Cache-Control: public, s-maxage=600, stale-while-revalidate`, so Laravel Cloud's edge serves most visitors without waking the app.
- The quote, portal, and admin routes keep the full `web` stack. Cloud purges the edge cache on every deploy.

## Admin (Filament v5)
- **Dashboard:**
  - KPIs: new leads this week, active projects, visits this week, plus revenue MTD and outstanding A/R from QB
  - Today/upcoming visits, lead inbox
  - Low-stock parts and parts awaiting delivery
  - 12-month revenue chart
  - Unpaid invoices with a "Send reminder" action (QB `/send`)
- **Project board:** kanban by stage (`relaticle/flowforge` 4.x). **Schedule:** calendar of visits (`guava/calendar` 3.x; fallback `saade/filament-fullcalendar` 4.x).
- **Resources:**
  - Leads, with "Convert to client + property + project", which de-dupes on email/phone
  - Clients, with relation managers for Properties, Projects, Portal Users, Estimates, Invoices, plus "Invite to portal"
  - Properties, with an Installed Equipment relation manager shown as a tree (door → opener, springs, hardware)
  - Parts, filterable by category, manufacturer, and low stock. Actions: receive stock, adjust stock, view movement history, view "where installed"
  - Suppliers
  - "Parts to order": a page listing every line item marked `to_order`, grouped by supplier, with bulk "Mark ordered" and "Mark received" actions
  - Projects:
    - line items, photos (upload from a phone on site), visits, tasks, timeline, messages
    - actions: "Create Estimate in QB", "Create Invoice in QB", "Create deposit invoice", "Publish to gallery"
  - Estimates, Invoices, Payments (mirrors with actions), Catalog Items
  - Showcases, Testimonials, Services, Service Areas, FAQs, Settings
  - Super admin only: Users, QuickBooks Integration, Sync Issues, Activity Log
- Mobile-friendly. Global search across clients, properties (by address), and projects.

## Client portal (`/portal`)
- **Login:** an email that carries both a magic link and a 6-digit code (`spatie/laravel-one-time-passwords`).
  - The link opens a **confirm page that POSTs**, so mail scanners (Outlook Safe Links, Gmail prefetch) can't burn the token.
  - Tokens are stored hashed and live 15 minutes. Requests are throttled by email and by IP, and the response is the same whether or not the email exists.
  - The owner starts it with "Invite to portal".
- **Dashboard:** active projects, next visit, balance due.
- **Project page:**
  - Client-visible timeline, photos, documents, message thread. No progress tracker for now; it's excluded by request.
  - **Estimate card:** PDF (proxied from QB) plus **Approve**. Approval needs a typed name, and is accepted only if the estimate hasn't changed since the page was rendered (same SyncToken) and hasn't expired.
  - **Invoice card:** PDF plus **Pay Now**. A redirect route fetches `invoiceLink` fresh on each click; the link is never stored.
- **Profile:** contact edits go through the customer sync to QB.
- **Notification emails:** new message, estimate ready, visit scheduled or confirmed, job complete. Each has an unsubscribe link where CAN-SPAM requires one.

## QuickBooks Online integration
**App setup**
- Intuit Developer app with sandbox now and production keys after the questionnaire. Scope `com.intuit.quickbooks.accounting`.
- Required URLs: host domain, launch, disconnect, connect/reconnect, EULA, and privacy. All are app routes.
- Laravel Cloud's outbound IPs come from `cloud.laravel.com/api/ip`.

**Client and tokens** (`app/Services/QuickBooks/`)
- **`QuickBooksClient`:** a thin wrapper on Laravel's `Http` client (no SDK).
  - Sandbox/production base URL, pinned to `minorversion=75`.
  - Helpers: query (paginated with STARTPOSITION/MAXRESULTS), read, create, **sparse** update with SyncToken, cdc, report, pdf, send.
  - On error 5010 (stale object): refetch, rediff, retry once, then raise a Sync Issue.
- **`TokenManager`:**
  - Endpoints: authorize `appcenter.intuit.com/connect/oauth2`, token `oauth.platform.intuit.com/oauth2/v1/tokens/bearer`, revoke `developer.api.intuit.com/v2/oauth2/tokens/revoke`.
  - Refresh runs inside a database transaction with `lockForUpdate` on the connection row, re-checks expiry after taking the lock, and persists the rotated refresh token atomically.
  - Sends the `x-include-refresh-token-hard-expires-in: true` header and stores `x_refresh_token_hard_expires_in`.
  - Refreshes proactively once a day and alerts when the hard expiry is less than 60 days away.
  - On `invalid_grant`: mark the connection disconnected and email the super admin.
- **Throttling:**
  - `RateLimited` job middleware enforces 10 requests/second per realm.
  - A 429 releases the job for 60 s.
  - Backoff never exceeds 900 s.

**Customer sync (two-way)**
- **Outbound:**
  - Observers write a `quickbooks_outbox` row in the same transaction as the change; a job dispatches after commit.
  - Echo guard: skip when the hash of the QB-mapped fields equals `last_synced_hash`, and when an inbound-sync flag is set via `Context`. Not `saveQuietly`, because that would also silence the activity log.
  - A per-entity `WithoutOverlapping` lock keeps a push and a pull of the same record from interleaving.
- **Ownership:** QB owns DisplayName, CompanyName, BillAddr, billing email, terms, and tax exemption. The app owns portal users, properties, and preferences. Phone is shared: the newer edit wins, and edits on both sides create a conflict issue.
- **QB quirks:**
  - **DisplayName must be unique** across customers, vendors, and employees (error 6240). Apply a deterministic suffix and raise an issue.
  - Deleting a customer in QB only sets `Active=false`. Mirror it as inactive, never delete it.
  - **Merges:** `data.deletedid` names the removed record. Re-point the mapping, or create a merge suggestion if both sides exist locally.
- **Sub-customers:** QB sub-customers use `Job`/`ParentRef`, and QBO Projects are flagged `IsProject`. Each client picks one mode:
  - `flat` (default): the property address goes in ShipAddr.
  - `sub_customer_per_property`: for property managers.
- **First-connect wizard:**
  - Import QB customers one page per job, staying under the 90 s cap.
  - Auto-match on email, phone, or name; the owner reviews whatever's left.

**Items, estimates, invoices, tax**
- Items are pulled from QB. Every line carries an ItemRef (Service or NonInventory; no Category or Group items in v1).
- Estimates are pushed from the draft line items.
  - Every line is marked TAX or NON from the catalog default, and the owner can override it.
  - **ShipAddr is set to the property address** so QB's Automated Sales Tax picks the right county rate.
  - Totals and `TaxLine` are read back and labeled "tax calculated by QuickBooks."
  - Lines are owned by the app until the estimate is sent. After that, re-pushing shows a confirmation diff, because QB replaces the whole line array on update.
  - If the company uses custom transaction numbers, we supply DocNumber ourselves.
- **NC sales tax:** repair/maintenance/installation (RMI) services are taxed differently from real-property contracts, and NCDOR SD-13-5 uses a garage-door example. **His CPA must sign off on the item/tax mapping.**
- Invoices are created from an accepted estimate. The API allows **only one linked invoice per estimate** and no progress invoicing, so:
  - An optional **deposit invoice** is a separate, unlinked invoice using a "Deposit" item (the CPA picks the account).
  - The final invoice is linked to the estimate and carries a negative "Less deposit" line.

**Inbound: webhooks + CDC**
- `POST /webhooks/quickbooks`:
  - Stateless (no session, CSRF-exempt).
  - Verifies the `intuit-signature` header (base64 HMAC-SHA256 of `$request->getContent()`, keyed with the verifier token) using `hash_equals`.
  - Drops events whose `intuitaccountid` isn't the connected realm, inserts each CloudEvent (unique on `id`), and returns 200 immediately, unknown types included.
  - Then dispatches **one unique job per entity** that always fetches the latest version, so arrival order doesn't matter.
- **Subscribed entities:** Customer, Item, Estimate, Invoice, Payment, CreditMemo, RefundReceipt.
- **Payment events:**
  1. Fetch the payment.
  2. Refetch every invoice it's applied to.
  3. Recompute `billing_status` (partial when 0 < Balance < Total; a voided invoice is not paid).
  4. Add a timeline entry, plus "Paid ✓" in the portal.
- **Estimate Accepted** (from the portal or from QB's own customer page) moves the stage to `approved`.
- Hard-deleted documents (error 610 or CDC `Deleted`) are soft-deleted locally, and the owner is alerted if they belonged to a project.
- **Hourly CDC sweep:**
  - Cursor is the server time minus an overlap window; the lookback is ≤ 30 days.
  - If a response hits the 1,000-object cap, shrink the window and re-run.
  - This is the safety net for scale-to-zero cold starts that miss Intuit's ~3 s webhook window. Intuit also retries on its own backoff.

**Money dashboard**
- ProfitAndLoss (MTD and YTD), AgedReceivables plus open invoices, and a monthly revenue trend, cached for 1 hour.
- Read volume stays far below the free Builder tier's 500k CorePlus calls per month.

## Background jobs and schedule
- **Queued (one managed queue):** mail, notifications, media conversions, QB outbox, pulls, webhook processing, PDF proxy caching.
- **Scheduled** (Cloud scheduler toggle; nothing more often than hourly, so the environment can sleep):
  - **Hourly:** CDC sweep.
  - **Daily:**
    - QB token refresh and health check.
    - Review requests for projects completed ≥ 1 day ago. These go to every customer: no review gating, so as not to violate Google policy or the FTC rule.
    - Equipment maintenance reminders (from `installed_equipment.maintenance_due_at`).
    - Pruning of `livewire-tmp/`.
  - **Weekly:** prune old webhook events and logs.
  - **Seasonal:** a hurricane-season prep email in late May to opted-in clients.

## Security
- Encrypted casts on QB tokens and property access info.
- Webhook signature verification and realm filter.
- Throttling on quote, login, and webhook routes.
- Policies on every model; portal isolation goes through the pivot.
- Private files only through short-lived temporary URLs.
- Upload validation (mime, size, dimensions); EXIF GPS stripped from public images.
- Filament MFA, impersonation audit, activity log.
- Postgres point-in-time recovery on Cloud.

## Milestones (each one demoable)
**M0 Foundation**
- The repo already exists (`posidev-coding/shoreline`, branch `main`) and holds PLAN.md, `.gitignore`, and `.claude/`. **Don't run `laravel new` in the repo root, and never with `--force`**: it refuses a non-empty folder, and `--force` deletes the folder, `.git` included.
  - Instead, scaffold into a temp dir (`laravel new /tmp/shoreline-scaffold`: no starter kit, Pest, pgsql), then copy it in with `rsync -a --exclude .git /tmp/shoreline-scaffold/ ./`.
  - Merge the scaffold's `.gitignore` with ours; keep `.claude/settings.local.json` ignored.
- Databases:
  - Local Mac: DBngin Postgres plus the Herd site `shoreline.test`.
  - Claude Code cloud sessions (the `Shoreline` environment): the environment's setup script installs and starts Postgres inside the container.
  - `.env.example` defaults must work in both.
- Install Filament and the packages; Larastan, Pint, a `composer test` script, CLAUDE.md.
- Laravel Cloud **staging** environment: Postgres, two buckets, managed queue, scheduler, Cloudflare SMTP.
- Done when a deploy can send an email, run a job, and land an upload in R2.
- Spike in the Cloud Commands tab: imagick HEIC support, pg_trgm, outbound port 465.

**M1 Brand + public site**
- Design tokens, wordmark, door-rollup hero, all pages.
- Filament content resources (services, areas, FAQs, testimonials, showcases, settings) with MFA.
- SEO baseline and edge caching.
- Going live here also unblocks the Intuit questionnaire, since it needs the privacy and EULA URLs.

**M2 Leads**
- Quote wizard (HEIC conversion, Turnstile, honeypot, throttle).
- Owner alert and customer auto-reply.
- Lead inbox with the convert action.

**M3 Operations core**
- Clients, client_user, properties, projects (stage), line items, visits.
- **Parts and equipment:**
  - Parts catalog with suppliers, plus optional stock tracking and stock movements.
  - Line-item ordering workflow and the "Parts to order" page.
  - Installed-equipment tree per property.
  - "Record installed equipment" step when a project is completed.
- Calendar, kanban, tasks, timeline, activity log, non-money KPIs (including low stock), ICS feed.

**M4 Portal (no money yet)**
- Code/link login, invites, dashboard, project pages, photos, documents, messages.
- Impersonation banner.

**M5 QuickBooks inbound**
- Sandbox OAuth and TokenManager.
- Item and customer import with the matching wizard.
- Estimate, invoice, and payment mirrors (read-only).
- Webhooks, CDC, Sync Issues UI.
- Submit the Intuit production-key questionnaire.
- **First spike:** in the sandbox, confirm that `TxnStatus=Accepted` is writable, what `invoiceLink` requires, how AST behaves on estimates, merge-event semantics, and the CustomTxnNumbers behavior.

**M6 QuickBooks outbound + portal money**
- Two-way customer sync, estimates from projects (AST + ShipAddr), PDFs.
- Portal approval write-back, linked and deposit invoices, Pay Now, billing_status.

**M7 Dashboard + automations**
- P&L, A/R, and revenue widgets.
- Review requests, maintenance reminders, hurricane-season email.

**M8 Launch hardening**
- Accessibility, security, and performance pass; a backup-restore drill.
- Runbooks: QB reconnect, webhook replay, CDC backfill.
- **Production** environment on `shorelinedoors.com`: Cloudflare DNS-only records, CNAME flattening at the apex, Web Analytics snippet added by hand.
- Production Intuit keys. Seed the real owner account (the super admin email comes from an env var).

## Packages
- **composer:**
  - Core: `filament/filament:^5.10`, `league/flysystem-aws-s3-v3:^3.0`, `aws/aws-sdk-php:^3`, `brick/money`
  - Media and settings: `filament/spatie-laravel-media-library-plugin:^5.10`, `spatie/laravel-medialibrary:^11.23`, `filament/spatie-laravel-settings-plugin:^5.10`, `spatie/laravel-settings:^3.10`
  - Admin plugins: `spatie/laravel-activitylog:^5.1`, `pxlrbt/filament-activity-log:^3.1`, `stechstudio/filament-impersonate:^5.6`, `relaticle/flowforge:^4.3`, `guava/calendar:^3.2`
  - SEO: `spatie/laravel-sitemap:^8.2`, `spatie/schema-org`
  - Forms and login: `ryangjchandler/laravel-cloudflare-turnstile:^3.0`, `spatie/laravel-honeypot:^4.7`, `spatie/laravel-one-time-passwords:^1.1`, `propaganistas/laravel-phone:^6.1`
- **composer --dev:** `pestphp/pest:^5.3`, `pestphp/pest-plugin-laravel:^5`, `pestphp/pest-plugin-browser:^5.1`, `larastan/larastan:^3.13`, `laravel/pint`, `laravel/boost`
- **npm:** `tailwindcss@^4`, `@tailwindcss/vite`, `heic2any`, `@fontsource/*` (display and body fonts), dev `playwright`

## Verification
- **`composer test`:** Pest feature, unit, and browser tests, then Larastan and Pint. Key suites:
  - Quote wizard validation and spam guards (`Turnstile::fake()`).
  - Lead → client de-dupe.
  - Parts:
    - Stock movements stay consistent with `on_hand`.
    - Completing a project deducts stocked parts exactly once.
    - Installed equipment is pre-filled from line items.
    - Ordering status rolls up to the project's `materials_ordered` stage.
  - Magic-link and code login, including the scanner-safe confirm page and throttling.
  - **Cross-client isolation:** a client can never see another client's project, file, or invoice.
  - Panel access and MFA by role.
  - TokenManager: concurrent refresh under the row lock, `invalid_grant` handling.
  - QuickBooksClient: 401 retry, 429 release, 5010 stale retry, sparse updates.
  - Webhook: signature, realm filter, idempotency, out-of-order events.
  - Outbox echo guard; merge and inactive handling.
  - CDC cursor and the truncation guard.
  - Payment → billing_status math (partial, void, overpayment).
  - Estimate approval: SyncToken guard, write-back.
  - Edge-cache headers present on content routes and absent on quote and portal routes.
- **Local and visual:** run on Herd and check every public page at phone and desktop widths in the browser pane. Lighthouse on Home and a town page.
- **QuickBooks sandbox end-to-end** (webhooks and OAuth through `herd share` or the Cloud staging env):
  1. Connect and import.
  2. Create a client → it appears in QB. Edit it in QB → it updates here.
  3. Push an estimate → tax is computed for the right county. Approve it in the portal → QB shows Accepted.
  4. Invoice it → Pay Now opens QB's payment page.
  5. Record a partial, then a full payment in QB → the project goes partial, then paid, by webhook, and by CDC with webhooks disabled.
- **Staging and production smoke:**
  - The quote email arrives through Cloudflare.
  - Private URLs expire.
  - The Cloud Queues dashboard shows jobs processing.
  - The scheduler fires the hourly sweep.
  - The sitemap route is valid.
  - A content page's edge cache hits while the app is asleep.

## What Taylor or the owner will provide
- A Laravel Cloud account and a GitHub repo.
  - Plan choice at deploy time: Starter ($5) has 1 queue and **1-day log retention**. Growth ($20) has 7-day logs, basic auth for staging, and WAF rate limiting. Since monitoring is Cloud logs only, Growth is worth it.
- shorelinedoors.com DNS on Cloudflare, and Cloudflare Workers Paid ($5) for Email Sending.
- An Intuit Developer account; production keys come after the questionnaire.
- His CPA's sign-off on how tax and deposits map in QB.
- Real phone, address, license number, hours, photos, owner bio, and the Google Business Profile link. Placeholders stay until then, all editable in Settings.

## Local environment (checked 2026-10-21)
- Laravel Herd 1.30.1: PHP 8.5.10, Composer 2.10.2, Laravel installer 5.32.0.
- Node 24.12.0, npm 11.11.
- PHP extensions: pdo_pgsql, pgsql, imagick (reads HEIC/HEIF/AVIF/WebP locally), gd, intl, exif.
- DBngin and TablePlus are installed. No Postgres is on the PATH, so start Postgres from DBngin.
- No Docker.
- Git: initialized on `main` with remote `git@github.com:posidev-coding/shoreline.git`. It holds PLAN.md, `.gitignore`, and `.claude/`.

## Claude Code cloud sessions (`Shoreline` environment)
- Configuration lives in [.claude/cloud/README.md](.claude/cloud/README.md): the network allowlist, environment variables, the setup script ([.claude/cloud/setup-script.sh](.claude/cloud/setup-script.sh)), and a smoke check.
- A SessionStart hook ([.claude/hooks/cloud-session-start.sh](.claude/hooks/cloud-session-start.sh)) starts Postgres and installs dependencies in each cloud session. It does nothing locally.
- The cloud VM has PHP 8.5 (installed by the setup script), Node 22, and Postgres 16 with role `shoreline`/`shoreline` and databases `shoreline` and `shoreline_testing`. Create the same role locally in DBngin so `.env.example` works in both places.
- In the cloud: no Herd and no `herd share`. Use `php artisan serve`. `gh pr` and `gh issue` are blocked (GraphQL), so use `gh api` or the diff view's Create PR.

## Unverified: confirm early (QB sandbox spike in M5, Cloud spike in M0)
**QuickBooks sandbox**
- Whether Estimate `TxnStatus=Accepted`, `AcceptedBy`, and `AcceptedDate` can be written via a sparse update.
- What `invoiceLink` requires: `include=invoiceLink` on a GET, BillEmail, Payments enabled, the `AllowOnline*` flags.
- How AST behaves on Estimates (the docs only cover invoices).
- Whether a merge event's `intuitentityid` is the surviving record.
- Whether `Active IN (true,false)` queries work.
- Whether CustomTxnNumbers produces a blank DocNumber.
- Whether `:` is allowed in DisplayName.

**Laravel Cloud**
- Whether Cloud's imagick reads HEIC.
- Whether pg_trgm is available.
- Whether outbound SMTP on port 465 works.
- Whether Livewire's S3 temp-upload cleanup works with Cloud keys.
- Whether the WAF lets Intuit webhook POSTs through.

**Tooling**
- The Playwright setup for the Pest 5 browser plugin.

## Key references
**Laravel Cloud docs**
- Managed queues: https://laravel.com/cloud/docs/queues.md
- Scheduler: https://laravel.com/cloud/docs/scheduled-tasks.md
- Serverless Postgres: https://laravel.com/cloud/docs/resources/databases/postgres.md
- Object storage: https://laravel.com/cloud/docs/resources/object-storage.md
- Domains: https://laravel.com/cloud/docs/domains.md
- Network / edge cache: https://laravel.com/cloud/docs/network.md
- Docs index: https://laravel.com/cloud/docs/llms.txt

**Intuit / QuickBooks**
- OAuth: https://static.developer.intuit.com/output_html/qbo/docs/develop/authentication-and-authorization/oauth-2.0.html
- Webhooks: https://static.developer.intuit.com/output_html/qbo/docs/develop/webhooks/configure-webhooks.html
- CDC: https://static.developer.intuit.com/output_html/qbo/docs/learn/explore-the-quickbooks-online-api/change-data-capture.html
- Limits: https://static.developer.intuit.com/output_html/qbo/docs/learn/limits-and-throttles.html
- Automated sales tax: https://static.developer.intuit.com/output_html/qbo/docs/workflows/calculate-sales-tax/automated-sales-tax-for-us-locales.html
- Linked transactions: https://static.developer.intuit.com/output_html/qbo/docs/workflows/manage-linked-transactions.html
- Minor versions: https://static.developer.intuit.com/output_html/qbo/docs/learn/explore-the-quickbooks-online-api/minor-versions.html
- Partner program FAQ: https://developer.intuit.com/app/developer/qbo/docs/get-started/partner-faq

**Cloudflare Email**
- SMTP: https://developers.cloudflare.com/email-service/api/send-emails/smtp/
- Limits: https://developers.cloudflare.com/email-service/platform/limits/

**NC sales tax on RMI services:** https://ncdor.gov/documents/directives/sales-use-tax-directive-13-5/open

**Package READMEs:** Flowforge https://github.com/Relaticle/flowforge, Filament Impersonate https://github.com/stechstudio/filament-impersonate, Turnstile https://github.com/ryangjchandler/laravel-cloudflare-turnstile, activitylog v5 upgrade notes https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md

## Working agreements for the implementing session
- Work milestone by milestone, starting with M0. Finish each milestone with green `composer test`, then a visual check in the browser pane for UI work, then a commit.
- Before a milestone's first commit, create a branch for it if a GitHub remote exists.
- Ask Taylor before anything outward-facing: creating the GitHub repo, Laravel Cloud resources, DNS, Intuit app settings.
- Keep secrets out of the repo. `.env.example` lists every variable with placeholder values.
- Before using any package API, check its current docs with Laravel Boost (`search-docs`) or the package README. Several packages changed majors recently: activitylog v5, Pest 5, Filament 5, Livewire 4.
