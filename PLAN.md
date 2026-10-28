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
| Files | Laravel Cloud Object Storage (R2) with **two buckets**. `private` is the default disk and holds client photos, quote uploads, documents, and Livewire temp uploads, served via `temporaryUrl`. `public_media` holds gallery and site imagery. Cloud passes each bucket's public URL to its disk (confirmed on staging in M0), so `AWS_URL` isn't needed |
| Queue | Cloud Managed Queue (`QUEUE_CONNECTION=cloud`), Flex, 1 GiB memory. Jobs must finish in < 90 s, and delays/backoff can't exceed 900 s |
| Mail | Cloudflare Email Sending over SMTP (`MAIL_SCHEME=smtps`, `smtp.mx.cloudflare.net:465`, user `api_token`), always queued. Emails carry links rather than attachments (5 MiB cap). Cloudflare Email Routing forwards inbound `hello@` to the owner |
| Tests/quality | **Pest 5** + pest-plugin-browser, Larastan 3, Pint, Laravel Boost (dev) |
| Analytics | Cloudflare Web Analytics |

## Roles and access
- `users.role`: `super_admin | owner | client` (a string-backed enum).
- `client_user` pivot with a role (`primary | manager | billing | viewer`). One property-management company can have several logins, and one person can belong to several accounts. Built in M4 with the portal, decided in M3: nothing logs a client in before then.
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
- **clients**: display_name (unique ignoring case, trashed clients included, because QuickBooks refuses duplicate customer names), first/last name, company, email, phone (E.164), billing address, `is_property_manager`, `qb_mode` (`flat` | `sub_customer_per_property`), notes, plus the QB sync columns described below. `qb_mode` and the sync columns arrive with M5 and M6, where they mean something.
- **properties**:
  - client_id, label, address, county, service_area_id. No lat/lng, decided in M3: without a geocoding API nothing would fill them.
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
    - `catalog_item_id`, the QB Item it bills as. Arrives with M5's item import.
    - photo and spec-sheet media, active
  - Optional stock tracking: `track_stock`, `on_hand`, `reorder_point`, plus **part_stock_movements** (received / used on project / adjusted / returned).
    - The movements are a ledger: `on_hand` changes only through `App\Support\Stock`, which locks the part and writes a movement with the new balance, so the count always equals the sum of its movements.
    - Stock can go below zero, because he may use a part before anyone records receiving it.
    - A stocked part ordered for a job goes into stock when it's received, and comes out when the job is completed. Each line item is received once and used once, enforced by a partial unique index.
  - QB inventory items need QBO Plus, so stock lives in the app and QB just bills the item.
- **suppliers**: name, rep contact, account number, website, notes.
- **installed_equipment** (what's installed at a property):
  - property_id, `type` (door / opener / spring set / hardware / keypad / remote / sensor / smart hub / other), `part_id` (nullable)
  - **`parent_id`** for nesting, e.g. springs and opener belong to a door
  - manufacturer, model, serial, specs
  - Door-only fields: size, material, design pressure (+/−) with a photo of the label, impact-rated
  - `installed_on`, `installed_by_project_id`, `warranty_expires_on`, `last_serviced_on`, `maintenance_due_on` (dates, so `_on`)
  - status (active / replaced / removed) with `replaced_by_id`
  - Drives salt-air maintenance reminders, warranty lookups ("what spring is on this door?"), and repeat work.
- **leads**:
  - service, details, urgency, contact info (normalized for dedupe), address, photos
  - attribution (utm_*, gclid, landing page), consent flags
  - status (new / contacted / converted / spam)
  - converted: `projects.lead_id` points back to the request, and the project carries the client and property.
- **projects**:
  - client_id, property_id, service_id, lead_id, title, description (scope of work), photos (private)
  - client_id always follows the property, so the two can't disagree.
  - **`stage`**: `estimating → proposal_sent → approved → materials_ordered → scheduled → in_progress → completed → closed`, plus `on_hold`, `lost` (with `lost_reason`), and `cancelled`
  - **`billing_status`**, derived from invoices and payments: `unbilled | invoiced | partial | paid | overdue | void`. Arrives with M6.
  - The kanban board shows `stage` only.
- **visits**:
  - project_id, type (site measure / install / service / warranty), arrival window
  - status (tentative / confirmed / done). `client_notified_at`, `notified_starts_at`, and `notified_ends_at` record the window the client was last emailed (M4c).
  - Visits feed the calendar and a signed ICS feed the owner can subscribe to on his phone.
- **project_line_items**:
  - The working draft: `part_id` (optional; picking a part fills description, rate, and QB item), catalog_item_id, description, qty, rate, TAX/NON.
  - **Ordering fields:** supplier_id, `order_status` (to_order / ordered / received, null when nothing is ordered for the line), `expected_on`, plus `ordered_at` and `received_at` stamped as the status moves. These drive the `materials_ordered` stage (an approved job moves there when one of its parts is ordered) and the "Parts to order" page. `needs_ordering` was dropped in M3b: a null `order_status` says the same thing, without a way to disagree.
  - When a project is completed:
    - Stocked parts are deducted from stock.
    - The owner is prompted to record what was installed in installed_equipment, pre-filled from the line items.
- **project_tasks** (checklist), **project_updates** (timeline entries with a `visible_to_client` flag), **project_messages** (one portal thread per project, `is_from_client` for the side) with **project_message_reads** (`last_read_message_id` per person: an id, not a time, since two messages can share a second).
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
- **HEIC is converted on the server** (decided in M2; the plan had `heic2any` in the browser, but it was last released in 2023 and is 2.7 MB). Phones upload HEIC as is, and a queued job converts it to JPEG with Imagick. If Imagick can't decode it, the original stays attached; Safari shows HEIC natively.
- Spam protection: Turnstile (`ryangjchandler/laravel-cloudflare-turnstile`), honeypot, and throttling.
- Each submission creates a Lead, emails the owner, raises a Filament database notification, and sends the customer an auto-reply.

**SEO and performance**
- JSON-LD: `HomeAndConstructionBusiness` with areaServed, `Service`, `BreadcrumbList`. FAQ and review schema are nice-to-have only, since Google restricts those rich results.
- The sitemap is served from a **route** built from models, not a file (Cloud's filesystem is ephemeral).
- Canonical URLs, per-page meta, responsive WebP from medialibrary conversions for public media only.
- Target Lighthouse ≥ 95 on mobile.

**Caching and scale-to-zero:**
- Content routes (home, services, areas, work, FAQ) use a lean middleware group with no session or cookies, so a page view writes nothing to the database.
- They send `Cache-Control: public, s-maxage=600, stale-while-revalidate`, but Laravel Cloud's shared edge caches only static file types, never HTML (verified on staging in M1). Every page view reaches the app. Per-path cache rules need a Private Cloud dedicated edge.
- What keeps a sleeping environment fast is a current Flex size, which wakes in under 500 ms. Legacy Flex sizes take 5 to 20 s.
- Don't proxy the domain through our own Cloudflare zone to add a cache rule there. Cloud's purge on deploy wouldn't reach that cache, so after each deploy it would serve old pages that point at deleted CSS and JS files.
- The quote, portal, and admin routes keep the full `web` stack.

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
- SEO baseline and cookie-free content routes.
- Going live here also unblocks the Intuit questionnaire, since it needs the privacy and EULA URLs.

**M2 Leads**
- Quote wizard (HEIC conversion, Turnstile, honeypot, throttle).
- Owner alert and customer auto-reply.
- Lead inbox (new, contacted, spam). Convert moved to M3, decided in M2: it creates the clients, properties, and projects that M3 builds.

**M3 Operations core** (three PRs, each checked on staging)
- **M3a Clients, properties, projects:**
  - Clients, properties, projects (stage), line items, visits, tasks, timeline, project photos.
  - The lead inbox's "Convert to client + property + project" action. It suggests the client with the same email (ignoring case) or phone, and their property at the same address; the owner picks.
  - Activity log: each record's History page, and the full log for super admins. Property access info stays out of it.
  - Global search across clients, properties (by address), and projects.
  - Decided in M3a: `client_user` waits for M4. The QuickBooks columns wait for M5 and M6. No pg_trgm: Filament's search is a plain ILIKE, instant at this size. The quote request's photos stay on the lead and show on the project rather than being copied.
- **M3b Parts and equipment:**
  - Parts catalog with suppliers, category-specific details (a spring's wire size and wind, an opener's drive), and optional stock counts with Receive, Count, Return from a job, and Write off.
  - Line items can pick a part, which fills in the description, rate, and supplier, and can be marked to order, ordered, or received. The "Parts to order" page groups what's outstanding by supplier, with bulk actions.
  - Installed-equipment tree per property: each door with its springs, opener, and hardware under it.
  - Completing a job deducts its stocked parts and opens "Record installed equipment", drafted from the line items whose parts become equipment (doors, openers, springs, keypads, remotes, sensors, smart controllers, struts and wind braces), on the new door or the one already there. Where exactly one piece of the same kind is in place, it's suggested as the one being replaced.
  - Decided in M3b: cables, drums, rollers, tracks, hinges, weather seal, and other hardware aren't recorded as equipment, since they wear out and are replaced without a record. A part with history can't be deleted; turning off "Still sold" retires it.
- **M3c Board, schedule, dashboard:** calendar, kanban, non-money KPIs (including low stock), ICS feed.
  - **Board:** one column per open stage, plus jobs completed in the last 14 days. Lost, cancelled, and closed have no column; a card's "Change stage" reaches them, and asks why a job was lost. Completing a job on the board uses its stocked parts and links to "Record installed equipment". Cards keep their order within a column (`projects.board_position`); a job that changes stage anywhere else joins the end of its new column.
  - **Schedule:** the visits calendar. Click a time to book a visit, drag one to move it, click one to edit or delete it. Done visits stay put. Phones start on the week's list.
  - **Dashboard:** this week's quote requests, open jobs, visits, and low-stock parts; "Coming up" (today and the next six days, with directions and Mark confirmed / Mark done); new quote requests; low stock; parts to order or on the way. Weeks run Monday to Sunday in Eastern time. Nothing polls, so an open dashboard doesn't keep the app awake.
  - **ICS feed:** each admin makes a private link on the Schedule page. Only its hash is stored, so the link is shown once; making a new one or turning it off kills the old one. It carries names, addresses, phone numbers, and visit notes, never access info, and is served with no session and `Cache-Control: no-store`.
  - Decided in M3c: the owner uses **Google Calendar**, which refreshes a subscribed feed only every several hours and ignores the feed's refresh hint, so a visit booked that morning may not reach his phone that day. The Schedule and the dashboard are the same-day truth. Writing visits straight into his Google Calendar (a Google Cloud project and Workspace domain-wide delegation) would remove the lag; it's an option for later and needs Taylor's go-ahead for the outward-facing setup.

**M4 Portal (no money yet)** (three PRs, each checked on staging)
- **M4a Sign-in and access:**
  - `client_user` with `client_role` (primary / manager / billing / viewer) and `invited_at`. Not `role`: Filament puts a pivot's columns and casts on the related rows, where `users.role` already is.
  - A client's **Portal** tab: Invite to portal (their name and email filled in), Resend invitation, Change role, Remove access (removing someone's last access deletes them), and View their portal (impersonation, with its banner). Invitations, role changes, removals, and every impersonation go in the activity log.
  - **Sign-in:** an email address, then a 6-digit code, emailed with a link that carries it. The link opens a page whose button signs in (a POST), so a mail scanner opening the link can't use the code.
    - Codes last 15 minutes and work once. They're stored as an HMAC keyed with the app key: a plain hash of six digits can be reversed in a moment.
    - A code isn't tied to the browser or network that asked for it (the package's default), because the link is often opened in a mail app's browser or on another device.
    - Limits: 3 codes per address per 10 minutes and 10 per network per hour; 5 wrong codes per person per 10 minutes and 20 per network. The answer, and how long it takes, are the same whether or not the address has a portal.
    - A portal user stays signed in on the device.
  - **Portal:** the next visit, jobs under way, and finished jobs; a job's page has its address and visits. Lost, cancelled, and deleted jobs don't show. Job URLs use a ULID, and someone else's job is a 404.
  - Decided in M4a: visit notes, the scope of work, and project photos stay out of the portal until M4b decides what's shared, since the admin labels photos "Private" and nothing told the owner the scope is client-facing. Roles are stored but gate nothing until messages (M4c) and estimates (M6). Admins can view a client's portal; viewing as the owner waits for the Users resource. The site footer links "Client sign-in".
- **M4b Project page:** the client-visible timeline, photos, and documents.
  - **Shared by default:** new photos show in the portal; new documents don't. Every photo from before M4b stays private, since the owner took it when the admin said "Private: only you see them". A file shows only when its `in_portal` custom property is `true`, so a missing value means private and nothing needed migrating.
  - A job's **Files** tab lists its photos and documents, each with an "In the portal" switch (and bulk Show and Hide), Open, and Delete. On the job's page, a shared photo is marked "In the portal".
  - **Documents** (warranty papers, permits, manuals) are a second private collection on the job, uploaded on its Edit page: PDF, JPEG, PNG, WebP, Word, and Excel, up to the same 20 MB. They keep the name they were uploaded with, so the client's download has a sensible name. The owner is the only uploader.
  - **Portal job page:** visits, then the shared updates (dated, never signed with who wrote them), photos (a thumbnail grid opening the full photo), and documents. A section with nothing shared doesn't show. Files open through 30-minute temporary links, the same as in the admin.
  - Decided in M4b: the scope of work and visit notes stay out of the portal. The owner wrote both believing them private, and a shared timeline entry is a deliberate way to tell the client something. Showing the scope is a one-line change if he wants it. Sharing a file isn't written to the activity log.
- **M4c Messages and notification emails:** one thread per job, and emails for a new message, a visit confirmed, moved, or called off, and a job finished.
  - **Thread:** on the job's portal page (after the updates) and on the job's **Messages** tab in the admin, whose badge counts the client's unread messages. Opening either marks it read for that person. The portal dashboard marks a job with unread messages. Messages from the business are signed with its name, never the admin's. Plain text, up to 5,000 characters; a portal user can send 30 an hour.
  - **Roles, decided in M4c:** Primary, Manager, and Billing can send messages; a Viewer reads only. Message emails go to those who can reply. Visit and job-finished emails go to Primary, Manager, and Viewer; Billing waits for the invoice emails in M6. The invite form's role descriptions say so.
  - **Only portal users are emailed.** A client without portal access hears from the owner as before.
  - **New message:** emailed 5 minutes later, and only by the newest unread message's notification, so a burst sends one email listing them all, and someone reading along in the portal gets none. The owner also gets it in the admin's bell; replying to his email reaches the client directly, and the email says only a reply on the job shows in their portal.
  - **Visits:** a tentative visit sends nothing (it shows in the portal as "We’ll confirm the time with you"). Confirming one, or moving a confirmed one, emails 2 minutes later if its window still differs from the one last emailed, so a few quick drags on the calendar send one email, and moving it back sends none. Deleting a visit the client was told about, before it happened, emails that it's called off. The visit form says when the client is emailed, and the Visits tab shows when they were.
  - **Job finished:** emailed 2 minutes after the job is marked completed (or closed), if it still is, so a card dropped on Completed by mistake and moved back sends nothing. Reopening and finishing again sends another.
  - Admins viewing a client's portal can read the thread but can't post from it or mark it read.
  - Decided in M4c: no unsubscribe link. These are transactional emails about the client's own jobs, which CAN-SPAM doesn't require one for; `email_preferences` arrives with review requests and the hurricane-prep email, which do need one. No attachments in messages, and replies by email don't land in the thread.

**M5 QuickBooks inbound** (three PRs, each checked on staging)
- **M5a Connect:**
  - `quickbooks_connections` (one row: reconnecting the same company updates it; another company is refused until this one is forgotten) and `quickbooks_sync_logs` (every call to Intuit with its `intuit_tid`, kept 30 days by the weekly `model:prune`). Both tables need `#[Table]`: Laravel makes `quick_books_…` of the class names.
  - **TokenManager:** connect, refresh, disconnect, as described under "Client and tokens". A refresh skips itself when another process refreshed first (or, after a 401, when the stored token is no longer the rejected one). Token-call logs are written after the transaction, so a failed refresh, which rolls back, keeps its log.
  - **QuickBooksClient:** get, post, read, `queryAll` (pages of up to 1,000), and the company's details. A 401 refreshes and retries once; a 429 is a rate-limited `QuickBooksException` for the job to back off (the job middleware arrives with the first jobs, in M5b).
  - **Settings → QuickBooks** (super admins): Connect, Check the connection, Disconnect (revokes with Intuit; disconnects even if the revoke fails), Forget this company. It shows the company, the tokens' expiries, the exact redirect URI, and four company settings from QuickBooks' Preferences. Intuit's sign-in returns to `/admin/quickbooks/callback`, a panel page (so it has the admin's sign-in and MFA) that checks the OAuth `state` this session sent.
  - **`app:quickbooks-refresh`**, daily: refreshes even a fresh token, so the refresh token never lapses and each rotated one is saved, reloads the company's details, and reminds super admins 60, 30, 14, 7, 3, and 1 days before the five-year limit. `invalid_grant` disconnects and tells them.
  - Decided in M5a: the sandbox test needs a connected company, so it runs from staging's Commands tab after Connect works there, as a command in M5b. A cloud session can call the sandbox but can't receive Intuit's redirect.
- **M5b Import:** the sandbox test, items into `catalog_items`, then customers with the matching wizard.
- **M5c Inbound:** estimate, invoice, and payment mirrors (read-only); webhooks, the CDC sweep, and the Sync Issues screen. Staging's HTTP basic auth will refuse Intuit's webhooks, which can't send a password, so this needs a path exempted or another way in. Then submit the Intuit production-key questionnaire.
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
- **Mail authentication**, because Cloudflare Email Sending put a `p=reject` DMARC policy on the domain (see [docs/laravel-cloud.md](docs/laravel-cloud.md), step 4):
  - Check that the owner's Google Workspace mail passes SPF and DKIM. SPF went in on October 27; Google's DKIM key (`google._domainkey`) is still to add.
  - Check that nothing else, like a scheduling app or a phone system, sends as `@shorelinedoors.com` without passing too.
  - Add a DMARC report address (`rua`), so a failing sender shows up before customers notice.
- Production Intuit keys. Seed the real owner account (the super admin email comes from an env var).

## Packages
- **composer:**
  - Core: `filament/filament:^5.10`, `league/flysystem-aws-s3-v3:^3.0`, `aws/aws-sdk-php:^3`, `brick/money`
  - Media and settings: `filament/spatie-laravel-media-library-plugin:^5.10`, `spatie/laravel-medialibrary:^11.23`, `filament/spatie-laravel-settings-plugin:^5.10`, `spatie/laravel-settings:^3.10`
  - Admin plugins: `spatie/laravel-activitylog:^5.1`, `pxlrbt/filament-activity-log:^3.1`, `stechstudio/filament-impersonate:^5.6`, `relaticle/flowforge:^4.3`, `guava/calendar:^3.2`
  - SEO: `spatie/laravel-sitemap:^8.2`, `spatie/schema-org`
  - Forms and login: `ryangjchandler/laravel-cloudflare-turnstile:^3.0`, `spatie/laravel-honeypot:^4.7`, `spatie/laravel-one-time-passwords:^1.1`, `propaganistas/laravel-phone:^6.1`
- **composer --dev:** `pestphp/pest:^5.3`, `pestphp/pest-plugin-laravel:^5`, `pestphp/pest-plugin-browser:^5.1`, `larastan/larastan:^3.13`, `laravel/pint`, `laravel/boost`
- **npm:** `tailwindcss@^4`, `@tailwindcss/vite`, `@fontsource/*` (display and body fonts), dev `playwright`

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
  - A content page answers in about a second while the environment is asleep.

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
- The name of the token response field that reports the refresh token's five-year limit. The app asks for it with `x-include-refresh-token-hard-expires-in: true` and reads `x_refresh_token_hard_expires_in`; the sync log records which fields each token response had, without their values.
- What the Preferences flags shown on the QuickBooks page mean: `TaxPrefs.PartnerTaxEnabled` for automated sales tax, and `SalesFormsPrefs.AllowOnlineCreditCardPayment` and `AllowOnlineACHPayment` for QB Payments.

**Laravel Cloud**
- ~~Whether Cloud's imagick decodes HEIC.~~ Verified on staging in M2: `app:cloud-smoke` decoded a sample HEIC (320×240). M0 had shown only that imagick *lists* HEIC, which proves nothing: a cloud session's imagick listed it too and couldn't decode one until libheif's HEVC plugin (`libheif-plugin-libde265`) was installed. The sample is a simple single-image HEIC, while iPhones save tiled images with depth data, so a real iPhone upload through the quote form is the last check.
- ~~Whether quote photos reach R2 and the lead from a real phone.~~ Verified on staging in M2:
  - The first iPhone quote 500'd: Livewire's S3 driver takes one file per upload, so the form now uploads each photo separately.
  - The next one uploaded to R2, but the queued job lost its photos: one converted to an 11.96 MB file, over the media library's 10 MB default. The limits are now one 20 MB setting, HEIC conversions are scaled to 12 MP, and one refused photo no longer takes the others down.
  - After that fix, the photos attached and showed in `/admin`.
- ~~Whether pg_trgm is available.~~ Verified on staging in M0: pg_trgm 1.6 is available, not yet installed. The first migration that needs it runs `create extension if not exists pg_trgm`. pg_trgm is a trusted extension, so the app's role should be allowed to create it; that migration's deploy confirms it.
- ~~Whether outbound SMTP on port 465 works.~~ Verified on staging in M0: `smtp.mx.cloudflare.net:465` is reachable over TLS (55 ms), and a queue worker delivered a test message through Cloudflare.
- ~~Whether Cloud's edge caches HTML.~~ Verified on staging in M1: it doesn't. `/services`, sent with `public, s-maxage=600` and no app cookies, came back `cf-cache-status: DYNAMIC`. The shared edge caches by file extension only. Staging runs a current Flex size, which wakes in under 500 ms, so the site doesn't need the HTML cache.
- ~~Whether Livewire's S3 temp-upload cleanup works with Cloud keys.~~ Not needed: `app:prune-uploads` deletes abandoned uploads daily instead of a bucket lifecycle rule.
- Whether `request()->ip()` is the visitor on Cloud. The quote form prefers Cloudflare's `CF-Connecting-IP` there; the admin shows each lead's IP so staging can confirm it.
- Whether the WAF lets Intuit webhook POSTs through.

**Tooling**
- ~~The Playwright setup for the Pest 5 browser plugin.~~ Verified in M0: pest-plugin-browser 5.1.2 drives Chromium build 1248 through the project's `playwright@1.64.1` (the plugin needs ≥ 1.63.0).

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
